<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        // EU VAT 标准税率 (2025/2026)
        $euCountries = [
            ['country_code' => 'AT', 'name' => 'VAT', 'rate' => 0.2000, 'type' => 'vat', 'is_eu' => true, 'description' => 'Austria VAT Standard'],
            ['country_code' => 'BE', 'name' => 'VAT', 'rate' => 0.2100, 'type' => 'vat', 'is_eu' => true, 'description' => 'Belgium VAT Standard'],
            ['country_code' => 'BG', 'name' => 'VAT', 'rate' => 0.2000, 'type' => 'vat', 'is_eu' => true, 'description' => 'Bulgaria VAT Standard'],
            ['country_code' => 'HR', 'name' => 'VAT', 'rate' => 0.2500, 'type' => 'vat', 'is_eu' => true, 'description' => 'Croatia VAT Standard'],
            ['country_code' => 'CY', 'name' => 'VAT', 'rate' => 0.1900, 'type' => 'vat', 'is_eu' => true, 'description' => 'Cyprus VAT Standard'],
            ['country_code' => 'CZ', 'name' => 'VAT', 'rate' => 0.2100, 'type' => 'vat', 'is_eu' => true, 'description' => 'Czech Republic VAT Standard'],
            ['country_code' => 'DK', 'name' => 'VAT', 'rate' => 0.2500, 'type' => 'vat', 'is_eu' => true, 'description' => 'Denmark VAT Standard'],
            ['country_code' => 'EE', 'name' => 'VAT', 'rate' => 0.2200, 'type' => 'vat', 'is_eu' => true, 'description' => 'Estonia VAT Standard'],
            ['country_code' => 'FI', 'name' => 'VAT', 'rate' => 0.2550, 'type' => 'vat', 'is_eu' => true, 'description' => 'Finland VAT Standard'],
            ['country_code' => 'FR', 'name' => 'VAT', 'rate' => 0.2000, 'type' => 'vat', 'is_eu' => true, 'description' => 'France VAT Standard'],
            ['country_code' => 'DE', 'name' => 'VAT', 'rate' => 0.1900, 'type' => 'vat', 'is_eu' => true, 'description' => 'Germany VAT Standard'],
            ['country_code' => 'GR', 'name' => 'VAT', 'rate' => 0.2400, 'type' => 'vat', 'is_eu' => true, 'description' => 'Greece VAT Standard'],
            ['country_code' => 'HU', 'name' => 'VAT', 'rate' => 0.2700, 'type' => 'vat', 'is_eu' => true, 'description' => 'Hungary VAT Standard'],
            ['country_code' => 'IE', 'name' => 'VAT', 'rate' => 0.2300, 'type' => 'vat', 'is_eu' => true, 'description' => 'Ireland VAT Standard'],
            ['country_code' => 'IT', 'name' => 'VAT', 'rate' => 0.2200, 'type' => 'vat', 'is_eu' => true, 'description' => 'Italy VAT Standard'],
            ['country_code' => 'LV', 'name' => 'VAT', 'rate' => 0.2100, 'type' => 'vat', 'is_eu' => true, 'description' => 'Latvia VAT Standard'],
            ['country_code' => 'LT', 'name' => 'VAT', 'rate' => 0.2100, 'type' => 'vat', 'is_eu' => true, 'description' => 'Lithuania VAT Standard'],
            ['country_code' => 'LU', 'name' => 'VAT', 'rate' => 0.1700, 'type' => 'vat', 'is_eu' => true, 'description' => 'Luxembourg VAT Standard'],
            ['country_code' => 'MT', 'name' => 'VAT', 'rate' => 0.1800, 'type' => 'vat', 'is_eu' => true, 'description' => 'Malta VAT Standard'],
            ['country_code' => 'NL', 'name' => 'VAT', 'rate' => 0.2100, 'type' => 'vat', 'is_eu' => true, 'description' => 'Netherlands VAT Standard'],
            ['country_code' => 'PL', 'name' => 'VAT', 'rate' => 0.2300, 'type' => 'vat', 'is_eu' => true, 'description' => 'Poland VAT Standard'],
            ['country_code' => 'PT', 'name' => 'VAT', 'rate' => 0.2300, 'type' => 'vat', 'is_eu' => true, 'description' => 'Portugal VAT Standard'],
            ['country_code' => 'RO', 'name' => 'VAT', 'rate' => 0.1900, 'type' => 'vat', 'is_eu' => true, 'description' => 'Romania VAT Standard'],
            ['country_code' => 'SK', 'name' => 'VAT', 'rate' => 0.2300, 'type' => 'vat', 'is_eu' => true, 'description' => 'Slovakia VAT Standard'],
            ['country_code' => 'SI', 'name' => 'VAT', 'rate' => 0.2200, 'type' => 'vat', 'is_eu' => true, 'description' => 'Slovenia VAT Standard'],
            ['country_code' => 'ES', 'name' => 'VAT', 'rate' => 0.2100, 'type' => 'vat', 'is_eu' => true, 'description' => 'Spain VAT Standard'],
            ['country_code' => 'SE', 'name' => 'VAT', 'rate' => 0.2500, 'type' => 'vat', 'is_eu' => true, 'description' => 'Sweden VAT Standard'],
        ];

        // 非 EU 主要国家 GST/Sales Tax
        $nonEuCountries = [
            // 亚太 GST
            ['country_code' => 'AU', 'name' => 'GST', 'rate' => 0.1000, 'type' => 'gst', 'is_eu' => false, 'description' => 'Australia GST'],
            ['country_code' => 'NZ', 'name' => 'GST', 'rate' => 0.1500, 'type' => 'gst', 'is_eu' => false, 'description' => 'New Zealand GST'],
            ['country_code' => 'SG', 'name' => 'GST', 'rate' => 0.0900, 'type' => 'gst', 'is_eu' => false, 'description' => 'Singapore GST'],
            ['country_code' => 'IN', 'name' => 'GST', 'rate' => 0.1800, 'type' => 'gst', 'is_eu' => false, 'description' => 'India GST Standard'],
            ['country_code' => 'MY', 'name' => 'SST', 'rate' => 0.0800, 'type' => 'gst', 'is_eu' => false, 'description' => 'Malaysia SST'],
            ['country_code' => 'TH', 'name' => 'VAT', 'rate' => 0.0700, 'type' => 'vat', 'is_eu' => false, 'description' => 'Thailand VAT'],
            ['country_code' => 'VN', 'name' => 'VAT', 'rate' => 0.1000, 'type' => 'vat', 'is_eu' => false, 'description' => 'Vietnam VAT'],
            ['country_code' => 'ID', 'name' => 'PPN', 'rate' => 0.1100, 'type' => 'vat', 'is_eu' => false, 'description' => 'Indonesia PPN'],
            ['country_code' => 'PH', 'name' => 'VAT', 'rate' => 0.1200, 'type' => 'vat', 'is_eu' => false, 'description' => 'Philippines VAT'],
            ['country_code' => 'JP', 'name' => 'Consumption Tax', 'rate' => 0.1000, 'type' => 'gst', 'is_eu' => false, 'description' => 'Japan Consumption Tax'],
            ['country_code' => 'KR', 'name' => 'VAT', 'rate' => 0.1000, 'type' => 'vat', 'is_eu' => false, 'description' => 'South Korea VAT'],
            ['country_code' => 'CN', 'name' => 'VAT', 'rate' => 0.1300, 'type' => 'vat', 'is_eu' => false, 'description' => 'China VAT Standard'],

            // 北美 Sales Tax
            ['country_code' => 'US', 'region_code' => 'CA', 'name' => 'Sales Tax', 'rate' => 0.0725, 'type' => 'sales_tax', 'is_eu' => false, 'description' => 'California Sales Tax'],
            ['country_code' => 'US', 'region_code' => 'TX', 'name' => 'Sales Tax', 'rate' => 0.0825, 'type' => 'sales_tax', 'is_eu' => false, 'description' => 'Texas Sales Tax'],
            ['country_code' => 'US', 'region_code' => 'NY', 'name' => 'Sales Tax', 'rate' => 0.0888, 'type' => 'sales_tax', 'is_eu' => false, 'description' => 'New York Sales Tax'],
            ['country_code' => 'US', 'region_code' => 'FL', 'name' => 'Sales Tax', 'rate' => 0.0700, 'type' => 'sales_tax', 'is_eu' => false, 'description' => 'Florida Sales Tax'],
            ['country_code' => 'US', 'region_code' => 'WA', 'name' => 'Sales Tax', 'rate' => 0.1010, 'type' => 'sales_tax', 'is_eu' => false, 'description' => 'Washington Sales Tax'],
            ['country_code' => 'US', 'region_code' => 'IL', 'name' => 'Sales Tax', 'rate' => 0.0875, 'type' => 'sales_tax', 'is_eu' => false, 'description' => 'Illinois Sales Tax'],
            ['country_code' => 'CA', 'name' => 'GST/HST', 'rate' => 0.0500, 'type' => 'gst', 'is_eu' => false, 'description' => 'Canada Federal GST'],
            ['country_code' => 'CA', 'region_code' => 'ON', 'name' => 'HST', 'rate' => 0.1300, 'type' => 'gst', 'is_eu' => false, 'description' => 'Ontario HST'],
            ['country_code' => 'CA', 'region_code' => 'BC', 'name' => 'PST', 'rate' => 0.0700, 'type' => 'sales_tax', 'is_eu' => false, 'description' => 'BC PST'],
            ['country_code' => 'CA', 'region_code' => 'QC', 'name' => 'QST', 'rate' => 0.0998, 'type' => 'sales_tax', 'is_eu' => false, 'description' => 'Quebec QST'],

            // 南美
            ['country_code' => 'BR', 'name' => 'ICMS', 'rate' => 0.1800, 'type' => 'vat', 'is_eu' => false, 'description' => 'Brazil ICMS (avg)'],

            // 中东
            ['country_code' => 'AE', 'name' => 'VAT', 'rate' => 0.0500, 'type' => 'vat', 'is_eu' => false, 'description' => 'UAE VAT'],
            ['country_code' => 'SA', 'name' => 'VAT', 'rate' => 0.1500, 'type' => 'vat', 'is_eu' => false, 'description' => 'Saudi Arabia VAT'],

            // 非洲
            ['country_code' => 'ZA', 'name' => 'VAT', 'rate' => 0.1500, 'type' => 'vat', 'is_eu' => false, 'description' => 'South Africa VAT'],
            ['country_code' => 'NG', 'name' => 'VAT', 'rate' => 0.0750, 'type' => 'vat', 'is_eu' => false, 'description' => 'Nigeria VAT'],

            // 欧洲非 EU
            ['country_code' => 'GB', 'name' => 'VAT', 'rate' => 0.2000, 'type' => 'vat', 'is_eu' => false, 'description' => 'UK VAT'],
            ['country_code' => 'CH', 'name' => 'VAT', 'rate' => 0.0810, 'type' => 'vat', 'is_eu' => false, 'description' => 'Switzerland VAT'],
            ['country_code' => 'NO', 'name' => 'VAT', 'rate' => 0.2500, 'type' => 'vat', 'is_eu' => false, 'description' => 'Norway VAT'],
        ];

        $all = array_merge($euCountries, $nonEuCountries);

        foreach ($all as $t) {
            TaxRate::firstOrCreate(
                [
                    'country_code' => $t['country_code'],
                    'region_code' => $t['region_code'] ?? null,
                    'type' => $t['type'],
                    'category' => 'standard',
                ],
                array_merge($t, [
                    'category' => 'standard',
                    'is_active' => true,
                    'effective_from' => now()->subYear(),
                ])
            );
        }
    }
}
