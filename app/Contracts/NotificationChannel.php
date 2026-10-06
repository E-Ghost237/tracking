<?php

namespace App\Contracts;

use App\Models\NotificationLog;

/**
 * Delivery channel for templated notifications. Email in v1; SMS and WhatsApp later (section 6.2).
 */
interface NotificationChannel
{
    public function name(): string;

    /**
     * @param  array{subject: string, body: string, unsubscribe_url: ?string, locale: string}  $message
     */
    public function deliver(NotificationLog $log, array $message): ?string;
}
