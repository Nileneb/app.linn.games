<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(private string $inviteUrl) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Deine Einladung zu app.linn.games')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Du wurdest eingeladen, app.linn.games zu nutzen.')
            ->action('Einladung annehmen', $this->inviteUrl)
            ->line('Dieser Link ist 28 Tage gültig.')
            ->line('Falls du diese E-Mail nicht erwartet hast, kannst du sie ignorieren.');
    }
}
