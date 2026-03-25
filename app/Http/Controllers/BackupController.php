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
}
