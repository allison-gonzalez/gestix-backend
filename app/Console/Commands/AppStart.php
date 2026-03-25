<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AppStart extends Command
{
    protected $signature   = 'app:start {--host=127.0.0.1} {--port=8000}';
    protected $description = 'Start the development server and the backup scheduler together';

    public function handle(): int
    {
        $host = $this->option('host');
        $port = $this->option('port');

        $this->info("Iniciando Gestix Backend en http://{$host}:{$port}");
        $this->info("Iniciando scheduler de respaldos automáticos...");
        $this->line('Presiona Ctrl+C para detener.');
        $this->newLine();

        $serve    = new Process(['php', 'artisan', 'serve', "--host={$host}", "--port={$port}"]);
        $scheduler = new Process(['php', 'artisan', 'schedule:work']);

        $serve->setWorkingDirectory(base_path())->setTimeout(null);
        $scheduler->setWorkingDirectory(base_path())->setTimeout(null);

        $serve->start(function ($type, $output) {
            $this->output->write("<fg=cyan>[server]</> {$output}");
        });

        $scheduler->start(function ($type, $output) {
            $this->output->write("<fg=yellow>[scheduler]</> {$output}");
        });

        // Keep alive while both processes run
        while ($serve->isRunning() || $scheduler->isRunning()) {
            $serve->checkTimeout();
            $scheduler->checkTimeout();
            usleep(200_000); // 200 ms
        }

        if (!$serve->isSuccessful()) {
            $this->error("El servidor se detuvo: " . $serve->getErrorOutput());
        }
        if (!$scheduler->isSuccessful()) {
            $this->error("El scheduler se detuvo: " . $scheduler->getErrorOutput());
        }

        return Command::SUCCESS;
    }
}
