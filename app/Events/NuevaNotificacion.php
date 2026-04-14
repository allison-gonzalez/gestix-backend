<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class NuevaNotificacion implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public array $notificacion;

    public function __construct(array $notificacion)
    {
        $this->notificacion = $notificacion;
    }

    /**
     * Canal público — el frontend filtra por receptor_id cuando aplica.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('gestix-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'nueva-notificacion';
    }
}
