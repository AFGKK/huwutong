<?php

namespace App\Services\Accounting;

/**
 * Xero 集成
 * 
 * OAuth 2.0 + Xero Accounting API
 * 发票 → Invoices
 * 收款 → Payments
 * 客户 → Contacts
 */
class XeroService extends BaseAccountingService
{
    protected string $provider = 'xero';
    private ?string $accessToken = null;
    private ?string $tenantId = null;

    /**
     * 获取授权URL
     */
    public function getAuthorizationUrl(): string
    {
        $clientId = $this->integration->client_id;
        $redirectUri = config('accounting.xero.redirect_uri');
        $state = csrf_token();

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email accounting.transactions accounting.contacts offline_access',
            'state' => $state,
        ]);

        return "https://login.xero.com/identity/connect/authorize?{$params}";
    }

    /**
     * 处理OAuth回调
     */
    public function handleCallback(string $code): bool
    {
        $clientId = $this->integration->client_id;
        $clientSecret = $this->integration->client_secret;
        $redirectUri = config('accounting.xero.redirect_uri');

        try {
            $client = $this->httpClient();
            $response = $client->post('https://identity.xero.com/connect/token', [
                'auth' => [$clientId, $clientSecret],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!empty($data['access_token'])) {
                // 获取 Xero Tenants
                $tenants = $this->getXeroTenants($data['access_token']);
                $firstTenant = $tenants[0]['tenantId'] ?? '';

                $this->integration->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $this->integration->refresh_token,
                    'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                    'is_active' => true,
                    'company_id' => $firstTenant,
                ]);
                return true;
            }
        } catch (\Exception $e) {
            $this->logError('Xero callback failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    protected function getXeroTenants(string $token): array
    {
        try {
            $client = $this->httpClient();
            $response = $client->get('https://api.xero.com/connections', [
                'headers' => ['Authorization' => "Bearer {$token}"],
            ]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function refreshToken(): bool
    {
        $clientId = $this->integration->client_id;
        $clientSecret = $this->integration->client_secret;
        $refreshToken = $this->integration->refresh_token;

        if (!$refreshToken) return false;

        try {
            $client = $this->httpClient();
            $response = $client->post('https://identity.xero.com/connect/token', [
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
            $this->logError('Xero token refresh failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    protected function ensureToken(): bool
    {
        if ($this->integration->token_expires_at && $this->integration->token_expires_at->isPast()) {
            return $this->refreshToken();
        }
        $this->accessToken = $this->integration->access_token;
        $this->tenantId = $this->integration->company_id;
        return !empty($this->accessToken);
    }

    protected function apiClient()
    {
        $this->ensureToken();
        return $this->httpClient([
            'base_uri' => 'https://api.xero.com/api.xro/2.0/',
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Xero-Tenant-Id' => $this->tenantId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function connect(array $credentials = []): bool
    {
        return $this->ensureToken();
    }

    /**
     * 推送发票 → Xero Invoice
     */
    public function pushInvoice(array $data): array
    {
        try {
            $client = $this->apiClient();

            $lineItems = [[
                'Description' => $data['description'] ?? 'License Sale',
                'Quantity' => 1.0,
                'UnitAmount' => $data['subtotal'] ?? $data['amount'],
                'AccountCode' => config('accounting.defaults.invoice_account_code', '200'),
            ]];

            if (!empty($data['tax_amount']) && (float)$data['tax_amount'] > 0) {
                $lineItems[0]['TaxAmount'] = (float) $data['tax_amount'];
                $lineItems[0]['TaxType'] = 'OUTPUT';
            } else {
                $lineItems[0]['TaxType'] = 'NONE';
            }

            $xeroInvoice = [
                'Type' => $data['status'] === 'paid' ? 'ACCREC' : 'ACCREC',
                'InvoiceNumber' => $data['invoice_no'] ?? '',
                'Contact' => ['Name' => $data['customer_name'] ?? 'Customer'],
                'Date' => $data['issue_date'] ?? now()->format('Y-m-d'),
                'DueDate' => $data['due_date'] ?? $data['issue_date'] ?? now()->format('Y-m-d'),
                'LineItems' => $lineItems,
                'Status' => $data['status'] === 'paid' ? 'AUTHORISED' : 'DRAFT',
            ];

            if (!empty($data['customer_email'])) {
                $xeroInvoice['Contact']['EmailAddress'] = $data['customer_email'];
            }

            $response = $client->post('Invoices', ['json' => ['Invoices' => [$xeroInvoice]]]);
            $result = json_decode($response->getBody()->getContents(), true);

            $invoice = $result['Invoices'][0] ?? [];
            if ($invoice['InvoiceID'] ?? false) {
                return [
                    'success' => true,
                    'remote_id' => $invoice['InvoiceID'],
                    'remote_number' => $invoice['InvoiceNumber'] ?? $invoice['InvoiceID'],
                ];
            }

            $errors = $result['Elements'][0]['ValidationErrors'] ?? [];
            $errorMsg = !empty($errors) ? $errors[0]['Message'] : 'Xero API error';
            return ['success' => false, 'error' => $errorMsg];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 推送收款 → Xero Payment
     */
    public function pushPayment(array $data): array
    {
        try {
            $client = $this->apiClient();

            $xeroPayment = [
                'Invoice' => ['InvoiceID' => $data['invoice_remote_id'] ?? ''],
                'Account' => ['Code' => config('accounting.defaults.receivable_account_code', '090')],
                'Date' => $data['payment_date'] ?? now()->format('Y-m-d'),
                'Amount' => $data['amount'],
                'Reference' => $data['reference'] ?? '',
            ];

            $response = $client->post('Payments', ['json' => ['Payments' => [$xeroPayment]]]);
            $result = json_decode($response->getBody()->getContents(), true);

            $payment = $result['Payments'][0] ?? [];
            if ($payment['PaymentID'] ?? false) {
                return ['success' => true, 'remote_id' => $payment['PaymentID']];
            }

            return ['success' => false, 'error' => 'Xero payment failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 推送退款 → Xero CreditNote
     */
    public function pushRefund(array $data): array
    {
        try {
            $client = $this->apiClient();

            $xeroCreditNote = [
                'Type' => 'ACCRECCREDIT',
                'Contact' => ['Name' => $data['customer_name'] ?? 'Customer'],
                'Date' => $data['refund_date'] ?? now()->format('Y-m-d'),
                'LineItems' => [[
                    'Description' => $data['reason'] ?? 'Refund',
                    'Quantity' => -1,
                    'UnitAmount' => $data['amount'],
                    'AccountCode' => config('accounting.defaults.invoice_account_code', '200'),
                ]],
                'Status' => 'AUTHORISED',
            ];

            $response = $client->post('CreditNotes', ['json' => ['CreditNotes' => [$xeroCreditNote]]]);
            $result = json_decode($response->getBody()->getContents(), true);

            $cn = $result['CreditNotes'][0] ?? [];
            if ($cn['CreditNoteID'] ?? false) {
                return ['success' => true, 'remote_id' => $cn['CreditNoteID']];
            }

            return ['success' => false, 'error' => 'Xero credit note failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 推送客户 → Xero Contact
     */
    public function pushCustomer(array $data): array
    {
        try {
            $client = $this->apiClient();

            $xeroContact = [
                'Name' => $data['name'] ?? 'Unknown',
                'EmailAddress' => $data['email'] ?? '',
            ];

            if (!empty($data['phone'])) {
                $xeroContact['Phones'] = [['PhoneType' => 'DEFAULT', 'PhoneNumber' => $data['phone']]];
            }

            $response = $client->post('Contacts', ['json' => ['Contacts' => [$xeroContact]]]);
            $result = json_decode($response->getBody()->getContents(), true);

            $contact = $result['Contacts'][0] ?? [];
            if ($contact['ContactID'] ?? false) {
                return ['success' => true, 'remote_id' => $contact['ContactID']];
            }

            return ['success' => false, 'error' => 'Xero contact failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function checkConnection(): array
    {
        if (!$this->ensureToken()) {
            return ['connected' => false, 'error' => 'Not connected'];
        }

        try {
            $client = $this->apiClient();
            $response = $client->get('Organisation');
            $org = json_decode($response->getBody()->getContents(), true)['Organisations'][0] ?? [];

            return [
                'connected' => true,
                'company_name' => $org['Name'] ?? '',
                'country' => $org['CountryCode'] ?? '',
                'currency' => $org['BaseCurrency'] ?? '',
            ];
        } catch (\Exception $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }
}
