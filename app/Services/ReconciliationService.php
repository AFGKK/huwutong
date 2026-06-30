<?php

namespace App\Services;

use App\Models\InvoiceReconciliation;
use App\Models\Order;
use App\Models\ReconciliationCalendar;
use App\Models\ReconciliationChannelRow;
use App\Models\ReconciliationImport;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * M2-158 🛒 电商对账系统
 *
 * 支付渠道账单CSV导入 → 自动匹配订单 → 差异告警 → 报告导出 → 周期日历
 * 支持: 微信支付/支付宝/Stripe/PayPal
 */
class ReconciliationService
{
    // ═══════════════════════════════════════
    //  仪表盘 & 统计
    // ═══════════════════════════════════════

    public function dashboard(): array
    {
        $totalRecon = InvoiceReconciliation::count();
        $pending = InvoiceReconciliation::where('status', 'pending')->count();
        $matched = InvoiceReconciliation::where('status', 'matched')->count();
        $unmatched = InvoiceReconciliation::where('status', 'unmatched')->count();
        $resolved = InvoiceReconciliation::where('status', 'resolved')->count();

        $totalDiff = round(
            InvoiceReconciliation::whereIn('status', ['pending', 'unmatched'])
                ->sum(DB::raw('ABS(difference)')),
            2
        );

        $imports = ReconciliationImport::count();
        $todayImports = ReconciliationImport::whereDate('created_at', today())->count();

        $channelRows = ReconciliationChannelRow::count();
        $unmatchedRows = ReconciliationChannelRow::where('match_status', 'unmatched')->count();

        // 按渠道统计
        $byChannel = ReconciliationChannelRow::selectRaw('channel, COUNT(*) as total, SUM(CASE WHEN match_status="matched" THEN 1 ELSE 0 END) as matched, SUM(CASE WHEN match_status="unmatched" THEN 1 ELSE 0 END) as unmatched')
            ->groupBy('channel')->get();

        // 最近导入
        $recentImports = ReconciliationImport::orderByDesc('created_at')->limit(5)->get();

        return compact(
            'totalRecon', 'pending', 'matched', 'unmatched', 'resolved',
            'totalDiff', 'imports', 'todayImports', 'channelRows', 'unmatchedRows',
            'byChannel', 'recentImports',
        );
    }

    // ═══════════════════════════════════════
    //  CSV 导入
    // ═══════════════════════════════════════

    /**
     * 解析并导入支付渠道 CSV 账单
     */
    public function importCsv(UploadedFile $file, string $channel): ReconciliationImport
    {
        $channel = strtolower($channel);
        if (!in_array($channel, ReconciliationImport::CHANNELS, true)) {
            throw new \InvalidArgumentException("不支持的支付渠道: {$channel}");
        }

        $filename = $file->getClientOriginalName();
        $path = $file->storeAs('reconciliation-imports', date('Ymd_His') . '_' . $filename);

        $import = ReconciliationImport::create([
            'channel' => $channel,
            'filename' => $filename,
            'status' => 'processing',
            'imported_by' => auth()->id(),
        ]);

        try {
            $rows = $this->parseCsv($file, $channel);
            $import->update(['total_rows' => count($rows)]);

            $matched = 0;
            $unmatched = 0;
            $errors = 0;

            DB::transaction(function () use ($rows, $import, $channel, &$matched, &$unmatched, &$errors) {
                foreach ($rows as $index => $row) {
                    try {
                        $channelRow = ReconciliationChannelRow::create([
                            'import_id' => $import->id,
                            'channel' => $channel,
                            'transaction_id' => $row['transaction_id'] ?? null,
                            'order_id' => $row['order_id'] ?? null,
                            'amount' => $row['amount'] ?? 0,
                            'fee' => $row['fee'] ?? 0,
                            'currency' => $row['currency'] ?? 'CNY',
                            'status' => $row['status'] ?? null,
                            'transaction_time' => isset($row['transaction_time']) ? Carbon::parse($row['transaction_time']) : null,
                            'payer_account' => $row['payer_account'] ?? null,
                            'payee_account' => $row['payee_account'] ?? null,
                            'raw_data' => $row,
                            'match_status' => 'pending',
                        ]);

                        // 自动匹配订单
                        $matchResult = $this->matchOrder($channelRow);
                        if ($matchResult) {
                            $channelRow->update($matchResult);
                            $matched++;
                        } else {
                            $channelRow->update(['match_status' => 'unmatched']);
                            $unmatched++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::warning("CSV import row #{$index} failed", ['error' => $e->getMessage()]);
                    }
                }
            });

            $import->update([
                'status' => 'completed',
                'matched_rows' => $matched,
                'unmatched_rows' => $unmatched,
                'error_rows' => $errors,
                'summary' => [
                    'matched_amount' => ReconciliationChannelRow::where('import_id', $import->id)
                        ->where('match_status', 'matched')->sum('amount'),
                    'unmatched_amount' => ReconciliationChannelRow::where('import_id', $import->id)
                        ->where('match_status', 'unmatched')->sum('amount'),
                ],
            ]);

            Log::info("Reconciliation: CSV import completed", [
                'import_id' => $import->id, 'channel' => $channel,
                'total' => count($rows), 'matched' => $matched, 'unmatched' => $unmatched, 'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error("Reconciliation: CSV import failed", ['import_id' => $import->id, 'error' => $e->getMessage()]);
        }

        return $import->fresh();
    }

    /**
     * 解析 CSV 文件为数组
     */
    protected function parseCsv(UploadedFile $file, string $channel): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \RuntimeException('无法读取CSV文件');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \RuntimeException('CSV文件为空');
        }

        // 标准化表头
        $headers = array_map(fn($h) => trim($h), $headers);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($headers)) {
                continue; // 跳过格式不正确的行
            }
            $row = array_combine($headers, $line);
            $row = array_map(fn($v) => trim($v ?? ''), $row);

            // 按渠道标准化字段
            $rows[] = $this->normalizeRow($row, $channel);
        }

        fclose($handle);
        return $rows;
    }

    /**
     * 按渠道标准化 CSV 行
     */
    protected function normalizeRow(array $row, string $channel): array
    {
        return match ($channel) {
            'wechat' => [
                'transaction_id' => $row['微信交易号'] ?? $row['transaction_id'] ?? null,
                'order_id' => $row['商户订单号'] ?? $row['out_trade_no'] ?? null,
                'amount' => (float) ($row['交易金额'] ?? $row['total_fee'] ?? $row['amount'] ?? 0),
                'fee' => (float) ($row['手续费'] ?? $row['fee'] ?? 0),
                'currency' => 'CNY',
                'status' => $row['交易状态'] ?? $row['status'] ?? 'SUCCESS',
                'transaction_time' => $row['交易时间'] ?? $row['time_end'] ?? null,
                'payer_account' => $row['付款方'] ?? $row['openid'] ?? null,
                'payee_account' => $row['收款方'] ?? null,
            ],
            'alipay' => [
                'transaction_id' => $row['支付宝交易号'] ?? $row['trade_no'] ?? null,
                'order_id' => $row['商户订单号'] ?? $row['out_trade_no'] ?? null,
                'amount' => (float) ($row['交易金额'] ?? $row['total_amount'] ?? $row['amount'] ?? 0),
                'fee' => (float) ($row['手续费'] ?? $row['fee'] ?? 0),
                'currency' => 'CNY',
                'status' => $row['交易状态'] ?? $row['trade_status'] ?? 'TRADE_SUCCESS',
                'transaction_time' => $row['交易时间'] ?? $row['gmt_create'] ?? null,
                'payer_account' => $row['付款方'] ?? $row['buyer_logon_id'] ?? null,
                'payee_account' => $row['收款方'] ?? $row['seller_email'] ?? null,
            ],
            'stripe' => [
                'transaction_id' => $row['id'] ?? $row['charge_id'] ?? $row['transaction_id'] ?? null,
                'order_id' => $row['metadata_order_id'] ?? $row['order_id'] ?? $row['description'] ?? null,
                'amount' => (float) (($row['amount'] ?? 0) / 100), // Stripe 单位为分
                'fee' => (float) (($row['fee'] ?? $row['application_fee'] ?? 0) / 100),
                'currency' => strtoupper($row['currency'] ?? 'USD'),
                'status' => $row['status'] ?? 'succeeded',
                'transaction_time' => $row['created'] ?? $row['transaction_time'] ?? null,
                'payer_account' => $row['customer'] ?? $row['source_id'] ?? null,
                'payee_account' => $row['destination'] ?? null,
            ],
            'paypal' => [
                'transaction_id' => $row['Transaction ID'] ?? $row['transaction_id'] ?? null,
                'order_id' => $row['Invoice Number'] ?? $row['custom'] ?? $row['order_id'] ?? null,
                'amount' => (float) ($row['Gross Amount'] ?? $row['amount'] ?? 0),
                'fee' => (float) ($row['Fee Amount'] ?? $row['fee'] ?? 0),
                'currency' => $row['Currency'] ?? $row['currency'] ?? 'USD',
                'status' => $row['Status'] ?? $row['status'] ?? 'Completed',
                'transaction_time' => $row['Date'] ?? $row['transaction_time'] ?? null,
                'payer_account' => $row['From Email Address'] ?? $row['payer_email'] ?? null,
                'payee_account' => $row['To Email Address'] ?? $row['receiver_email'] ?? null,
            ],
            default => throw new \InvalidArgumentException("不支持的渠道: {$channel}"),
        };
    }

    /**
     * 自动匹配订单
     */
    protected function matchOrder(ReconciliationChannelRow $channelRow): ?array
    {
        // 1. 按交易号匹配
        if ($channelRow->transaction_id) {
            $order = Order::where('transaction_id', $channelRow->transaction_id)->first();
            if ($order) {
                return $this->buildMatchResult($order, $channelRow);
            }
        }

        // 2. 按商户订单号匹配
        if ($channelRow->order_id) {
            $order = Order::where('order_no', $channelRow->order_id)
                ->orWhere('id', $channelRow->order_id)
                ->first();
            if ($order) {
                return $this->buildMatchResult($order, $channelRow);
            }
        }

        // 3. 按金额+时间模糊匹配（24小时内同金额）
        $candidates = Order::whereBetween('created_at', [
            $channelRow->transaction_time?->copy()->subHours(24) ?? now()->subHours(24),
            $channelRow->transaction_time?->copy()->addHours(24) ?? now()->addHours(24),
        ])->where('total_amount', $channelRow->amount)->get();

        if ($candidates->count() === 1) {
            return $this->buildMatchResult($candidates->first(), $channelRow);
        }

        return null; // 未匹配
    }

    /**
     * 构建匹配结果
     */
    protected function buildMatchResult(Order $order, ReconciliationChannelRow $channelRow): array
    {
        $difference = round($channelRow->amount - (float) $order->total_amount, 2);

        return [
            'match_status' => abs($difference) < 0.01 ? 'matched' : 'unmatched',
            'matched_order_id' => $order->id,
            'matched_order_no' => $order->order_no,
            'difference' => $difference,
        ];
    }

    // ═══════════════════════════════════════
    //  对账记录 CRUD
    // ═══════════════════════════════════════

    public function listReconciliations(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = InvoiceReconciliation::with(['invoice:id,invoice_no,amount,status', 'customer:id,name'])
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['reconciliation_type'])) $query->where('reconciliation_type', $filters['reconciliation_type']);
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('payment_ref', 'like', "%{$s}%")
                  ->orWhere('notes', 'like', "%{$s}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function resolveReconciliation(int $id, string $resolution, ?string $notes = null): InvoiceReconciliation
    {
        $rec = InvoiceReconciliation::findOrFail($id);
        $rec->update([
            'status' => 'resolved',
            'notes' => $notes ? "{$resolution}: {$notes}" : $resolution,
            'resolved_at' => now(),
        ]);
        return $rec->fresh(['invoice:id,invoice_no,amount', 'customer:id,name']);
    }

    // ═══════════════════════════════════════
    //  渠道行管理
    // ═══════════════════════════════════════

    public function listChannelRows(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ReconciliationChannelRow::with('import')
            ->orderByDesc('created_at');

        if (!empty($filters['import_id'])) $query->where('import_id', $filters['import_id']);
        if (!empty($filters['channel'])) $query->where('channel', $filters['channel']);
        if (!empty($filters['match_status'])) $query->where('match_status', $filters['match_status']);
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('transaction_id', 'like', "%{$s}%")
                  ->orWhere('order_id', 'like', "%{$s}%")
                  ->orWhere('matched_order_no', 'like', "%{$s}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function listImports(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ReconciliationImport::orderByDesc('created_at');
        if (!empty($filters['channel'])) $query->where('channel', $filters['channel']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        return $query->paginate($perPage);
    }

    /**
     * 手动匹配渠道行到订单
     */
    public function manualMatch(int $channelRowId, int $orderId): ReconciliationChannelRow
    {
        $row = ReconciliationChannelRow::findOrFail($channelRowId);
        $order = Order::findOrFail($orderId);

        $difference = round($row->amount - (float) $order->total_amount, 2);

        $row->update([
            'match_status' => abs($difference) < 0.01 ? 'matched' : 'unmatched',
            'matched_order_id' => $order->id,
            'matched_order_no' => $order->order_no,
            'difference' => $difference,
            'notes' => '手动匹配',
        ]);

        return $row->fresh();
    }

    // ═══════════════════════════════════════
    //  对账日历
    // ═══════════════════════════════════════

    public function listCalendars(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ReconciliationCalendar::orderByDesc('period_start');
        if (!empty($filters['period_type'])) $query->where('period_type', $filters['period_type']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        return $query->paginate($perPage);
    }

    public function generateCalendarPeriods(string $type = 'daily', int $months = 3): array
    {
        $generated = [];
        $start = now()->startOfMonth()->subMonth();

        for ($i = 0; $i < $months * 30; $i++) {
            $date = $start->copy()->addDays($i);
            $periodStart = match ($type) {
                'weekly' => $date->copy()->startOfWeek(),
                'monthly' => $date->copy()->startOfMonth(),
                'quarterly' => $date->copy()->startOfQuarter(),
                default => $date->copy()->startOfDay(),
            };
            $periodEnd = match ($type) {
                'weekly' => $periodStart->copy()->endOfWeek(),
                'monthly' => $periodStart->copy()->endOfMonth(),
                'quarterly' => $periodStart->copy()->endOfQuarter(),
                default => $periodStart->copy()->endOfDay(),
            };

            if ($periodEnd->gt(now()->addMonth())) break;

            $exists = ReconciliationCalendar::where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->where('period_type', $type)
                ->exists();

            if (!$exists) {
                $cal = ReconciliationCalendar::create([
                    'period_type' => $type,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'status' => 'pending',
                ]);
                $generated[] = $cal;
            }
        }

        return $generated;
    }

    // ═══════════════════════════════════════
    //  报告导出
    // ═══════════════════════════════════════

    /**
     * 生成对账报告数据
     */
    public function generateReport(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : now()->endOfDay();

        $recons = InvoiceReconciliation::whereBetween('created_at', [$start, $end])->get();
        $channelRows = ReconciliationChannelRow::whereBetween('created_at', [$start, $end])->get();

        $byChannel = $channelRows->groupBy('channel')->map(fn($rows) => [
            'total' => $rows->count(),
            'matched' => $rows->where('match_status', 'matched')->count(),
            'unmatched' => $rows->where('match_status', 'unmatched')->count(),
            'total_amount' => round($rows->sum('amount'), 2),
            'total_fee' => round($rows->sum('fee'), 2),
        ]);

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'summary' => [
                'total_reconciliation' => $recons->count(),
                'total_matched' => $recons->where('status', 'matched')->count(),
                'total_unmatched' => $recons->where('status', 'unmatched')->count(),
                'total_pending' => $recons->where('status', 'pending')->count(),
                'total_difference' => round($recons->whereIn('status', ['pending', 'unmatched'])->sum('difference'), 2),
                'total_channel_rows' => $channelRows->count(),
                'total_channel_amount' => round($channelRows->sum('amount'), 2),
            ],
            'by_channel' => $byChannel,
            'unmatched_list' => $channelRows->where('match_status', 'unmatched')->values()->map(fn($r) => [
                'id' => $r->id,
                'channel' => $r->channel,
                'transaction_id' => $r->transaction_id,
                'order_id' => $r->order_id,
                'amount' => $r->amount,
                'transaction_time' => $r->transaction_time?->toIso8601String(),
            ]),
        ];
    }
}
