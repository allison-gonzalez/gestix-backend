<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo automático configurable por el usuario (lee backup_schedule.json)
Schedule::command('backup:database')
    ->everyMinute()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('Backup command failed to execute');
    })
    ->onSuccess(function () {
        \Log::info('Backup command executed successfully');
    })
    ->when(function () {
        $configFile = storage_path('app/backup_schedule.json');
        if (!file_exists($configFile)) {
            \Log::debug("Backup config file not found");
            return false;
        }

        $config = json_decode(file_get_contents($configFile), true);

        if (empty($config['enabled'])) {
            \Log::debug("Backup disabled in config");
            return false;
        }

        $now = now();
        [$hour, $minute] = array_map('intval', explode(':', $config['time'] ?? '02:00'));

        \Log::debug("Backup schedule check", [
            'configured_time' => $config['time'],
            'current_time'    => $now->format('H:i:s'),
            'frequency'       => $config['frequency'] ?? 'daily',
            'matches'         => ($now->hour === $hour && $now->minute === $minute)
        ]);

        if ($now->hour !== $hour || $now->minute !== $minute) {
            return false;
        }

        $result = match ($config['frequency'] ?? 'daily') {
            'daily'   => true,
            'weekly'  => $now->dayOfWeek === (int) ($config['day_of_week'] ?? 1),
            'monthly' => $now->day       === (int) ($config['day_of_month'] ?? 1),
            default   => false,
        };

        \Log::info("Backup schedule condition", ['result' => $result]);
        return $result;
    });
