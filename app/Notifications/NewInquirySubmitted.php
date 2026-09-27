<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Notifications\Notification;

class NewInquirySubmitted extends Notification
{
    public function __construct(
        public Inquiry $inquiry,
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
            'message' => "New inquiry submitted: '{$this->inquiry->title}'",
        ];
    }
}
