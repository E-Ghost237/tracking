<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationChannel;
use App\Mail\TemplatedMail;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Mail;

class MailChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'mail';
    }

    public function deliver(NotificationLog $log, array $message): ?string
    {
        $sent = Mail::to($log->recipient)->send(new TemplatedMail(
            subjectLine: $message['subject'],
            htmlBody: $message['body'],
            unsubscribeUrl: $message['unsubscribe_url'],
            mailLocale: $message['locale'],
        ));

        return $sent?->getMessageId();
    }
}
