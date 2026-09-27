<?php

namespace App\Notifications;

use App\Models\ClarificationConsult;
use Illuminate\Notifications\Notification;

class AgencyConsultRequested extends Notification
{
    public function __construct(
        public ClarificationConsult $consult,
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
        $inquiryTitle = $this->consult->thread->inquiry->title;

        return [
            'consult_id' => $this->consult->id,
            'inquiry_id' => $this->consult->thread->inquiry_id,
            'message' => "MCMC has asked your agency for advice on: '{$inquiryTitle}'",
        ];
    }
}
