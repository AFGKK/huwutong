<?php

namespace App\Services\Accounting;

/**
 * 金蝶 集成
 * 
 * 支持金蝶 K/3 WISE / 金蝶云·星空
 * 凭证 → 总账
 * 发票 → 销售发票
 * 客户 → 客户档案
 */
class KingdeeService extends BaseAccountingService
{
    protected string $provider = 'kingdee';

    /**
     * 金蝶 API 登录
     */
    protected function login(): ?string
    {
        try {
            $client = $this->httpClient();
            $response = $client->post($this->url('/api/login'), [
                'json' => [
                    'acctID' => $this->integration->company_id,
                    'username' => $this->integration->username,
                    'password' => $this->integration->password,
                    'lang' => 2052, // 简体中文
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['context']['token'] ?? $data['token'] ?? null;
        } catch (\Exception $e) {
            $this->logError('Kingdee login failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function url(string $path): string
    {
        $base = $this->integration->api_endpoint;
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    /**
     * 金蝶 API 调用（K/3 Cloud 格式）
     */
    protected function apiCall(string $formId, string $op, array $data): array
    {
        $token = $this->login();
        if (!$token) {
            return ['success' => false, 'error' => 'Login failed'];
        }

        try {
            $client = $this->httpClient([
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            $response = $client->post($this->url('/api/k3cloud/kingdee'), [
                'json' => [
                    'formid' => $formId,
                    'op' => $op,
                    'data' => $data,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (($result['Result']['Id'] ?? $result['Result']['Number'] ?? false)) {
                return [
                    'success' => true,
                    'remote_id' => (string) ($result['Result']['Id'] ?? ''),
                    'remote_number' => $result['Result']['Number'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error' => $result['Result']['Errors'][0]['Message'] ?? '金蝶API错误',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function connect(array $credentials = []): bool
    {
        return $this->login() !== null;
    }

    /**
     * 推送 → 金蝶销售发票
     */
    public function pushInvoice(array $data): array
    {
        $result = $this->apiCall('SAL_SaleInvoice', 'Save', [
            'Model' => [
                'FBillNo' => $data['invoice_no'] ?? '',
                'FDate' => $data['issue_date'] ?? now()->format('Y-m-d'),
                'FSaleDept' => ['FNumber' => 'BM000001'],
                'FCustomerID' => ['FNumber' => 'CUS' . ($data['customer_name'] ?? '')],
                'FCorrespondOrgId' => ['FNumber' => '100'],
                'FEntity' => [
                    [
                        'FMaterialID' => ['FNumber' => 'LICENSE_SVC'],
                        'FQty' => 1,
                        'FPrice' => $data['subtotal'] ?? $data['amount'],
                        'FAmount' => $data['subtotal'] ?? $data['amount'],
                        'FTaxCombination' => ['FNumber' => 'TAX01'],
                        'FEntryNote' => $data['description'] ?? '',
                    ],
                ],
            ],
        ]);

        return $result;
    }

    public function pushPayment(array $data): array
    {
        return $this->apiCall('AR_RECEIVEBILL', 'Save', [
            'Model' => [
                'FBillNo' => $data['reference'] ?? '',
                'FDATE' => $data['payment_date'] ?? now()->format('Y-m-d'),
                'FCUSTOMERID' => ['FNumber' => 'CUS' . ($data['customer_name'] ?? '')],
                'FRECEIVEAMOUNTFOR' => $data['amount'],
                'FREMARK' => $data['description'] ?? 'License收款',
            ],
        ]);
    }

    public function pushRefund(array $data): array
    {
        return $this->apiCall('AR_RECEIVEBILL', 'Save', [
            'Model' => [
                'FBillNo' => 'REF-' . ($data['reference'] ?? ''),
                'FDATE' => $data['refund_date'] ?? now()->format('Y-m-d'),
                'FCUSTOMERID' => ['FNumber' => 'CUS' . ($data['customer_name'] ?? '')],
                'FRECEIVEAMOUNTFOR' => -abs($data['amount']),
                'FREMARK' => '退款: ' . ($data['reason'] ?? ''),
            ],
        ]);
    }

    public function pushCustomer(array $data): array
    {
        return $this->apiCall('BD_Customer', 'Save', [
            'Model' => [
                'FNumber' => 'CUS' . ($data['id'] ?? ''),
                'FName' => $data['name'] ?? 'Unknown',
                'FContact' => $data['email'] ?? '',
                'FTel' => $data['phone'] ?? '',
                'FTaxRegisterCode' => $data['tax_id'] ?? '',
                'FAddress' => $data['address'] ?? '',
            ],
        ]);
    }

    public function checkConnection(): array
    {
        $token = $this->login();
        if (!$token) {
            return ['connected' => false, 'error' => 'Login failed'];
        }
        return ['connected' => true, 'message' => 'Connected successfully'];
    }
}
