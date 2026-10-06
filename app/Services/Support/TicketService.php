<?php

namespace App\Services\Support;

use App\Models\Shipment;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Support tickets and contact messages (FR-107).
 */
class TicketService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param  array{subject: string, message: string, shipment_id?: ?string}  $data
     */
    public function open(User $customer, array $data): Ticket
    {
        $shipmentId = isset($data['shipment_id'])
            ? Shipment::query()->whereBelongsTo($customer)->where('public_id', $data['shipment_id'])->value('id')
            : null;

        $ticket = DB::transaction(function () use ($customer, $data, $shipmentId): Ticket {
            $ticket = new Ticket(['subject' => $data['subject']]);
            $ticket->forceFill([
                'user_id' => $customer->id,
                'shipment_id' => $shipmentId,
                'source' => 'account',
                'name' => $customer->name,
                'email' => $customer->email,
                'status' => 'open',
                'last_reply_at' => now(),
            ])->save();
            $this->addMessage($ticket, $customer, $data['message'], false);

            return $ticket;
        });

        $this->notifications->notifyStaff('admin.new_ticket', Permissions::TICKETS_MANAGE, ['subject' => $ticket->subject, 'from' => $customer->email]);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fromContactForm(array $data, ?User $user, string $ip): Ticket
    {
        $ticket = DB::transaction(function () use ($data, $user, $ip): Ticket {
            $ticket = new Ticket(['subject' => $data['subject']]);
            $ticket->forceFill([
                'user_id' => $user?->id,
                'source' => 'contact',
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => 'open',
                'ip' => $ip,
                'last_reply_at' => now(),
            ])->save();

            $body = $data['message'];
            if (! empty($data['tracking_number'])) {
                $body = 'Tracking number: '.$data['tracking_number']."\n\n".$body;
            }
            $this->addMessage($ticket, $user, $body, false);

            return $ticket;
        });

        $this->notifications->notifyStaff('admin.new_ticket', Permissions::TICKETS_MANAGE, ['subject' => $ticket->subject, 'from' => $ticket->email]);

        return $ticket;
    }

    public function customerReply(Ticket $ticket, User $customer, string $message): void
    {
        DB::transaction(function () use ($ticket, $customer, $message): void {
            $this->addMessage($ticket, $customer, $message, false);
            $ticket->forceFill(['status' => 'open', 'last_reply_at' => now()])->save();
        });
    }

    public function staffReply(Ticket $ticket, User $staff, string $message, bool $close = false): void
    {
        DB::transaction(function () use ($ticket, $staff, $message, $close): void {
            $this->addMessage($ticket, $staff, $message, true);
            $ticket->forceFill(['status' => $close ? 'closed' : 'answered', 'last_reply_at' => now()])->save();
        });

        $recipient = $ticket->user ?? $ticket->email;
        $locale = $ticket->user?->preferredLocale() ?? 'en';
        $this->notifications->send('ticket.reply', $recipient, [
            'subject' => $ticket->subject,
            'message' => mb_substr($message, 0, 2000),
            'ticket_url' => $ticket->user ? route($locale.'.account.support.show', $ticket) : route($locale.'.contact'),
        ], $locale);
    }

    private function addMessage(Ticket $ticket, ?User $author, string $body, bool $isStaff): TicketMessage
    {
        $message = new TicketMessage(['body' => trim($body)]);
        $message->forceFill(['ticket_id' => $ticket->id, 'user_id' => $author?->id, 'is_staff' => $isStaff])->save();

        return $message;
    }
}
