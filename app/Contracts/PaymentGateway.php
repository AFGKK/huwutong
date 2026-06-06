<?php

namespace App\Contracts;

use App\Models\Invoice;

interface PaymentGateway
{
    /**
     * 发起支付
     *
     * @return array{success: bool, transaction_id?: string, redirect_url?: string, error?: string}
     */
    public function charge(Invoice $invoice, array $options = []): array;

    /**
     * 退款
     *
     * @return array{success: bool, refund_id?: string, error?: string}
     */
    public function refund(Invoice $invoice, array $options = []): array;

    /**
     * 查询支付状态
     */
    public function query(string $transactionId): array;

    /**
     * 验证回调签名
     */
    public function verifyCallback(array $payload): bool;

    /**
     * 网关名称
     */
    public function name(): string;
}
