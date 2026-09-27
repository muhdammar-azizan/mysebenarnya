<?php

namespace App\Notifications;

use App\Models\Agency;
use Illuminate\Notifications\Notification;

class NewAgencyRegistered extends Notification
{
    public function __construct(
        public Agency $agency,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'agency_id' => $this->agency->id,
            'message' => "New agency registered: '{$this->agency->name}'",
        ];
    }
}
