<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceTaxLine;
use App\Models\TaxExemptCertificate;
use App\Models\TaxRate;
use Illuminate\Support\Collection;

class TaxCalculatorService
{
    /**
     * EU OSS/IOSS 阈值（年销售额 EUR）
     */
    const EU_OSS_THRESHOLD = 10000;
    const EU_IOSS_THRESHOLD = 150;

    /**
     * 计算订单税额
     *
     * @param float $amount 金额（不含税）
     * @param string $countryCode ISO 国家代码
     * @param array $options [
     *   'region_code' => ?string,
     *   'tax_type' => ?string,        // 强制指定税种
     *   'customer_id' => ?int,        // 检查免税证书
     *   'tenant_id' => ?int,
     *   'is_b2b' => bool,              // 企业客户（用于 EU 反向征收）
     *   'seller_country' => ?string,   // 卖家所在国家
     * ]
     * @return array
     */
    public function calculate(float $amount, string $countryCode, array $options = []): array
    {
        $countryCode = strtoupper($countryCode);
        $regionCode = isset($options['region_code']) ? strtoupper($options['region_code']) : null;
        $customerId = $options['customer_id'] ?? null;
        $tenantId = $options['tenant_id'] ?? null;
        $isB2b = $options['is_b2b'] ?? false;
        $sellerCountry = isset($options['seller_country']) ? strtoupper($options['seller_country']) : null;

        // 1. 检查免税证书
        $exemption = $this->checkExemption($tenantId, $customerId, $countryCode);
        if ($exemption) {
            return $this->buildResult($amount, 0, 'none', null, 'exempt', $exemption['reason']);
        }

        // 2. 查询适用税率
        $taxRate = TaxRate::findRate($countryCode, $regionCode);
        if (! $taxRate) {
            // 找不到税率 = 不收税
            return $this->buildResult($amount, 0, 'none', null);
        }

        $rate = $taxRate->rate;
        $type = $taxRate->type;
        $reportingCode = null;

        // 3. EU 特殊规则
        if ($taxRate->is_eu) {
            $euResult = $this->handleEuRules($amount, $countryCode, $sellerCountry, $isB2b);
            if ($euResult !== null) {
                return $euResult;
            }
            // 标记 OSS/IOSS
            $reportingCode = $isB2b ? 'EU_OSS' : 'EU_IOSS';
        }

        // 4. 计算税额
        $taxAmount = round($amount * $rate, 2);

        return $this->buildResult($amount, $taxAmount, $type, $rate, 'taxable', null, $reportingCode, $taxRate->id);
    }

    /**
     * 将税额应用到发票
     */
    public function applyToInvoice(Invoice $invoice, array $taxResult): void
    {
        $invoice->update([
            'billing_country' => $taxResult['country_code'] ?? $invoice->billing_country,
            'tax_type' => $taxResult['tax_type'],
            'tax_rate_applied' => $taxResult['rate'],
            'tax_amount' => $taxResult['tax_amount'],
            'subtotal' => $taxResult['taxable_amount'],
            'tax_exempt_certificate_id' => $taxResult['exempt_reason'] ? ($taxResult['exempt_reason'] === 'exempt' ? 'EXEMPT' : null) : null,
            'tax_exempt_reason' => $taxResult['exempt_reason'],
            'tax_reporting_code' => $taxResult['reporting_code'],
        ]);

        // 创建税行
        InvoiceTaxLine::create([
            'invoice_id' => $invoice->id,
            'tax_rate_id' => $taxResult['tax_rate_id'],
            'name' => $taxResult['type_label'] ?? 'Tax',
            'rate' => $taxResult['rate'] ?? 0,
            'taxable_amount' => $taxResult['taxable_amount'],
            'tax_amount' => $taxResult['tax_amount'],
            'exempt_reason' => $taxResult['exempt_reason'],
        ]);
    }

    /**
     * 更新发票总金额（含税）
     */
    public function calculateTotal(Invoice $invoice): float
    {
        $subtotal = (float) ($invoice->subtotal ?? $invoice->amount ?? 0);
        $tax = (float) ($invoice->tax_amount ?? 0);

        return round($subtotal + $tax, 2);
    }

    /**
     * 获取国家列表（用于前端下拉）
     */
    public function getCountryTaxInfo(): array
    {
        $rates = TaxRate::where('is_active', true)
            ->whereNull('region_code')
            ->whereNull('effective_until')
            ->orWhere('effective_until', '>', now())
            ->get()
            ->groupBy('country_code');

        $countries = [];
        foreach ($rates as $code => $items) {
            $first = $items->first();
            $countries[] = [
                'country_code' => $code,
                'name' => $this->getCountryName($code),
                'tax_type' => $first->type,
                'tax_name' => $first->name,
                'rate' => $first->rate,
                'is_eu' => $first->is_eu,
            ];
        }

        return $countries;
    }

    /**
     * 获取国家子区域税率（如美国各州）
     */
    public function getRegionTaxes(string $countryCode): Collection
    {
        return TaxRate::where('country_code', strtoupper($countryCode))
            ->whereNotNull('region_code')
            ->where('is_active', true)
            ->get();
    }

    // ─── Private Helpers ───

    private function checkExemption(?int $tenantId, ?int $customerId, string $countryCode): ?array
    {
        if (! $tenantId && ! $customerId) return null;

        $query = TaxExemptCertificate::where('status', 'approved')
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where('issuing_country', $countryCode);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $cert = $query->first();
        if (! $cert) return null;

        return [
            'certificate_number' => $cert->certificate_number,
            'reason' => $cert->reason ?? $cert->certificate_type,
        ];
    }

    /**
     * EU 规则：B2B 反向征收 / OSS / IOSS
     */
    private function handleEuRules(float $amount, string $countryCode, ?string $sellerCountry, bool $isB2b): ?array
    {
        // B2B 跨欧盟交易 = 反向征收（0% 税率）
        if ($isB2b && $sellerCountry && $sellerCountry !== $countryCode) {
            // 买家自行申报 VAT，卖家收 0%
            return $this->buildResult($amount, 0, 'vat', 0, 'reverse_charge', 'EU reverse charge');
        }

        // 卖家在非 EU 且销售额低于 IOSS 阈值
        if ($sellerCountry && ! in_array($sellerCountry, TaxRate::getEuCountries())) {
            if ($amount <= self::EU_IOSS_THRESHOLD) {
                return $this->buildResult($amount, 0, 'vat', 0, 'ioss_threshold', 'Under IOSS threshold');
            }
        }

        return null; // 继续使用标准税率
    }

    private function buildResult(
        float $amount,
        float $taxAmount,
        string $type,
        ?float $rate = null,
        ?string $exemptReason = null,
        ?string $exemptLabel = null,
        ?string $reportingCode = null,
        ?int $taxRateId = null,
    ): array {
        $typeLabels = [
            'vat' => 'VAT',
            'gst' => 'GST',
            'sales_tax' => 'Sales Tax',
            'none' => 'No Tax',
        ];

        return [
            'taxable_amount' => $amount,
            'tax_amount' => $taxAmount,
            'total' => round($amount + $taxAmount, 2),
            'rate' => $rate,
            'tax_type' => $type,
            'type_label' => $typeLabels[$type] ?? ucfirst($type),
            'tax_percent' => $rate !== null ? round($rate * 100, 2) : 0,
            'exempt_reason' => $exemptReason,
            'exempt_label' => $exemptLabel,
            'reporting_code' => $reportingCode,
            'tax_rate_id' => $taxRateId,
        ];
    }

    private function getCountryName(string $code): string
    {
        $names = [
            'AT' => 'Austria', 'BE' => 'Belgium', 'BG' => 'Bulgaria', 'HR' => 'Croatia',
            'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'EE' => 'Estonia',
            'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany', 'GR' => 'Greece',
            'HU' => 'Hungary', 'IE' => 'Ireland', 'IT' => 'Italy', 'LV' => 'Latvia',
            'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MT' => 'Malta', 'NL' => 'Netherlands',
            'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'SK' => 'Slovakia',
            'SI' => 'Slovenia', 'ES' => 'Spain', 'SE' => 'Sweden',
            'AU' => 'Australia', 'NZ' => 'New Zealand', 'SG' => 'Singapore',
            'IN' => 'India', 'MY' => 'Malaysia', 'TH' => 'Thailand', 'VN' => 'Vietnam',
            'ID' => 'Indonesia', 'PH' => 'Philippines', 'JP' => 'Japan', 'KR' => 'South Korea',
            'CN' => 'China',
            'US' => 'United States', 'CA' => 'Canada',
            'BR' => 'Brazil',
            'AE' => 'UAE', 'SA' => 'Saudi Arabia',
            'ZA' => 'South Africa', 'NG' => 'Nigeria',
            'GB' => 'United Kingdom', 'CH' => 'Switzerland', 'NO' => 'Norway',
        ];

        return $names[$code] ?? $code;
    }
}
