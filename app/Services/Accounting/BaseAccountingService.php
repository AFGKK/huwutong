<?php

namespace App\Services\Accounting;

use App\Models\AccountingIntegration;
use App\Models\AccountingSyncMapping;
use App\Models\AccountingSyncLog;
use Illuminate\Support\Facades\Log;

/**
 * 会计系统集成 — 基类
 * 
 * 子类需实现:
 *  - connect():         建立连接 / 获取Token
 *  - pushInvoice():     推送发票
 *  - pushPayment():     推送收款
 *  - pushCustomer():    推送客户
 *  - syncStatus():      查询同步状态
 */
abstract class BaseAccountingService
{
    protected AccountingIntegration $integration;
    protected string $provider;

    public function __construct(AccountingIntegration $integration)
    {
        $this->integration = $integration;
        $this->provider = $integration->provider;
    }

    /**
     * 建立与会计系统的连接（OAuth / 登录）
     */
    abstract public function connect(array $credentials = []): bool;

    /**
     * 推送发票到会计系统
     * @return array{success: bool, remote_id?: string, remote_number?: string, error?: string}
     */
    abstract public function pushInvoice(array $invoiceData): array;

    /**
     * 推送收款记录到会计系统
     * @return array{success: bool, remote_id?: string, error?: string}
     */
    abstract public function pushPayment(array $paymentData): array;

    /**
     * 推送退款到会计系统
     * @return array{success: bool, remote_id?: string, error?: string}
     */
    abstract public function pushRefund(array $refundData): array;

    /**
     * 推送客户信息到会计系统
     * @return array{success: bool, remote_id?: string, error?: string}
     */
    abstract public function pushCustomer(array $customerData): array;

    /**
     * 检查API连接状态
     */
    abstract public function checkConnection(): array;

    /**
     * 将本地发票同步到会计系统
     */
    public function syncInvoice(\App\Models\Invoice $invoice): array
    {
        $existing = AccountingSyncMapping::where('integration_id', $this->integration->id)
            ->where('local_type', 'invoice')
            ->where('local_id', $invoice->id)
            ->first();

        if ($existing && $existing->status === 'synced') {
            return ['success' => true, 'message' => 'Already synced', 'mapping' => $existing];
        }

        $customerData = null;
        if ($invoice->customer) {
            $customerData = [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
                'email' => $invoice->customer->email,
                'phone' => $invoice->customer->phone,
                'address' => $invoice->customer->address ?? '',
                'tax_id' => $invoice->customer->tax_id ?? '',
            ];
            $this->pushCustomer($customerData);
        }

        $result = $this->pushInvoice([
            'invoice_no' => $invoice->invoice_no,
            'amount' => (float) $invoice->amount,
            'subtotal' => (float) $invoice->subtotal,
            'tax_amount' => (float) ($invoice->tax_amount ?? 0),
            'tax_rate' => (float) ($invoice->tax_rate_applied ?? 0),
            'currency' => $invoice->currency ?? 'CNY',
            'due_date' => $invoice->due_at?->format('Y-m-d'),
            'issue_date' => $invoice->created_at->format('Y-m-d'),
            'paid_at' => $invoice->paid_at?->format('Y-m-d'),
            'customer_name' => $invoice->customer?->name ?? 'Walk-in Customer',
            'customer_email' => $invoice->customer?->email ?? '',
            'description' => "Invoice #{$invoice->invoice_no}",
            'status' => $invoice->paid ? 'paid' : ($invoice->status ?? 'sent'),
        ]);

        if ($result['success']) {
            AccountingSyncMapping::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'local_type' => 'invoice',
                    'local_id' => $invoice->id,
                ],
                [
                    'tenant_id' => $this->integration->tenant_id,
                    'remote_id' => $result['remote_id'] ?? '',
                    'remote_number' => $result['remote_number'] ?? $result['remote_id'] ?? '',
                    'status' => 'synced',
                    'synced_at' => now(),
                ]
            );
        } else {
            AccountingSyncMapping::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'local_type' => 'invoice',
                    'local_id' => $invoice->id,
                ],
                [
                    'tenant_id' => $this->integration->tenant_id,
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ]
            );
        }

        return $result;
    }

    /**
     * 批量同步待处理单据
     */
    public function syncPending(): AccountingSyncLog
    {
        $log = AccountingSyncLog::create([
            'tenant_id' => $this->integration->tenant_id,
            'integration_id' => $this->integration->id,
            'sync_type' => 'auto',
            'direction' => 'push',
            'entity_type' => 'invoice,payment,refund',
            'started_at' => now(),
        ]);

        $success = 0;
        $failed = 0;
        $details = [];

        // 同步未处理的发票
        $mappingTable = (new \App\Models\AccountingSyncMapping)->getTable();
        $pendingInvoices = \App\Models\Invoice::where('tenant_id', $this->integration->tenant_id)
            ->where(function ($q) use ($mappingTable) {
                // 没有映射记录 或 映射记录为 failed
                $q->whereNotExists(function ($sq) use ($mappingTable) {
                    $sq->select(\DB::raw(1))
                       ->from($mappingTable)
                       ->whereColumn('local_id', 'invoices.id')
                       ->where('local_type', 'invoice')
                       ->where('integration_id', $this->integration->id)
                       ->whereIn('status', ['synced', 'pending']);
                })->orWhereExists(function ($sq) use ($mappingTable) {
                    $sq->select(\DB::raw(1))
                       ->from($mappingTable)
                       ->whereColumn('local_id', 'invoices.id')
                       ->where('local_type', 'invoice')
                       ->where('integration_id', $this->integration->id)
                       ->where('status', 'failed');
                });
            })
            ->limit(50)
            ->get();

        foreach ($pendingInvoices as $invoice) {
            try {
                $r = $this->syncInvoice($invoice);
                if ($r['success']) {
                    $success++;
                    $details[] = "Invoice #{$invoice->invoice_no}: synced";
                } else {
                    $failed++;
                    $details[] = "Invoice #{$invoice->invoice_no}: {$r['error']}";
                }
            } catch (\Exception $e) {
                $failed++;
                $details[] = "Invoice #{$invoice->invoice_no}: " . $e->getMessage();
            }
        }

        $log->update([
            'total_count' => $pendingInvoices->count(),
            'success_count' => $success,
            'fail_count' => $failed,
            'details' => $details,
            'completed_at' => now(),
        ]);

        $this->integration->update([
            'last_sync_at' => now(),
            'last_success_at' => $failed === 0 ? now() : $this->integration->last_success_at,
            'last_error' => $failed > 0 ? "{$failed} items failed" : null,
        ]);

        return $log;
    }

    /**
     * HTTP 客户端
     */
    protected function httpClient(array $options = [])
    {
        return new \GuzzleHttp\Client(array_merge([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ], $options));
    }

    /**
     * 记录日志
     */
    protected function log(string $message, array $context = []): void
    {
        Log::channel('daily')->info("[Accounting:{$this->provider}] {$message}", $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::channel('daily')->error("[Accounting:{$this->provider}] {$message}", $context);
    }
}
