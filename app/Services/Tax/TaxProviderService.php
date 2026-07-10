<?php

namespace App\Services\Tax;

use App\Models\TaxRate;
use App\Services\TaxCalculatorService;
use Illuminate\Support\Facades\Log;

/**
 * 税务提供商标配服务
 * 
 * 支持 TaxJar / Stripe Tax / Avalara 实时税率查询
 * 作为 TaxCalculatorService 的补充/替代
 */
class TaxProviderService
{
    protected TaxCalculatorService $localCalculator;

    public function __construct(TaxCalculatorService $localCalculator)
    {
        $this->localCalculator = $localCalculator;
    }

    /**
     * 计算税额（自动选择提供商）
     */
    public function calculate(float $amount, string $countryCode, array $options = []): array
    {
        $provider = config('tax-automation.default_provider', 'local');

        // 中国税务走本地计算 + 电子发票
        if ($countryCode === 'CN') {
            return $this->localCalculator->calculate($amount, $countryCode, $options);
        }

        $result = match ($provider) {
            'taxjar'  => $this->calculateViaTaxJar($amount, $countryCode, $options),
            'stripe'  => $this->calculateViaStripe($amount, $countryCode, $options),
            'avalara' => $this->calculateViaAvalara($amount, $countryCode, $options),
            default   => $this->localCalculator->calculate($amount, $countryCode, $options),
        };

        // Fallback: 如果外部提供商失败，回退到本地计算
        if (isset($result['error'])) {
            Log::warning('Tax provider failed, falling back to local', [
                'provider' => $provider,
                'error' => $result['error'],
            ]);
            return $this->localCalculator->calculate($amount, $countryCode, $options);
        }

        return $result;
    }

    /**
     * 通过 TaxJar 计算税率
     */
    protected function calculateViaTaxJar(float $amount, string $countryCode, array $options): array
    {
        $apiKey = config('tax-automation.taxjar.api_key');
        $sandbox = config('tax-automation.taxjar.sandbox', true);
        if (!$apiKey) return ['error' => 'TaxJar not configured'];

        $baseUrl = $sandbox ? 'https://api.sandbox.taxjar.com/v2' : 'https://api.taxjar.com/v2';

        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => $baseUrl,
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ],
            ]);

            $params = [
                'from_country' => config('tax-automation.seller.country_code', 'CN'),
                'to_country' => $countryCode,
                'amount' => $amount,
                'shipping' => 0,
            ];

            if (!empty($options['region_code'])) {
                $params['to_state'] = $options['region_code'];
            }
            if (!empty($options['is_b2b'])) {
                $params['exemption_type'] = 'wholesale';
            }

            $response = $client->post('/taxes', ['json' => $params]);
            $data = json_decode($response->getBody()->getContents(), true);
            $tax = $data['tax'] ?? [];

            $rate = ($tax['rate'] ?? 0) / 100;
            $taxAmount = $tax['amount_to_collect'] ?? round($amount * $rate, 2);

            return [
                'taxable_amount' => $amount,
                'tax_amount' => $taxAmount,
                'total' => round($amount + $taxAmount, 2),
                'rate' => $rate,
                'tax_type' => 'sales_tax',
                'type_label' => 'Sales Tax',
                'tax_percent' => round($rate * 100, 2),
                'exempt_reason' => null,
                'reporting_code' => 'TAXJAR',
                'tax_rate_id' => null,
                'provider' => 'taxjar',
                'breakdown' => $tax['breakdown'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 通过 Stripe Tax 计算税率
     */
    protected function calculateViaStripe(float $amount, string $countryCode, array $options): array
    {
        $secretKey = config('tax-automation.stripe.secret_key');
        if (!$secretKey) return ['error' => 'Stripe Tax not configured'];

        try {
            \Stripe\Stripe::setApiKey($secretKey);

            $params = [
                'currency' => strtolower($options['currency'] ?? 'usd'),
                'line_items' => [[
                    'amount' => (int) round($amount * 100),
                    'reference' => $options['reference'] ?? 'License',
                    'tax_behavior' => ($options['is_b2b'] ?? false) ? 'exclusive' : 'inclusive',
                ]],
                'customer_details' => [
                    'address' => [
                        'country' => $countryCode,
                        'state' => $options['region_code'] ?? null,
                    ],
                ],
            ];

            \Stripe\Stripe::setApiKey($secretKey);
            $calculation = \Stripe\Tax\Calculation::create($params);

            $taxAmount = ($calculation->tax_amount_exclusive ?? 0) / 100;
            $rate = $calculation->tax_rate ?? 0;

            return [
                'taxable_amount' => $amount,
                'tax_amount' => $taxAmount,
                'total' => round($amount + $taxAmount, 2),
                'rate' => $rate / 100,
                'tax_type' => 'vat',
                'type_label' => 'Tax',
                'tax_percent' => $rate,
                'exempt_reason' => null,
                'reporting_code' => 'STRIPE_TAX',
                'tax_rate_id' => null,
                'provider' => 'stripe',
                'stripe_calculation_id' => $calculation->id,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 通过 Avalara 计算税率
     */
    protected function calculateViaAvalara(float $amount, string $countryCode, array $options): array
    {
        $accountId = config('tax-automation.avalara.account_id');
        $licenseKey = config('tax-automation.avalara.license_key');
        if (!$accountId || !$licenseKey) return ['error' => 'Avalara not configured'];

        $sandbox = config('tax-automation.avalara.sandbox', true);
        $baseUrl = $sandbox
            ? 'https://sandbox-rest.avatax.com/api/v2'
            : 'https://rest.avatax.com/api/v2';

        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => $baseUrl,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("{$accountId}:{$licenseKey}"),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $lines = [[
                'Number' => '1',
                'Quantity' => 1,
                'Amount' => $amount,
                'TaxCode' => $options['tax_code'] ?? 'P0000000', // P0000000 = 通用数字产品
                'Description' => $options['description'] ?? 'Software License',
            ]];

            $body = [
                'companyCode' => config('tax-automation.avalara.company_code', 'DEFAULT'),
                'date' => now()->format('Y-m-d'),
                'customerCode' => $options['customer_id'] ?? 'CUSTOMER',
                'addresses' => [
                    'singleLocation' => [
                        'country' => $countryCode,
                        'region' => $options['region_code'] ?? '',
                        'postalCode' => $options['postal_code'] ?? '',
                    ],
                ],
                'lines' => $lines,
                'type' => 'SalesOrder',
                'commit' => false,
                'currencyCode' => strtoupper($options['currency'] ?? 'USD'),
            ];

            $response = $client->post('/transactions/create', ['json' => $body]);
            $data = json_decode($response->getBody()->getContents(), true);

            $totalTax = $data['totalTax'] ?? 0;
            $effectiveRate = $data['totalTaxCalculated'] > 0
                ? $totalTax / $data['totalTaxCalculated']
                : 0;

            return [
                'taxable_amount' => $amount,
                'tax_amount' => $totalTax,
                'total' => round($amount + $totalTax, 2),
                'rate' => $effectiveRate,
                'tax_type' => $data['taxSummary'][0]['taxGroup'] ?? 'vat',
                'type_label' => $data['taxSummary'][0]['taxName'] ?? 'Tax',
                'tax_percent' => round($effectiveRate * 100, 2),
                'exempt_reason' => null,
                'reporting_code' => 'AVALARA',
                'tax_rate_id' => null,
                'provider' => 'avalara',
                'avalara_doc_id' => $data['id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
