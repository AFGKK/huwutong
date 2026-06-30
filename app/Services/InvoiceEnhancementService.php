<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\InvoiceReconciliation;
use App\Models\InvoiceSplit;
use App\Models\InvoiceTemplate;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 发票增强服务 (M3-74)
 *
 * 管理发票模板、账单对账、账单拆分。
 * 复用现有的 BillingService + MeteredBillingService 进行发票创建。
 */
class InvoiceEnhancementService
{
    // ═══════ 发票模板 ═══════

    public function listTemplates(int $tenantId, array $filters = [])
    {
        $query = InvoiceTemplate::where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('id');

        if (!empty($filters['is_active'])) $query->where('is_active', $filters['is_active'] === 'true');

        return $query->get();
    }

    public function createTemplate(array $data): InvoiceTemplate
    {
        if (!empty($data['is_default'])) {
            InvoiceTemplate::where('tenant_id', $data['tenant_id'])->update(['is_default' => false]);
        }
        return InvoiceTemplate::create($data);
    }

    public function updateTemplate(InvoiceTemplate $template, array $data): InvoiceTemplate
    {
        if (!empty($data['is_default'])) {
            InvoiceTemplate::where('tenant_id', $template->tenant_id)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }
        $template->update($data);
        return $template->fresh();
    }

    public function deleteTemplate(InvoiceTemplate $template): void
    {
        $template->delete();
    }

    public function getDefaultTemplate(int $tenantId): ?InvoiceTemplate
    {
        return InvoiceTemplate::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    // ═══════ 账单对账 ═══════

    public function listReconciliations(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = InvoiceReconciliation::with(['invoice:id,invoice_no,amount,status', 'customer:id,name'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['reconciliation_type'])) $query->where('reconciliation_type', $filters['reconciliation_type']);
        if (!empty($filters['customer_id'])) $query->where('customer_id', $filters['customer_id']);

        return $query->paginate($perPage);
    }

    public function createReconciliation(array $data): InvoiceReconciliation
    {
        $invoiceAmount = $data['invoice_amount'] ?? 0;
        $actualAmount = $data['actual_amount'] ?? 0;
        $difference = round($actualAmount - $invoiceAmount, 2);

        $status = $difference === 0.0 ? 'matched' : 'unmatched';

        return InvoiceReconciliation::create(array_merge($data, [
            'difference' => $difference,
            'status' => $status,
            'matched_at' => $status === 'matched' ? now() : null,
        ]));
    }

    public function resolveReconciliation(int $id, string $resolution, string $notes = null): InvoiceReconciliation
    {
        $rec = InvoiceReconciliation::findOrFail($id);
        $rec->update([
            'status' => 'resolved',
            'notes' => $notes ? "{$resolution}: {$notes}" : $resolution,
            'resolved_at' => now(),
        ]);
        return $rec->fresh(['invoice:id,invoice_no,amount', 'customer:id,name']);
    }

    public function getReconciliationStats(int $tenantId): array
    {
        $query = fn($q) => $q->where('tenant_id', $tenantId);

        $totalCount = InvoiceReconciliation::where('tenant_id', $tenantId)->count();
        $pendingCount = InvoiceReconciliation::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $unmatchedCount = InvoiceReconciliation::where('tenant_id', $tenantId)->where('status', 'unmatched')->count();
        $totalDifference = round(
            InvoiceReconciliation::where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'unmatched'])
                ->sum(DB::raw('ABS(difference)')),
            2
        );

        $byStatus = InvoiceReconciliation::where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as cnt, SUM(ABS(difference)) as total_diff')
            ->groupBy('status')
            ->get();

        return [
            'total_count' => $totalCount,
            'pending_count' => $pendingCount,
            'unmatched_count' => $unmatchedCount,
            'total_difference' => $totalDifference,
            'by_status' => $byStatus,
        ];
    }

    // ═══════ 账单拆分 ═══════

    public function listSplits(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = InvoiceSplit::with([
            'originalInvoice:id,invoice_no,amount,status,customer_id,created_at',
            'splitInvoice:id,invoice_no,amount,status,created_at',
        ])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($perPage);
    }

    /**
     * 拆分账单 - 将原始发票的部分金额拆分为新发票
     */
    public function splitInvoice(int $tenantId, int $originalInvoiceId, float $splitAmount, string $reason = null): array
    {
        $original = Invoice::where('tenant_id', $tenantId)->findOrFail($originalInvoiceId);

        if ($original->status !== 'pending' && $original->status !== 'paid') {
            throw new \RuntimeException('只能拆分待支付或已支付的发票');
        }

        $remaining = $original->amount - $splitAmount;
        if ($splitAmount <= 0 || $remaining < 0) {
            throw new \RuntimeException('拆分金额无效');
        }

        $result = DB::transaction(function () use ($tenantId, $original, $splitAmount, $remaining, $reason) {
            // 创建拆分后的新发票
            $splitInvoice = Invoice::create([
                'tenant_id' => $tenantId,
                'customer_id' => $original->customer_id,
                'subscription_id' => $original->subscription_id,
                'invoice_no' => $this->generateSplitInvoiceNo($original),
                'amount' => $splitAmount,
                'subtotal' => $splitAmount,
                'currency' => $original->currency,
                'status' => $original->status,
                'paid' => $original->paid,
                'billing_reason' => 'split_' . $original->billing_reason,
                'due_at' => $original->due_at,
                'notes' => "由发票 #{$original->invoice_no} 拆分",
                'metadata' => ['split_from' => $original->id, 'split_reason' => $reason],
            ]);

            // 复制行项目（按比例分配金额）
            $lineItems = InvoiceLineItem::where('invoice_id', $original->id)->get();
            if ($lineItems->isNotEmpty()) {
                $ratio = $splitAmount / $original->amount;
                foreach ($lineItems as $item) {
                    InvoiceLineItem::create([
                        'invoice_id' => $splitInvoice->id,
                        'tenant_id' => $tenantId,
                        'type' => $item->type,
                        'description' => $item->description . ' (拆分)',
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'amount' => round($item->amount * $ratio, 2),
                        'currency' => $item->currency,
                        'period_start' => $item->period_start,
                        'period_end' => $item->period_end,
                        'sort_order' => $item->sort_order,
                    ]);
                }
            }

            // 更新原发票金额
            $original->update(['amount' => $remaining, 'subtotal' => $remaining]);

            // 记录拆分
            $split = InvoiceSplit::create([
                'tenant_id' => $tenantId,
                'original_invoice_id' => $original->id,
                'split_invoice_id' => $splitInvoice->id,
                'amount' => $splitAmount,
                'reason' => $reason,
                'status' => 'completed',
            ]);

            Log::info('InvoiceSplit: completed', [
                'original' => $original->invoice_no,
                'split' => $splitInvoice->invoice_no,
                'amount' => $splitAmount,
                'remaining' => $remaining,
            ]);

            return [
                'split' => $split->fresh(['originalInvoice', 'splitInvoice']),
                'original' => $original->fresh(),
                'split_invoice' => $splitInvoice->fresh(),
            ];
        });

        return $result;
    }

    /**
     * 生成拆分发票编号
     */
    protected function generateSplitInvoiceNo(Invoice $original): string
    {
        $base = $original->invoice_no;
        $count = InvoiceSplit::where('original_invoice_id', $original->id)->count() + 1;
        return "{$base}-S{$count}";
    }

    // ═══════ 发票统计增强 ═══════

    public function getEnhancedStats(int $tenantId): array
    {
        // 待对账统计
        $pendingRecon = InvoiceReconciliation::where('tenant_id', $tenantId)
            ->where('status', 'unmatched')
            ->count();

        // 本月发票统计
        $monthStart = now()->startOfMonth();
        $monthInvoices = Invoice::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $monthStart);

        $monthTotal = $monthInvoices->sum('amount');
        $monthCount = $monthInvoices->count();
        $monthPaid = (clone $monthInvoices)->where('paid', true)->sum('amount');

        // 模板数
        $templateCount = InvoiceTemplate::where('tenant_id', $tenantId)
            ->where('is_active', true)->count();

        return [
            'pending_reconciliations' => $pendingRecon,
            'monthly_invoice_count' => $monthCount,
            'monthly_invoice_total' => round($monthTotal, 2),
            'monthly_paid_total' => round($monthPaid, 2),
            'template_count' => $templateCount,
        ];
    }

    /**
     * 批量对账 - 自动匹配已支付的发票
     */
    public function autoReconcile(int $tenantId): array
    {
        $processed = 0;
        $errors = 0;

        // 查找已支付但未对账的发票
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('paid', true)
            ->whereDoesntHave('reconciliations')
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $this->createReconciliation([
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'reconciliation_type' => 'auto',
                    'invoice_amount' => $invoice->amount,
                    'actual_amount' => $invoice->amount,
                    'payment_ref' => $invoice->gateway_charge_id ?? null,
                    'payment_date' => $invoice->paid_at,
                ]);
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                Log::warning('AutoReconcile failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['processed' => $processed, 'errors' => $errors];
    }
}
