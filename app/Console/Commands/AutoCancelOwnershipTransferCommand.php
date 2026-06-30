<?php

namespace App\Console\Commands;

use App\Models\OwnershipTransferRequest;
use Illuminate\Console\Command;

class AutoCancelOwnershipTransferCommand extends Command
{
    protected $signature = 'ownership-transfer:auto-cancel {--hours=48 : 超时小时数}';
    protected $description = '自动取消超时未确认的所有权转移请求（M3-65 🏷️）';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $expired = OwnershipTransferRequest::whereIn('status', ['pending_source', 'pending_target'])
            ->where('created_at', '<', $cutoff)
            ->get();

        $cancelled = 0;
        foreach ($expired as $request) {
            $request->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'audit_log' => array_merge($request->audit_log ?? [], [
                    ['action' => 'auto_cancelled_expired', 'by' => 'system', 'at' => now()->toIso8601String()],
                ]),
            ]);
            $cancelled++;
        }

        $this->info("已自动取消 {$cancelled} 个超时所有权转移请求（超过 {$hours} 小时）");
        $this->getOutput()->writeln(json_encode([
            'cancelled' => $cancelled,
            'cutoff_hours' => $hours,
        ]));

        return Command::SUCCESS;
    }
}
