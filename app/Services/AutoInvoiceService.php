<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\InvoiceTitle;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 自动开票系统 (M2-148 🛒)
 *
 * 订单完成后自动生成电子发票 + 企业抬头管理 + 发票下载 + 发票重发
 */
class AutoInvoiceService
{
    public function __construct(
        protected TaxCalculatorService $taxCalculator,
    ) {}

    /**
     * 从订单自动生成发票
     */
    public function generateFromOrder(Order $order, ?int $invoiceTitleId = null): Invoice
    {
        return DB::transaction(function () use ($order, $invoiceTitleId) {
            $order->load(['items.sku', 'customer']);

            $invoiceNo = $this->generateInvoiceNo($order->tenant_id);

            // 计算税额
            $subtotal = (float) $order->final_amount;
            $taxResult = $this->taxCalculator->calculate(
                $subtotal,
                $order->customer?->billing_country ?? 'CN',
                ['region_code' => $order->customer?->billing_region],
            );

            $invoice = Invoice::create([
                'tenant_id' => $order->tenant_id,
                'customer_id' => $order->customer_id,
                'invoice_no' => $invoiceNo,
                'amount' => $subtotal + ($taxResult['tax_amount'] ?? 0),
                'subtotal' => $subtotal,
                'discount_amount' => $order->discount_amount ?? 0,
                'currency' => $order->currency ?? 'CNY',
                'status' => 'paid',
                'paid' => true,
                'paid_at' => $order->paid_at ?? now(),
                'billing_reason' => 'order',
                'tax_type' => $taxResult['tax_type'] ?? 'none',
                'tax_rate_applied' => $taxResult['tax_rate'] ?? 0,
                'tax_amount' => $taxResult['tax_amount'] ?? 0,
                'notes' => "订单 {$order->order_no} 自动生成",
                'metadata' => ['order_id' => $order->id, 'order_no' => $order->order_no],
                'invoice_pdf_url' => null, // PDF 生成需 dompdf 库
            ]);

            // 创建明细行
            foreach ($order->items as $item) {
                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'metadata' => [
                        'sku_id' => $item->sku_id,
                        'order_item_id' => $item->id,
                    ],
                ]);
            }

            // 生成 HTML 内容并保存
            $html = $this->renderInvoiceHtml($invoice);
            $htmlPath = "invoices/{$invoice->id}/invoice-{$invoiceNo}.html";
            Storage::disk('local')->put($htmlPath, $html);
            $invoice->update(['invoice_pdf_url' => $htmlPath]);

            // 回写订单
            $order->update([
                'invoice_id' => $invoice->id,
                'invoice_title_id' => $invoiceTitleId,
                'invoice_generated_at' => now(),
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * 生成发票号
     */
    protected function generateInvoiceNo(int $tenantId): string
    {
        $prefix = 'INV-' . str_pad($tenantId, 4, '0', STR_PAD_LEFT) . '-';
        $datePart = now()->format('Ymd');
        $lastToday = Invoice::where('invoice_no', 'like', "{$prefix}{$datePart}%")
            ->lockForUpdate()
            ->count();

        return $prefix . $datePart . '-' . str_pad($lastToday + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 生成发票 HTML
     */
    public function renderInvoiceHtml(Invoice $invoice): string
    {
        $invoice->load(['lineItems', 'customer']);
        $items = $invoice->lineItems;
        $title = InvoiceTitle::where('customer_id', $invoice->customer_id)
            ->where('is_default', true)->first();

        $rows = '';
        foreach ($items as $item) {
            $rows .= <<<HTML
            <tr>
                <td style="padding:10px;border:1px solid #ddd">{$item->description}</td>
                <td style="padding:10px;border:1px solid #ddd;text-align:center">{$item->quantity}</td>
                <td style="padding:10px;border:1px solid #ddd;text-align:right">¥" . number_format($item->unit_price, 2) . "</td>
                <td style="padding:10px;border:1px solid #ddd;text-align:right">¥" . number_format($item->subtotal, 2) . "</td>
            </tr>
HTML;
        }

        $titleHtml = '';
        if ($title) {
            $titleHtml = <<<HTML
            <div style="margin:16px 0;padding:12px;background:#f9f9f9;border-radius:4px">
                <strong>发票抬头：</strong>{$title->title}<br>
                <strong>税号：</strong>{$title->tax_no}<br>
                <strong>地址：</strong>{$title->address}<br>
                <strong>电话：</strong>{$title->phone}<br>
                <strong>开户行：</strong>{$title->bank_name}<br>
                <strong>账号：</strong>{$title->bank_account}
            </div>
HTML;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><style>
            body { font-family: 'Helvetica Neue', Arial, sans-serif; padding: 40px; color: #333; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { margin: 0; font-size: 24px; }
            .header p { color: #666; margin: 4px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; text-align: left; }
            td { padding: 10px; border: 1px solid #ddd; }
            .total-row { font-weight: bold; font-size: 16px; }
            .footer { text-align: center; color: #999; font-size: 12px; margin-top: 40px; }
        </style></head>
        <body>
            <div class="header">
                <h1>电子发票</h1>
                <p>发票号：{$invoice->invoice_no}</p>
                <p>开票日期：{$invoice->created_at->format('Y-m-d')}</p>
                <p>状态：已开票</p>
            </div>
            {$titleHtml}
            <table>
                <thead>
                    <tr><th>商品名称</th><th>数量</th><th>单价</th><th>小计</th></tr>
                </thead>
                <tbody>{$rows}</tbody>
                <tfoot>
                    <tr><td colspan="3" style="text-align:right;padding:10px;border:1px solid #ddd">小计</td>
                        <td style="padding:10px;border:1px solid #ddd;text-align:right">¥" . number_format($invoice->subtotal, 2) . "</td></tr>
                    <tr><td colspan="3" style="text-align:right;padding:10px;border:1px solid #ddd">税额 ({$invoice->tax_rate_applied}%)</td>
                        <td style="padding:10px;border:1px solid #ddd;text-align:right">¥" . number_format($invoice->tax_amount, 2) . "</td></tr>
                    <tr class="total-row"><td colspan="3" style="text-align:right;padding:10px;border:1px solid #ddd">合计</td>
                        <td style="padding:10px;border:1px solid #ddd;text-align:right">¥" . number_format($invoice->amount, 2) . "</td></tr>
                </tfoot>
            </table>
            <div class="footer">
                <p>此发票由系统自动生成</p>
                <p>Huwutong License Management System</p>
            </div>
        </body>
        </html>
HTML;
    }

    /**
     * 获取发票 HTML 内容
     */
    public function getInvoiceHtml(int $invoiceId): ?string
    {
        $invoice = Invoice::findOrFail($invoiceId);
        if ($invoice->invoice_pdf_url) {
            $content = Storage::disk('local')->get($invoice->invoice_pdf_url);
            return $content;
        }
        return $this->renderInvoiceHtml($invoice);
    }

    /**
     * 重新发送发票邮件
     */
    public function resendInvoiceEmail(int $invoiceId): bool
    {
        $invoice = Invoice::with('customer.user')->findOrFail($invoiceId);
        $email = $invoice->customer?->user?->email;
        if (!$email) {
            throw new \RuntimeException('客户无邮箱地址');
        }

        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(
                new \App\Mail\InvoiceMail($invoice)
            );
            Log::info('发票邮件已重发', ['invoice_id' => $invoiceId, 'email' => $email]);
            return true;
        } catch (\Throwable $e) {
            Log::error('发票邮件重发失败', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 获取客户发票列表
     */
    public function getCustomerInvoices(int $customerId, array $params = [])
    {
        $query = Invoice::with(['lineItems', 'customer'])
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at');

        $perPage = $params['per_page'] ?? 20;
        $page = $params['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 获取租户所有发票
     */
    public function getTenantInvoices(int $tenantId, array $params = [])
    {
        $query = Invoice::with(['customer', 'lineItems'])
            ->where('tenant_id', $tenantId);

        if ($status = $params['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($search = $params['search'] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }
        if ($dateFrom = $params['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $params['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $sort = $params['sort'] ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $query->orderBy(in_array($field, ['created_at', 'amount', 'invoice_no']) ? $field : 'created_at', $direction);

        $perPage = $params['per_page'] ?? 20;
        $page = $params['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 发票统计
     */
    public function getStats(int $tenantId): array
    {
        $base = Invoice::where('tenant_id', $tenantId);
        $invoicedOrderIds = Invoice::where('tenant_id', $tenantId)
            ->whereNotNull('metadata->order_id')
            ->get()
            ->pluck('metadata.order_id')
            ->filter()
            ->values()
            ->toArray();
        $billableOrders = Order::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereNotIn('id', $invoicedOrderIds)
            ->count();

        return [
            'total_invoices' => (clone $base)->count(),
            'total_amount' => (clone $base)->sum('amount'),
            'paid_invoices' => (clone $base)->where('status', 'paid')->count(),
            'today_invoices' => (clone $base)->whereDate('created_at', today())->count(),
            'today_amount' => (clone $base)->whereDate('created_at', today())->sum('amount'),
            'billable_orders' => $billableOrders,
        ];
    }
}
