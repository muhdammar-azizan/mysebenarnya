<?php

namespace App\Notifications;

use App\Models\Agency;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgencyStaffInvited extends Notification
{
    public function __construct(
        public Agency $agency,
        public string $temporaryPassword,
        public string $invitedBy,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been added to '.$this->agency->name.' on SEBENARNYA.MY')
            ->greeting('Welcome, '.$notifiable->name)
            ->line($this->invitedBy.' has added you as a staff member of '.$this->agency->name.' on SEBENARNYA.MY.')
            ->line('Your login email: '.$notifiable->email)
            ->line('Your temporary password: '.$this->temporaryPassword)
            ->line('For security, you will be required to set a new password the first time you log in.')
            ->action('Log In', route('login'));
    }
}
