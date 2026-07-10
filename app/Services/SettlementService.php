<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\PlatformFee;
use App\Models\SettlementBatch;
use App\Models\SettlementBatchItem;
use App\Models\SettlementCycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SettlementService
{
    const COMMISSION_RELEASE_DAYS = 30;

    /**
     * 结算仪表盘数据
     */
    public function getDashboard(int $tenantId): array
    {
        // 检查表和列是否存在
        $hasNewSettlementCols = Schema::hasTable('commission_settlements') 
            && Schema::hasColumn('commission_settlements', 'settlement_batch_id');
        $hasBatches = Schema::hasTable('settlement_batches');
        $hasFees = Schema::hasTable('platform_fees');

        $pendingSettlements = 0;
        $releasableCount = 0;
        $monthlySettled = 0;
        $settlementTrend = collect();
        $pendingByAgent = collect();

        if ($hasNewSettlementCols) {
            $pendingSettlements = CommissionSettlement::whereNull('settlement_batch_id')
                ->where('status', 'pending_settlement')
                ->count();

            $releasableCount = CommissionSettlement::where('status', 'pending_settlement')
                ->where('released_at', '<=', now())
                ->count();

            $monthlySettled = CommissionSettlement::where('status', 'settled')
                ->whereMonth('settled_at', now()->month)
                ->whereYear('settled_at', now()->year)
                ->sum('commission_amount');

            try {
                $settlementTrend = CommissionSettlement::selectRaw(
                    db_date_format('settled_at', '%Y-%m').' as month, SUM(commission_amount) as total'
                )->where('status', 'settled')
                    ->where('settled_at', '>=', now()->subMonths(12))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('total', 'month');
            } catch (\Exception $e) {
                Log::warning('Settlement trend query failed: ' . $e->getMessage());
            }

            try {
                $pendingByAgent = CommissionSettlement::whereNull('settlement_batch_id')
                    ->where('status', 'pending_settlement')
                    ->selectRaw('agent_id, COUNT(*) as count, SUM(commission_amount) as total')
                    ->groupBy('agent_id')
                    ->with('agent.user:id,name')
                    ->get();
            } catch (\Exception $e) {
                Log::warning('Pending by agent query failed: ' . $e->getMessage());
            }
        }

        $pendingPayouts = 0;
        if ($hasBatches) {
            $pendingPayouts = SettlementBatch::byTenant($tenantId)
                ->whereIn('status', ['draft', 'pending_approval', 'approved', 'processing'])
                ->sum('total_amount');
        }

        $activeAgents = Agent::where('status', 'active')->count();

        $recentCycles = collect();
        if (Schema::hasTable('settlement_cycles')) {
            $recentCycles = SettlementCycle::byTenant($tenantId)
                ->orderBy('period_end', 'desc')
                ->take(6)
                ->get();
        }

        $monthlyFees = 0;
        if ($hasFees) {
            $monthlyFees = PlatformFee::byTenant($tenantId)
                ->where('status', 'collected')
                ->whereMonth('collected_at', now()->month)
                ->whereYear('collected_at', now()->year)
                ->sum('amount');
        }

        return [
            'pending_settlements' => $pendingSettlements,
            'releasable_count' => $releasableCount,
            'pending_payouts' => (float) $pendingPayouts,
            'active_agents' => $activeAgents,
            'monthly_settled' => (float) $monthlySettled,
            'monthly_fees' => (float) $monthlyFees,
            'recent_cycles' => $recentCycles,
            'settlement_trend' => $settlementTrend,
            'pending_by_agent' => $pendingByAgent,
        ];
    }

    /**
     * 创建结算周期
     */
    public function createCycle(array $data): SettlementCycle
    {
        return SettlementCycle::create($data);
    }

    /**
     * 结算周期列表
     */
    public function getCycles(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = SettlementCycle::byTenant($tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('period_end', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('period_start', '<=', $filters['date_to']);
        }

        return $query->orderBy('period_end', 'desc')->paginate($perPage);
    }

    /**
     * 自动扫描可结算的佣金（已过冻结期）
     */
    public function scanReleasableCommissions(int $tenantId): array
    {
        $hasNewCols = Schema::hasTable('commission_settlements') 
            && Schema::hasColumn('commission_settlements', 'settlement_batch_id');
        
        if (!$hasNewCols) {
            return ['total_count' => 0, 'total_amount' => 0, 'items' => []];
        }

        $releasable = CommissionSettlement::where('status', 'pending_settlement')
            ->whereNull('settlement_batch_id')
            ->where('released_at', '<=', now())
            ->with('agent.user')
            ->get();

        return [
            'total_count' => $releasable->count(),
            'total_amount' => (float) $releasable->sum('commission_amount'),
            'items' => $releasable,
        ];
    }

    /**
     * 创建结算批次
     */
    public function createBatch(array $data): SettlementBatch
    {
        return DB::transaction(function () use ($data) {
            $batch = SettlementBatch::create([
                'settlement_cycle_id' => $data['settlement_cycle_id'] ?? null,
                'tenant_id' => $data['tenant_id'],
                'batch_no' => $this->generateBatchNo(),
                'channel' => $data['channel'] ?? 'balance',
                'total_amount' => 0,
                'total_fee' => 0,
                'net_amount' => 0,
                'item_count' => 0,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'],
            ]);

            // 添加结算项
            $totalAmount = 0;
            $totalFee = 0;
            $itemCount = 0;

            if (!empty($data['settlement_ids'])) {
                $settlements = CommissionSettlement::whereIn('id', $data['settlement_ids'])
                    ->whereNull('settlement_batch_id')
                    ->get();

                foreach ($settlements as $settlement) {
                    $fee = $settlement->fee ?? 0;
                    $netAmount = ($settlement->commission_amount ?? 0) - $fee;

                    SettlementBatchItem::create([
                        'settlement_batch_id' => $batch->id,
                        'settleable_type' => CommissionSettlement::class,
                        'settleable_id' => $settlement->id,
                        'amount' => $settlement->commission_amount,
                        'fee' => $fee,
                        'net_amount' => $netAmount,
                        'status' => 'included',
                    ]);

                    $totalAmount += $settlement->commission_amount;
                    $totalFee += $fee;
                    $itemCount++;

                    // 标记结算记录
                    $settlement->update([
                        'settlement_batch_id' => $batch->id,
                        'settlement_cycle_id' => $data['settlement_cycle_id'] ?? null,
                    ]);
                }
            }

            $batch->update([
                'total_amount' => $totalAmount,
                'total_fee' => $totalFee,
                'net_amount' => $totalAmount - $totalFee,
                'item_count' => $itemCount,
            ]);

            return $batch->fresh(['items', 'settlementCycle']);
        });
    }

    /**
     * 批次列表
     */
    public function getBatches(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = SettlementBatch::byTenant($tenantId)->with('settlementCycle');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (!empty($filters['search'])) {
            $query->where('batch_no', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 批次详情
     */
    public function getBatchDetail(int $id): SettlementBatch
    {
        return SettlementBatch::with([
            'items.settleable',
            'settlementCycle',
        ])->findOrFail($id);
    }

    /**
     * 提交审核
     */
    public function submitForApproval(int $batchId): SettlementBatch
    {
        $batch = SettlementBatch::findOrFail($batchId);
        $batch->update(['status' => 'pending_approval']);
        return $batch->fresh();
    }

    /**
     * 审核通过
     */
    public function approveBatch(int $batchId, int $userId): SettlementBatch
    {
        $batch = SettlementBatch::findOrFail($batchId);
        $batch->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        // 更新结算周期
        if ($batch->settlement_cycle_id) {
            SettlementCycle::where('id', $batch->settlement_cycle_id)
                ->update(['status' => 'processing']);
        }

        return $batch->fresh();
    }

    /**
     * 标记批次为已完成
     */
    public function completeBatch(int $batchId): SettlementBatch
    {
        return DB::transaction(function () use ($batchId) {
            $batch = SettlementBatch::findOrFail($batchId);
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // 更新相关结算记录
            $items = SettlementBatchItem::where('settlement_batch_id', $batchId)->get();
            foreach ($items as $item) {
                if ($item->settleable_type === CommissionSettlement::class) {
                    $settlement = CommissionSettlement::find($item->settleable_id);
                    if ($settlement) {
                        $settlement->update(['status' => 'settled', 'settled_at' => now()]);

                        // 更新收益账户余额
                        if ($settlement->agent) {
                            EarningsAccount::where('user_id', $settlement->agent->user_id)
                                ->update([
                                    'available_balance' => DB::raw('available_balance + ' . $item->net_amount),
                                    'pending_balance' => DB::raw('GREATEST(pending_balance - ' . $item->amount . ', 0)'),
                                    'last_settlement_at' => now(),
                                    'lifetime_settled' => DB::raw('lifetime_settled + ' . $item->net_amount),
                                ]);
                        }
                    }
                }
            }

            // 更新结算周期
            if ($batch->settlement_cycle_id) {
                $cycle = SettlementCycle::find($batch->settlement_cycle_id);
                if ($cycle) {
                    $paidBatches = $cycle->batches()->where('status', 'completed')->count();
                    $totalBatches = $cycle->batches()->count();
                    if ($paidBatches >= $totalBatches) {
                        $cycle->update(['status' => 'paid', 'payout_date' => now()]);
                    }
                }
            }

            return $batch->fresh();
        });
    }

    /**
     * 取消批次
     */
    public function cancelBatch(int $batchId): SettlementBatch
    {
        return DB::transaction(function () use ($batchId) {
            $batch = SettlementBatch::findOrFail($batchId);

            // 释放结算项
            SettlementBatchItem::where('settlement_batch_id', $batchId)->chunk(100, function ($items) {
                foreach ($items as $item) {
                    if ($item->settleable_type === CommissionSettlement::class) {
                        CommissionSettlement::where('id', $item->settleable_id)
                            ->update(['settlement_batch_id' => null, 'settlement_cycle_id' => null]);
                    }
                }
            });

            $batch->update(['status' => 'cancelled']);

            if ($batch->settlement_cycle_id) {
                SettlementCycle::where('id', $batch->settlement_cycle_id)
                    ->update(['status' => 'pending']);
            }

            return $batch->fresh();
        });
    }

    /**
     * 平台费用统计
     */
    public function getFeeStats(int $tenantId, ?string $yearMonth = null): array
    {
        $query = PlatformFee::byTenant($tenantId)->where('status', 'collected');

        if ($yearMonth) {
            $monthExpr = db_date_format('collected_at', '%Y-%m');
            $query->whereRaw("{$monthExpr} = ?", [$yearMonth]);
        }

        $byType = $query->selectRaw('fee_type, SUM(amount) as total')
            ->groupBy('fee_type')
            ->pluck('total', 'fee_type');

        $totalFees = $query->sum('amount');

        return [
            'total_fees' => (float) $totalFees,
            'by_type' => $byType->map(fn($v) => (float) $v)->toArray(),
        ];
    }

    /**
     * 生成批次号
     */
    protected function generateBatchNo(): string
    {
        $prefix = 'STL' . now()->format('Ymd');
        $last = SettlementBatch::where('batch_no', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('batch_no');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 创建/生成默认结算周期
     */
    public function generateMonthlyCycle(int $tenantId, ?int $userId = null): SettlementCycle
    {
        $now = now();
        $periodStart = $now->copy()->startOfMonth()->subMonth();
        $periodEnd = $now->copy()->startOfMonth()->subDay();

        // 检查是否已存在
        $existing = SettlementCycle::byTenant($tenantId)
            ->where('period_start', $periodStart->format('Y-m-d'))
            ->where('period_end', $periodEnd->format('Y-m-d'))
            ->first();

        if ($existing) {
            return $existing;
        }

        return SettlementCycle::create([
            'tenant_id' => $tenantId,
            'name' => $periodStart->format('Y年n月') . '结算周期',
            'period_type' => 'monthly',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'settlement_date' => $now->copy()->addDays(self::COMMISSION_RELEASE_DAYS),
            'status' => 'pending',
            'created_by' => $userId,
        ]);
    }
}
