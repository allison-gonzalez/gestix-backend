<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MongoDB\Client as MongoClient;

class BackupDatabase extends Command
{
    protected $signature   = 'backup:database {--keep= : Number of backups to retain (overrides config)}';
    protected $description = 'Create a MongoDB backup and prune old ones';

    private function loadConfig(): array
    {
        $path = storage_path('app/backup_schedule.json');
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true) ?? [];
        }
        return [];
    }

    public function handle(): int
    {
        $backupPath = storage_path('app/backups');
        $mongoUri   = config('database.connections.mongodb.dsn');
        $dbName     = config('database.connections.mongodb.database');

        $config = $this->loadConfig();
        $keep   = $this->option('keep') !== null
                    ? (int) $this->option('keep')
                    : (int) ($config['retention'] ?? 7);

        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        // ── Create backup ────────────────────────────────────────────────────
        try {
            $client = new MongoClient($mongoUri);
            $db     = $client->selectDatabase($dbName);

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename  = "backup_{$dbName}_{$timestamp}.json";
            $filepath  = "{$backupPath}/{$filename}";

            $data = [
                'database'    => $dbName,
                'created_at'  => now()->toIso8601String(),
                'collections' => [],
            ];

            foreach ($db->listCollections() as $col) {
                $name = $col->getName();
                $docs = [];
                foreach ($db->selectCollection($name)->find() as $doc) {
                    $docs[] = json_decode(json_encode($doc), true);
                }
                $data['collections'][$name] = $docs;
            }

            file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $sizeKb = round(filesize($filepath) / 1024, 1);
            $this->info("Backup creado: {$filename} ({$sizeKb} KB)");
        } catch (\Exception $e) {
            $this->error("Error creando backup: " . $e->getMessage());
            return Command::FAILURE;
        }

        // ── Prune old backups ────────────────────────────────────────────────
        if ($keep > 0) {
            $files = glob("{$backupPath}/backup_*.json");
            if ($files) {
                usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                $toDelete = array_slice($files, $keep);
                foreach ($toDelete as $old) {
                    unlink($old);
                    $this->line("Eliminado backup antiguo: " . basename($old));
                }
            }
        }

        return Command::SUCCESS;
    }
}
