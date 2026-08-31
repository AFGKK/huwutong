<?php

namespace App\Services\Accounting;

/**
 * 用友 集成
 * 
 * 支持用友 U8+ / 畅捷通 T+ 接口
 * 凭证 → 总账接口
 * 发票 → 销售模块
 * 客户 → 客商档案
 */
class YonyouService extends BaseAccountingService
{
    protected string $provider = 'yonyou';

    /**
     * 获取API端点
     */
    protected function apiUrl(string $path): string
    {
        $base = $this->integration->api_endpoint;
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    /**
     * 用友 API 会话Token
     */
    protected function login(): ?string
    {
        try {
            $client = $this->httpClient();
            $response = $client->post($this->apiUrl('/api/login'), [
                'json' => [
                    'username' => $this->integration->username,
                    'password' => $this->integration->password,
                    'account_set_id' => $this->integration->company_id,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['token'] ?? $data['sessionid'] ?? null;
        } catch (\Exception $e) {
            $this->logError('Yonyou login failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function apiClient()
    {
        $token = $this->login();
        return $this->httpClient([
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function connect(array $credentials = []): bool
    {
        return $this->login() !== null;
    }

    /**
     * 推送 → 用友销售发票（销售专用发票）
     */
    public function pushInvoice(array $data): array
    {
        try {
            $client = $this->apiClient();

            $yyInvoice = [
                'code' => $data['invoice_no'] ?? '',
                'date' => $data['issue_date'] ?? now()->format('Y-m-d'),
                'customer_name' => $data['customer_name'] ?? '散客',
                'maker' => '系统同步',
                'amount' => $data['amount'],
                'tax_amount' => $data['tax_amount'] ?? 0,
                'total_amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'CNY',
                'remark' => "License Invoice #{$data['invoice_no']}",
                'details' => [
                    [
                        'inventory_name' => 'License授权服务',
                        'quantity' => 1,
                        'price' => $data['subtotal'] ?? $data['amount'],
                        'amount' => $data['subtotal'] ?? $data['amount'],
                        'tax_rate' => $data['tax_rate'] ?? 0,
                    ],
                ],
            ];

            $response = $client->post($this->apiUrl('/api/arap/saleinvoice'), ['json' => $yyInvoice]);
            $result = json_decode($response->getBody()->getContents(), true);

            if (($result['id'] ?? $result['code'] ?? false)) {
                return [
                    'success' => true,
                    'remote_id' => $result['id'] ?? '',
                    'remote_number' => $result['code'] ?? $data['invoice_no'],
                ];
            }

            return ['success' => false, 'error' => $result['message'] ?? __('app.common.yonyou_api_error')];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function pushPayment(array $data): array
    {
        try {
            $client = $this->apiClient();
            $response = $client->post($this->apiUrl('/api/arap/receipt'), [
                'json' => [
                    'code' => $data['reference'] ?? '',
                    'date' => $data['payment_date'] ?? now()->format('Y-m-d'),
                    'customer_name' => $data['customer_name'] ?? '散客',
                    'amount' => $data['amount'],
                    'payment_method' => $data['payment_method'] ?? '银行转账',
                    'remark' => 'License收款同步',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'remote_id' => $result['id'] ?? '',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function pushRefund(array $data): array
    {
        return $this->pushPayment(array_merge($data, [
            'amount' => -abs($data['amount']),
            'remark' => '退款同步',
        ]));
    }

    public function pushCustomer(array $data): array
    {
        try {
            $client = $this->apiClient();
            $response = $client->post($this->apiUrl('/api/arap/customer'), [
                'json' => [
                    'code' => 'CUS-' . ($data['id'] ?? ''),
                    'name' => $data['name'] ?? 'Unknown',
                    'contact' => $data['email'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'tax_id' => $data['tax_id'] ?? '',
                    'address' => $data['address'] ?? '',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'remote_id' => $result['id'] ?? '',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function checkConnection(): array
    {
        $token = $this->login();
        if (!$token) {
            return ['connected' => false, 'error' => 'Login failed'];
        }

        try {
            $client = $this->apiClient();
            $response = $client->get($this->apiUrl('/api/account-set'));
            $result = json_decode($response->getBody()->getContents(), true);

            return [
                'connected' => true,
                'account_set' => $result['name'] ?? $this->integration->company_id,
            ];
        } catch (\Exception $e) {
            return ['connected' => true]; // login worked, API check may fail
        }
    }
}
