<?php

namespace App\Services\Tax;

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * 中国电子发票服务
 * 
 * 支持:
 * - 发票通 (Fapiaotong) API
 * - 百旺/航信税控盘接口
 * - 增值税专用发票/普通发票
 * - 红字发票（冲红）
 */
class ChinaEInvoiceService
{
    protected string $provider;
    protected ?string $appKey;
    protected ?string $appSecret;
    protected ?string $endpoint;
    protected string $taxpayerId;

    public function __construct()
    {
        $this->provider = config('tax-automation.china_einvoice.fapiao_tong.provider', 'fapiaotong');
        $this->appKey = config('tax-automation.china_einvoice.fapiao_tong.app_key');
        $this->appSecret = config('tax-automation.china_einvoice.fapiao_tong.app_secret');
        $this->endpoint = config('tax-automation.china_einvoice.fapiao_tong.endpoint');
        $this->taxpayerId = config('tax-automation.china_einvoice.fapiao_tong.taxpayer_id', '');
    }

    /**
     * 开具电子发票
     * 
     * @param Invoice $invoice 本地发票
     * @param array $options ['buyer_tax_id', 'buyer_name', 'buyer_address', 'buyer_phone', 'bank_info', 'email']
     * @return array
     */
    public function issueInvoice(Invoice $invoice, array $options = []): array
    {
        $invoiceType = $options['invoice_type'] ?? ($options['buyer_tax_id'] ? 'special' : 'normal');
        // special = 增值税专用发票, normal = 增值税普通发票

        $items = $this->buildInvoiceItems($invoice);

        $payload = [
            'invoiceType' => $invoiceType === 'special' ? 'SPECIAL_VAT' : 'NORMAL_VAT',
            'taxpayerId' => $this->taxpayerId,
            'buyerName' => $options['buyer_name'] ?? $invoice->customer?->name ?? '散客',
            'buyerTaxId' => $options['buyer_tax_id'] ?? '',
            'buyerAddress' => $options['buyer_address'] ?? $invoice->billing_address_line1 ?? '',
            'buyerPhone' => $options['buyer_phone'] ?? $invoice->customer?->phone ?? '',
            'buyerBankName' => $options['buyer_bank_name'] ?? '',
            'buyerBankAccount' => $options['buyer_bank_account'] ?? '',
            'amount' => (float) $invoice->subtotal ?? (float) $invoice->amount,
            'taxRate' => $invoice->tax_rate_applied ? round($invoice->tax_rate_applied * 100, 2) : 0,
            'taxAmount' => (float) ($invoice->tax_amount ?? 0),
            'totalAmount' => (float) ($invoice->subtotal ?? $invoice->amount) + (float) ($invoice->tax_amount ?? 0),
            'orderNo' => $invoice->invoice_no ?? (string) $invoice->id,
            'items' => $items,
            'remark' => $options['remark'] ?? 'License授权服务',
            'email' => $options['email'] ?? $invoice->customer?->email ?? '',
        ];

        // 根据提供商选择不同接口
        return match ($this->provider) {
            'fapiaotong' => $this->sendToFapiaotong($payload),
            'baiwang'    => $this->sendToBaiwang($payload),
            'hangxin'    => $this->sendToHangxin($payload),
            default      => $this->sendToFapiaotong($payload),
        };
    }

    /**
     * 开具红字发票（冲红/退款）
     */
    public function issueCreditNote(Invoice $originalInvoice, float $amount, string $reason = ''): array
    {
        $payload = [
            'originalInvoiceNo' => $originalInvoice->invoice_no,
            'amount' => $amount,
            'reason' => $reason ?: '销售退回',
            'applyType' => 'sales_return',
        ];

        return match ($this->provider) {
            'fapiaotong' => $this->sendToFapiaotong(array_merge($payload, ['action' => 'credit'])),
            'baiwang'    => $this->sendToBaiwang(array_merge($payload, ['action' => 'credit'])),
            'hangxin'    => $this->sendToHangxin(array_merge($payload, ['action' => 'credit'])),
            default      => $this->sendToFapiaotong(array_merge($payload, ['action' => 'credit'])),
        };
    }

    /**
     * 查询发票状态
     */
    public function queryInvoice(string $invoiceNo): array
    {
        try {
            $client = $this->httpClient();
            $response = $client->get($this->endpoint . '/invoice/query', [
                'json' => [
                    'invoiceNo' => $invoiceNo,
                    'taxpayerId' => $this->taxpayerId,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (\Exception $e) {
            Log::error('China e-invoice query failed', [
                'invoice_no' => $invoiceNo,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 构建发票明细项目
     */
    protected function buildInvoiceItems(Invoice $invoice): array
    {
        // 从发票行项目或使用默认
        $lineItems = $invoice->lineItems ?? collect();

        if ($lineItems->isNotEmpty()) {
            return $lineItems->map(function ($item) {
                return [
                    'name' => $item->description ?? 'License授权',
                    'spec' => '',
                    'unit' => '套',
                    'quantity' => $item->quantity ?? 1,
                    'price' => (float) ($item->unit_price ?? 0),
                    'amount' => (float) ($item->total ?? 0),
                    'taxRate' => 0, // 由发票通自动计算
                    'taxAmount' => 0,
                ];
            })->toArray();
        }

        // 无明细项时用默认
        return [[
            'name' => '软件授权服务',
            'spec' => config('tax-automation.seller.tax_code', '软件'),
            'unit' => '套',
            'quantity' => 1,
            'price' => (float) ($invoice->subtotal ?? $invoice->amount),
            'amount' => (float) ($invoice->subtotal ?? $invoice->amount),
            'taxRate' => $invoice->tax_rate_applied ? round($invoice->tax_rate_applied * 100, 1) : 0,
            'taxAmount' => (float) ($invoice->tax_amount ?? 0),
        ]];
    }

    /**
     * 发送到发票通 API
     */
    protected function sendToFapiaotong(array $payload): array
    {
        try {
            $timestamp = now()->timestamp;
            $sign = $this->sign($payload, $timestamp);

            $client = $this->httpClient();
            $response = $client->post($this->endpoint . '/invoice/issue', [
                'json' => $payload,
                'headers' => [
                    'X-App-Key' => $this->appKey,
                    'X-Timestamp' => $timestamp,
                    'X-Signature' => $sign,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (($result['code'] ?? '') === '0000') {
                return [
                    'success' => true,
                    'invoice_no' => $result['invoiceNo'] ?? '',
                    'invoice_code' => $result['invoiceCode'] ?? '',
                    'pdf_url' => $result['pdfUrl'] ?? '',
                    'check_code' => $result['checkCode'] ?? '',
                    'provider' => 'fapiaotong',
                ];
            }

            Log::error('Fapiaotong API error', [
                'code' => $result['code'] ?? '',
                'message' => $result['message'] ?? '',
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => $result['message'] ?? '发票通接口返回错误',
                'code' => $result['code'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('Fapiaotong request failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 百旺税控接口
     */
    protected function sendToBaiwang(array $payload): array
    {
        // 百旺/航信通常使用本地税控盘 + 本地服务
        // 实现取决于具体部署方式
        return [
            'success' => false,
            'error' => 'Baiwang integration - configure local tax control device',
        ];
    }

    /**
     * 航信税控接口
     */
    protected function sendToHangxin(array $payload): array
    {
        return [
            'success' => false,
            'error' => 'Hangxin integration - configure local tax control device',
        ];
    }

    /**
     * 签名（发票通 API 格式）
     */
    protected function sign(array $payload, int $timestamp): string
    {
        $data = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $stringToSign = "{$this->appKey}{$timestamp}{$data}{$this->appSecret}";
        return strtoupper(md5($stringToSign));
    }

    protected function httpClient()
    {
        return new \GuzzleHttp\Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    /**
     * 检查配置是否可用
     */
    public function isConfigured(): bool
    {
        return !empty($this->appKey) && !empty($this->appSecret) && !empty($this->endpoint);
    }
}
