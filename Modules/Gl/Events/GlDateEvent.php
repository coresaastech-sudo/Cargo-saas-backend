<?php

namespace Modules\Gl\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class GlDateEvent implements ShouldBroadcast
{
    use SerializesModels;
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $connection = 'sync';
    private $data;
    private $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($data, $user)
    {
        $this->data = $data;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('gldate-channel.' . $this->user->instid);
    }

    public function broadcastAs()
    {
        return 'gldate-event';
    }

    public function broadcastWith()
    {
        return [
            'data' => $this->data
        ];
    }
}
