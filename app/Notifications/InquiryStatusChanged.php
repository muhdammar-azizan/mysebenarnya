<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Notifications\Notification;

class InquiryStatusChanged extends Notification
{
    public function __construct(
        public Inquiry $inquiry,
        public string $fromStatus,
        public string $toStatus,
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
            'inquiry_id' => $this->inquiry->id,
            'inquiry_title' => $this->inquiry->title,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'message' => "Your inquiry '{$this->inquiry->title}' is now {$this->toStatus}.",
        ];
    }
}
