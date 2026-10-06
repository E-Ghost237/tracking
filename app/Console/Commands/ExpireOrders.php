<?php

namespace App\Console\Commands;

use App\Services\Payments\OrderExpiryService;
use Illuminate\Console\Command;

class ExpireOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Send payment reminders and expire unpaid orders (section 5.10).';

    public function handle(OrderExpiryService $expiry): int
    {
        $result = $expiry->run();
        $this->info("Reminders sent: {$result['reminded']}, orders expired: {$result['expired']}");

        return self::SUCCESS;
    }
}
