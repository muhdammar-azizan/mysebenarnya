<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Notifications\Notification;

class InquirySlaReminder extends Notification
{
    public const STAGE_TRIAGE = 'triage';

    public const STAGE_INVESTIGATION = 'investigation';

    public function __construct(
        public Inquiry $inquiry,
        public string $stage,
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
        $message = $this->stage === self::STAGE_TRIAGE
            ? "Overdue for triage: '{$this->inquiry->title}' has been waiting more than ".config('sla.triage_days').' days.'
            : "Overdue investigation: '{$this->inquiry->title}' has been under investigation for more than ".config('sla.investigation_days').' days.';

        return [
            'inquiry_id' => $this->inquiry->id,
            'inquiry_title' => $this->inquiry->title,
            'message' => $message,
        ];
    }
}
