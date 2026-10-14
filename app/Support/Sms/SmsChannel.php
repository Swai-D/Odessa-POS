<?php

namespace App\Support\Sms;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/** Notification channel "sms": the notification supplies toSms(), the route supplies the number. */
class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $route = $notifiable instanceof AnonymousNotifiable ? $notifiable->routeNotificationFor('sms') : $notifiable->routeNotificationFor('sms', $notification);
        $to = PhoneNumber::normalize(is_string($route) ? $route : null);

        if ($to === null || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->gateway->send($to, (string) $notification->toSms($notifiable));
    }
}
