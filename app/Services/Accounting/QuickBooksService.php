<?php

namespace App\Services\Accounting;

use App\Models\AccountingIntegration;
use Illuminate\Support\Facades\Http;

/**
 * QuickBooks Online 集成
 * 
 * 使用 OAuth 2.0 + QuickBooks Online API v3
 * 发票 → SalesReceipt / Invoice
 * 收款 → Payment
 * 客户 → Customer
 */
class QuickBooksService extends BaseAccountingService
{
    protected string $provider = 'quickbooks';
    private ?string $baseUrl = null;

    public function __construct(AccountingIntegration $integration)
    {
        parent::__construct($integration);
        $sandbox = $integration->environment === 'sandbox';
        $this->baseUrl = $sandbox
            ? 'https://sandbox-quickbooks.api.intuit.com'
            : 'https://quickbooks.api.intuit.com';
    }

    /**
     * OAuth 2.0 授权流程 — 生成授权URL
     */
    public function getAuthorizationUrl(): string
    {
        $clientId = $this->integration->client_id;
        $redirectUri = config('accounting.quickbooks.redirect_uri');
        $state = csrf_token();

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'com.intuit.quickbooks.accounting',
            'state' => $state,
        ]);

        $base = $this->integration->environment === 'sandbox'
            ? 'https://appcenter.intuit.com/connect/oauth2'
            : 'https://appcenter.intuit.com/connect/oauth2';

        return "{$base}?{$params}";
    }

    /**
     * 用授权码换取 Access Token
     */
    public function handleCallback(string $code): bool
    {
        $clientId = $this->integration->client_id;
        $clientSecret = $this->integration->client_secret;
        $redirectUri = config('accounting.quickbooks.redirect_uri');

        $client = $this->httpClient();
        $response = $client->post('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', [
            'auth' => [$clientId, $clientSecret],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!empty($data['access_token'])) {
            $this->integration->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $this->integration->refresh_token,
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                'is_active' => true,
                'company_id' => $data['realmId'] ?? $this->integration->company_id,
            ]);
            return true;
        }

        return false;
    }

    /**
     * 刷新 Access Token
     */
    protected function refreshToken(): bool
    {
        $clientId = $this->integration->client_id;
        $clientSecret = $this->integration->client_secret;
        $refreshToken = $this->integration->refresh_token;

        if (!$refreshToken) return false;

        try {
            $client = $this->httpClient();
            $response = $client->post('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', [
                'auth' => [$clientId, $clientSecret],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!empty($data['access_token'])) {
                $this->integration->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                    'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                ]);
                return true;
            }
        } catch (\Exception $e) {
            $this->logError('Token refresh failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * 确保 Token 有效
     */
    protected function ensureToken(): bool
    {
        if ($this->integration->token_expires_at && $this->integration->token_expires_at->isPast()) {
            return $this->refreshToken();
        }
        return !empty($this->integration->access_token);
    }

    /**
     * QuickBooks API 客户端
     */
    protected function apiClient()
    {
        $this->ensureToken();

        $companyId = $this->integration->company_id;
        $baseUrl = "{$this->baseUrl}/{$this->getApiVersion()}/company/{$companyId}";

        return $this->httpClient([
            'base_uri' => $baseUrl . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . decrypt($this->integration->access_token),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    protected function getApiVersion(): string
    {
        return config('accounting.quickbooks.api_version', 'v3');
    }

    public function connect(array $credentials = []): bool
    {
        return $this->ensureToken();
    }

    /**
     * 推送发票 → QuickBooks Invoice / SalesReceipt
     */
    public function pushInvoice(array $data): array
    {
        try {
            $client = $this->apiClient();

            $qbInvoice = [
                'Line' => [
                    [
                        'DetailType' => 'SalesItemLineDetail',
                        'Amount' => $data['subtotal'] ?? $data['amount'],
                        'SalesItemLineDetail' => [
                            'ItemRef' => ['name' => 'Services'],
                            'UnitPrice' => $data['subtotal'] ?? $data['amount'],
                            'Qty' => 1,
                        ],
                    ],
                ],
                'CustomerRef' => ['name' => $data['customer_name'] ?? 'Customer'],
                'DocNumber' => $data['invoice_no'] ?? '',
                'TxnDate' => $data['issue_date'] ?? now()->format('Y-m-d'),
                'DueDate' => $data['due_date'] ?? $data['issue_date'] ?? now()->format('Y-m-d'),
                'EmailStatus' => 'NotSet',
            ];

            // 含税
            if (!empty($data['tax_amount']) && (float)$data['tax_amount'] > 0) {
                $qbInvoice['Line'][0]['SalesItemLineDetail']['TaxCodeRef'] = ['name' => 'TAX'];
                $qbInvoice['TxnTaxDetail'] = [
                    'TotalTax' => (float) $data['tax_amount'],
                ];
            }

            $endpoint = ($data['status'] === 'paid') ? 'salesreceipt' : 'invoice';
            $response = $client->post($endpoint, ['json' => $qbInvoice]);
            $result = json_decode($response->getBody()->getContents(), true);

            if (($result['Invoice']['Id'] ?? $result['SalesReceipt']['Id'] ?? false)) {
                $id = $result['Invoice']['Id'] ?? $result['SalesReceipt']['Id'];
                $docNum = $result['Invoice']['DocNumber'] ?? $result['SalesReceipt']['DocNumber'] ?? $id;
                return [
                    'success' => true,
                    'remote_id' => (string) $id,
                    'remote_number' => (string) $docNum,
                ];
            }

            $error = $result['Fault']['Error'][0]['Message'] ?? 'QB API error';
            return ['success' => false, 'error' => $error];
        } catch (\Exception $e) {
            $this->logError('pushInvoice failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 推送收款 → QuickBooks Payment
     */
    public function pushPayment(array $data): array
    {
        try {
            $client = $this->apiClient();

            $qbPayment = [
                'TotalAmt' => $data['amount'],
                'CustomerRef' => ['name' => $data['customer_name'] ?? 'Customer'],
                'TxnDate' => $data['payment_date'] ?? now()->format('Y-m-d'),
                'Line' => [
                    [
                        'Amount' => $data['amount'],
                        'LinkedTxn' => [
                            [
                                'TxnId' => $data['invoice_remote_id'] ?? '',
                                'TxnType' => 'Invoice',
                            ],
                        ],
                    ],
                ],
            ];

            $response = $client->post('payment', ['json' => $qbPayment]);
            $result = json_decode($response->getBody()->getContents(), true);

            if ($result['Payment']['Id'] ?? false) {
                return [
                    'success' => true,
                    'remote_id' => (string) $result['Payment']['Id'],
                ];
            }

            return ['success' => false, 'error' => $result['Fault']['Error'][0]['Message'] ?? 'QB API error'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 推送退款 → QuickBooks CreditMemo / RefundReceipt
     */
    public function pushRefund(array $data): array
    {
        try {
            $client = $this->apiClient();

            $qbRefund = [
                'TotalAmt' => $data['amount'],
                'CustomerRef' => ['name' => $data['customer_name'] ?? 'Customer'],
                'TxnDate' => $data['refund_date'] ?? now()->format('Y-m-d'),
                'Line' => [
                    [
                        'Amount' => $data['amount'],
                        'DetailType' => 'SalesItemLineDetail',
                        'SalesItemLineDetail' => [
                            'ItemRef' => ['name' => 'Services'],
                            'UnitPrice' => $data['amount'],
                            'Qty' => -1,
                        ],
                    ],
                ],
            ];

            $response = $client->post('creditmemo', ['json' => $qbRefund]);
            $result = json_decode($response->getBody()->getContents(), true);

            if ($result['CreditMemo']['Id'] ?? false) {
                return [
                    'success' => true,
                    'remote_id' => (string) $result['CreditMemo']['Id'],
                ];
            }

            return ['success' => false, 'error' => $result['Fault']['Error'][0]['Message'] ?? 'QB API error'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 推送客户 → QuickBooks Customer
     */
    public function pushCustomer(array $data): array
    {
        try {
            $client = $this->apiClient();

            $qbCustomer = [
                'DisplayName' => $data['name'] ?? 'Unknown',
                'PrimaryEmailAddr' => ['Address' => $data['email'] ?? ''],
                'PrimaryPhone' => ['FreeFormNumber' => $data['phone'] ?? ''],
            ];

            if (!empty($data['address'])) {
                $qbCustomer['BillAddr'] = ['Line1' => $data['address']];
            }

            $response = $client->post('customer', ['json' => $qbCustomer]);
            $result = json_decode($response->getBody()->getContents(), true);

            if ($result['Customer']['Id'] ?? false) {
                return [
                    'success' => true,
                    'remote_id' => (string) $result['Customer']['Id'],
                ];
            }

            return ['success' => false, 'error' => $result['Fault']['Error'][0]['Message'] ?? 'QB API error'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 检查连接
     */
    public function checkConnection(): array
    {
        if (!$this->ensureToken()) {
            return ['connected' => false, 'error' => 'Token expired or invalid'];
        }

        try {
            $client = $this->apiClient();
            $response = $client->get('companyinfo/' . $this->integration->company_id);
            $result = json_decode($response->getBody()->getContents(), true);

            return [
                'connected' => true,
                'company_name' => $result['CompanyInfo']['CompanyName'] ?? '',
                'country' => $result['CompanyInfo']['Country'] ?? '',
            ];
        } catch (\Exception $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }
}
