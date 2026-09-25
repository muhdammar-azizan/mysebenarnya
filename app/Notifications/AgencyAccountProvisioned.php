<?php

namespace App\Notifications;

use App\Models\Agency;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgencyAccountProvisioned extends Notification
{
    public function __construct(
        public Agency $agency,
        public string $temporaryPassword,
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
            ->subject('Your SEBENARNYA.MY agency account')
            ->greeting('Welcome, '.$notifiable->name)
            ->line('MCMC has registered '.$this->agency->name.' as a verification agency on SEBENARNYA.MY.')
            ->line('Your login email: '.$notifiable->email)
            ->line('Your temporary password: '.$this->temporaryPassword)
            ->line('For security, you will be required to set a new password the first time you log in.')
            ->action('Log In', route('login'));
    }
}
