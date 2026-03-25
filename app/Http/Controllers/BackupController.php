<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use MongoDB\Client as MongoClient;

class BackupController extends Controller
{
    private string $backupPath;
    private string $mongoUri;
    private string $dbName;

    public function __construct()
    {
        $this->backupPath = storage_path('app/backups');
        $this->mongoUri   = config('database.connections.mongodb.dsn');
        $this->dbName     = config('database.connections.mongodb.database');

        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Información de la base de datos
     */
    public function info()
    {
        try {
            $client = new MongoClient($this->mongoUri);
            $db     = $client->selectDatabase($this->dbName);

            $collections = [];
            $totalDocs   = 0;
            foreach ($db->listCollections() as $collection) {
                $name  = $collection->getName();
                $count = (int) $db->selectCollection($name)->countDocuments();
                $totalDocs += $count;
                $collections[] = [
                    'name'  => $name,
                    'count' => $count,
                ];
            }

            $backups = $this->getBackupList();

            return response()->json([
                'database'      => $this->dbName,
                'collections'   => $collections,
                'total_docs'    => $totalDocs,
                'backups_count' => count($backups),
                'last_backup'   => count($backups) > 0 ? $backups[0]['created_at'] : null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear un nuevo backup
     */
    public function create(Request $request)
    {
        try {
            $client = new MongoClient($this->mongoUri);
            $db     = $client->selectDatabase($this->dbName);

            $timestamp  = now()->format('Y-m-d_H-i-s');
            $filename   = "backup_{$this->dbName}_{$timestamp}.json";
            $filepath   = "{$this->backupPath}/{$filename}";

            $data = ['database' => $this->dbName, 'created_at' => now()->toIso8601String(), 'collections' => []];

            foreach ($db->listCollections() as $col) {
                $name = $col->getName();
                $docs = [];
                foreach ($db->selectCollection($name)->find() as $doc) {
                    $docs[] = json_decode(json_encode($doc), true);
                }
                $data['collections'][$name] = $docs;
            }

            file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return response()->json([
                'message'    => 'Backup creado exitosamente',
                'filename'   => $filename,
                'size'       => $this->formatSize(filesize($filepath)),
                'created_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar backups disponibles
     */
    public function list()
    {
        try {
            return response()->json($this->getBackupList());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Descargar un backup
     */
    public function download(string $filename)
    {
        $filepath = "{$this->backupPath}/{$filename}";

        if (!file_exists($filepath)) {
            return response()->json(['error' => 'Backup no encontrado'], 404);
        }

        // Validate filename to prevent path traversal
        if (basename($filename) !== $filename || !str_starts_with($filename, 'backup_')) {
            return response()->json(['error' => 'Nombre de archivo inválido'], 400);
        }

        return response()->download($filepath);
    }

    /**
     * Eliminar un backup
     */
    public function delete(string $filename)
    {
        // Validate filename to prevent path traversal
        if (basename($filename) !== $filename || !str_starts_with($filename, 'backup_')) {
            return response()->json(['error' => 'Nombre de archivo inválido'], 400);
        }

        $filepath = "{$this->backupPath}/{$filename}";

        if (!file_exists($filepath)) {
            return response()->json(['error' => 'Backup no encontrado'], 404);
        }

        unlink($filepath);

        return response()->json(['message' => 'Backup eliminado exitosamente']);
    }

    /**
     * Leer la configuración del schedule
     */
    public function schedule()
    {
        $config  = $this->loadScheduleConfig();
        $nextRun = $this->calculateNextRun($config);

        $logPath = storage_path('logs/backup.log');
        $lastLog = null;
        if (file_exists($logPath)) {
            $lines   = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lastLog = !empty($lines) ? end($lines) : null;
        }

        // Get server timezone offset in hours
        $serverTimezone = now()->getOffsetString(); // e.g., "+06:00"
        $offsetHours = intval(substr($serverTimezone, 0, 3)); // Extract hours

        return response()->json(array_merge($config, [
            'next_run'           => $nextRun,
            'last_log'           => $lastLog,
            'server_timezone'    => $serverTimezone,
            'server_offset_hours' => $offsetHours,
        ]));
    }

    /**
     * Guardar la configuración del schedule
     */
    public function updateSchedule(Request $request)
    {
        $validated = $request->validate([
            'enabled'      => 'required|boolean',
            'frequency'    => 'required|in:daily,weekly,monthly',
            'time'         => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'day_of_week'  => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'retention'    => 'required|integer|min:1|max:365',
        ]);

        $config = [
            'enabled'      => (bool)  $validated['enabled'],
            'frequency'    =>         $validated['frequency'],
            'time'         =>         $validated['time'],
            'day_of_week'  => (int)  ($validated['day_of_week']  ?? 1),
            'day_of_month' => (int)  ($validated['day_of_month'] ?? 1),
            'retention'    => (int)   $validated['retention'],
        ];

        file_put_contents(
            storage_path('app/backup_schedule.json'),
            json_encode($config, JSON_PRETTY_PRINT)
        );

        return response()->json(array_merge($config, [
            'next_run' => $this->calculateNextRun($config),
            'message'  => 'Configuración guardada exitosamente',
        ]));
    }

    /**
     * Restaurar un backup
     */
    public function restore(string $filename)
    {
        // Validate filename to prevent path traversal
        if (basename($filename) !== $filename || !str_starts_with($filename, 'backup_')) {
            return response()->json(['error' => 'Nombre de archivo inválido'], 400);
        }

        $filepath = "{$this->backupPath}/{$filename}";

        if (!file_exists($filepath)) {
            return response()->json(['error' => 'Backup no encontrado'], 404);
        }

        try {
            $data   = json_decode(file_get_contents($filepath), true);
            $client = new MongoClient($this->mongoUri);
            $db     = $client->selectDatabase($this->dbName);

            $restored = 0;
            foreach ($data['collections'] as $collectionName => $documents) {
                $collection = $db->selectCollection($collectionName);
                $collection->deleteMany([]);
                if (!empty($documents)) {
                    $documents = array_map([$this, 'convertExtendedJson'], $documents);
                    $collection->insertMany($documents);
                    $restored += count($documents);
                }
            }

            return response()->json([
                'message'           => 'Base de datos restaurada exitosamente',
                'collections'       => count($data['collections']),
                'documents_restored' => $restored,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function getBackupList(): array
    {
        if (!is_dir($this->backupPath)) {
            return [];
        }

        $files = glob("{$this->backupPath}/backup_*.json");
        if (!$files) return [];

        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'filename'   => basename($file),
                'size'       => $this->formatSize(filesize($file)),
                'size_bytes' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        usort($backups, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2)    . ' KB';
        return $bytes . ' B';
    }

    private function loadScheduleConfig(): array
    {
        $path = storage_path('app/backup_schedule.json');
        if (file_exists($path)) {
            $config = json_decode(file_get_contents($path), true);
            if (is_array($config)) {
                return $config;
            }
        }

        // Defaults
        return [
            'enabled'      => true,
            'frequency'    => 'daily',
            'time'         => '02:00',
            'day_of_week'  => 1,
            'day_of_month' => 1,
            'retention'    => 7,
        ];
    }

    private function calculateNextRun(array $config): ?string
    {
        if (empty($config['enabled'])) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $config['time'] ?? '02:00'));
        $now  = now();
        $next = $now->copy()->setTime($hour, $minute, 0);

        return match ($config['frequency'] ?? 'daily') {
            'daily' => $next->isPast() ? $next->addDay()->toDateTimeString()
                                       : $next->toDateTimeString(),

            'weekly' => (function () use ($now, $next, $config, $hour, $minute) {
                $target = (int) ($config['day_of_week'] ?? 1);
                $days   = ($target - $now->dayOfWeek + 7) % 7;
                if ($days === 0 && $next->isPast()) $days = 7;
                return $next->addDays($days)->toDateTimeString();
            })(),

            'monthly' => (function () use ($now, $config, $hour, $minute) {
                $day  = (int) ($config['day_of_month'] ?? 1);
                $next = $now->copy()->setDay($day)->setTime($hour, $minute, 0);
                if ($next->isPast()) $next->addMonth();
                return $next->toDateTimeString();
            })(),

            default => null,
        };
    }

    /**
     * Converts MongoDB Extended JSON arrays (produced by json_decode) back to
     * proper BSON types so insertMany works correctly on restore.
     */
    private function convertExtendedJson(array $doc): array
    {
        foreach ($doc as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if (isset($value['$oid'])) {
                $doc[$key] = new \MongoDB\BSON\ObjectId($value['$oid']);
            } elseif (isset($value['$date'])) {
                $dateVal = $value['$date'];
                if (is_array($dateVal) && isset($dateVal['$numberLong'])) {
                    $doc[$key] = new \MongoDB\BSON\UTCDateTime((int) $dateVal['$numberLong']);
                } elseif (is_string($dateVal)) {
                    $doc[$key] = new \MongoDB\BSON\UTCDateTime((int) (strtotime($dateVal) * 1000));
                }
            } elseif (isset($value['$numberLong'])) {
                $doc[$key] = (int) $value['$numberLong'];
            } elseif (isset($value['$numberInt'])) {
                $doc[$key] = (int) $value['$numberInt'];
            } elseif (isset($value['$numberDouble'])) {
                $doc[$key] = (float) $value['$numberDouble'];
            } else {
                $doc[$key] = $this->convertExtendedJson($value);
            }
        }

        return $doc;
    }
}
