<?php
namespace App\Services;
use App\Models\ChinaInvoice;
use App\Models\ChinaInvoiceItem;
use App\Models\ChinaTaxDevice;
use App\Models\ChinaTaxReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChinaInvoiceService
{
    const VAT_RATES = [13, 9, 6, 3, 0]; // 增值税税率

    /**
     * 开票
     */
    public function issueInvoice(array $params): ChinaInvoice
    {
        $tenantId = $params['tenant_id'];
        $device = ChinaTaxDevice::where('tenant_id', $tenantId)->where('is_active', true)->first();
        if (!$device) throw new \RuntimeException(__("app.china_invoice.tax_device_not_configured"));

        $invoiceCode = ChinaInvoice::generateInvoiceCode($tenantId);
        $invoiceNo = ChinaInvoice::generateInvoiceNo($tenantId);

        $totalAmount = round($params['amount'] + $params['tax_amount'], 2);

        $invoice = ChinaInvoice::create([
            'tenant_id' => $tenantId,
            'template_id' => $params['template_id'] ?? null,
            'tax_device_id' => $device->id,
            'order_id' => $params['order_id'] ?? null,
            'invoice_type' => $params['invoice_type'],
            'invoice_code' => $invoiceCode,
            'invoice_no' => $invoiceNo,
            'status' => 'pending',
            'buyer_name' => $params['buyer_name'],
            'buyer_tax_id' => $params['buyer_tax_id'] ?? null,
            'buyer_address' => $params['buyer_address'] ?? null,
            'buyer_phone' => $params['buyer_phone'] ?? null,
            'buyer_bank' => $params['buyer_bank'] ?? null,
            'buyer_bank_account' => $params['buyer_bank_account'] ?? null,
            'seller_name' => $device->company_name,
            'seller_tax_id' => $device->taxpayer_id,
            'seller_address' => $device->registered_address,
            'seller_phone' => $device->phone,
            'seller_bank' => $device->bank_name,
            'seller_bank_account' => $device->bank_account,
            'amount' => $params['amount'],
            'tax_rate' => $params['tax_rate'] ?? 13,
            'tax_amount' => $params['tax_amount'],
            'total_amount' => $totalAmount,
            'drawer' => $params['drawer'] ?? 'system',
            'reviewer' => $params['reviewer'] ?? null,
            'payee' => $params['payee'] ?? null,
            'remark' => $params['remark'] ?? null,
        ]);

        // 创建行项目
        foreach ($params['items'] ?? [] as $item) {
            $invoice->items()->create($item);
        }

        // 模拟税控开票
        $this->simulateTaxControl($invoice);

        return $invoice->fresh();
    }

    /**
     * 红冲(负数发票)
     */
    public function redLetter(ChinaInvoice $originalInvoice, string $reason = ''): ChinaInvoice
    {
        if ($originalInvoice->status !== 'issued') {
            throw new \RuntimeException(__("app.china_invoice.invoice_can_only_red_stamp_invoiced"));
        }

        $params = [
            'tenant_id' => $originalInvoice->tenant_id,
            'template_id' => $originalInvoice->template_id,
            'order_id' => $originalInvoice->order_id,
            'invoice_type' => $originalInvoice->invoice_type,
            'buyer_name' => $originalInvoice->buyer_name,
            'buyer_tax_id' => $originalInvoice->buyer_tax_id,
            'amount' => -$originalInvoice->amount,
            'tax_rate' => $originalInvoice->tax_rate,
            'tax_amount' => -$originalInvoice->tax_amount,
            'remark' => "红冲原发票 {$originalInvoice->invoice_code}{$originalInvoice->invoice_no}: {$reason}",
            'items' => $originalInvoice->items->map(fn($i) => [
                'item_name' => $i->item_name, 'quantity' => -$i->quantity,
                'unit_price' => $i->unit_price, 'amount' => -$i->amount,
                'tax_rate' => $i->tax_rate, 'tax_amount' => -$i->tax_amount,
                'unit' => $i->unit, 'specification' => $i->specification,
            ])->toArray(),
        ];

        $redLetter = $this->issueInvoice($params);
        $redLetter->update([
            'red_letter_source' => "{$originalInvoice->invoice_code}{$originalInvoice->invoice_no}",
            'status' => 'red_letter',
        ]);
        $originalInvoice->update(['status' => 'red_letter']);

        return $redLetter;
    }

    /**
     * 作废
     */
    public function voidInvoice(ChinaInvoice $invoice): void
    {
        if ($invoice->status !== 'pending') {
            throw new \RuntimeException(__("app.china_invoice.invoice_can_only_void_pending"));
        }
        $invoice->update(['status' => 'voided', 'voided_at' => now()]);
    }

    /**
     * 生成月度税务报告
     */
    public function generateTaxReport(int $tenantId, string $period): ChinaTaxReport
    {
        $start = Carbon::parse($period . '-01');
        $end = $start->copy()->endOfMonth();

        $invoices = ChinaInvoice::where('tenant_id', $tenantId)
            ->whereBetween('issued_at', [$start, $end])
            ->where('status', 'issued')
            ->get();

        $totalSales = $invoices->sum('amount');
        $totalTax = $invoices->sum('tax_amount');
        $deductible = DB::table('china_invoice_items')
            ->join('china_invoices', 'china_invoices.id', '=', 'china_invoice_items.invoice_id')
            ->where('china_invoices.tenant_id', $tenantId)
            ->whereBetween('china_invoice_items.created_at', [$start, $end])
            ->where('china_invoices.status', 'issued')
            ->where('china_invoices.invoice_type', 'vat_special')
            ->sum(DB::raw('china_invoice_items.tax_amount'));

        return ChinaTaxReport::updateOrCreate(
            ['tenant_id' => $tenantId, 'period' => $period, 'report_type' => 'vat'],
            [
                'total_sales' => $totalSales,
                'total_tax' => $totalTax,
                'deductible_tax' => $deductible,
                'payable_tax' => max(0, $totalTax - $deductible),
                'breakdown' => [
                    'invoice_count' => $invoices->count(),
                    'by_rate' => $invoices->groupBy('tax_rate')->map(fn($g) => $g->sum('amount')),
                ],
                'status' => 'draft',
            ]
        );
    }

    /**
     * 模拟税控开票
     */
    protected function simulateTaxControl(ChinaInvoice $invoice): void
    {
        $code = ChinaInvoice::generateTaxControlCode($invoice->invoice_no, $invoice->total_amount);
        $invoice->update([
            'tax_control_code' => $code,
            'qr_code_url' => "https://inv-veri.chinatax.gov.cn/verify?code={$invoice->invoice_code}&no={$invoice->invoice_no}",
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }
}
