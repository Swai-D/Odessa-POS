<?php

namespace App\Support\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * NextSMS (messaging-service.co.tz). Basic auth, JSON body {from, to, text, reference}.
 * A rejected message (bad number, no balance) is a final answer; network errors and 5xx are temporary.
 */
class NextSmsGateway implements SmsGateway
{
    public function send(string $to, string $text, ?string $reference = null): SmsResult
    {
        $cfg = (array) config('notifications.sms.nextsms');

        try {
            $response = Http::withBasicAuth((string) $cfg['username'], (string) $cfg['password'])
                ->acceptJson()
                ->asJson()
                ->timeout((int) $cfg['timeout'])
                ->post(rtrim((string) $cfg['base_url'], '/').'/'.ltrim((string) $cfg['send_path'], '/'), [
                    'from' => (string) config('notifications.sms.sender_id'),
                    'to' => $to,
                    'text' => $text,
                    'reference' => $reference ?? uniqid('odessa_', true),
                ]);
        } catch (ConnectionException $e) {
            throw new SmsFailedException('SMS provider unreachable: '.$e->getMessage(), 0, $e);
        }

        if ($response->serverError()) {
            throw new SmsFailedException('SMS provider error '.$response->status());
        }

        if ($response->failed()) {
            return new SmsResult(false, $reference, 'HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200));
        }

        $message = $response->json('messages.0');
        $groupId = is_array($message) ? (int) ($message['status']['groupId'] ?? 0) : 0;

        // Group 1 = pending, 3 = delivered, 2 = undeliverable, 4 = expired, 5 = rejected(provider-specific).
        if (is_array($message) && in_array($groupId, [2, 4, 5, 19], true)) {
            return new SmsResult(false, $reference, (string) ($message['status']['description'] ?? 'Rejected'));
        }

        return new SmsResult(true, is_array($message) ? (string) ($message['messageId'] ?? $reference) : $reference);
    }
}
