<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class CancelExpiredOrders extends Command
{
    protected $signature = 'orders:cancel-expired {--batch=50 : 每批处理数量}';
    protected $description = '自动取消超时未支付的订单（M2-146 🛒）';

    public function handle(OrderService $orderService): int
    {
        $batch = (int) $this->option('batch');
        $count = $orderService->cancelExpiredOrders($batch);

        $this->info("已取消 {$count} 个超时订单");
        $this->getOutput()->writeln(json_encode([
            'cancelled' => $count,
            'batch' => $batch,
        ]));

        return Command::SUCCESS;
    }
}
