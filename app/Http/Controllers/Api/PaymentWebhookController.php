<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookLog;
use App\Services\Payment\AlipayPaymentGateway;
use App\Services\Payment\StripePaymentGateway;
use App\Services\Payment\YipayPaymentGateway;
use App\Services\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * 支付网关 Webhook 处理器
 *
 * 处理 Stripe / Alipay 等异步支付回调。
 * 这些路由必须排除 CSRF 保护，且不经过 auth:sanctum 中间件。
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentManager $paymentManager,
    ) {}

    /**
     * Stripe Webhook 入口
     *
     * 处理：payment_intent.succeeded / payment_intent.payment_failed / charge.refunded / invoice.paid
     */
    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        // 验证签名
        $verified = $this->verifyStripeSignature($payload, $sigHeader);
        if (!$verified) {
            Log::warning('Stripe webhook: signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true);
        if (!$event || !isset($event['type'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $eventType = $event['type'];
        $eventId = $event['id'] ?? null;

        Log::info('Stripe webhook received', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        // 幂等性检查：避免重复处理
        if ($eventId) {
            $existing = PaymentWebhookLog::where('event_id', $eventId)->first();
            if ($existing && $existing->status === 'completed') {
                return response()->json(['status' => 'duplicate', 'message' => 'Already processed']);
            }
        }

        // 记录日志
        $log = PaymentWebhookLog::create([
            'gateway' => 'stripe',
            'event_type' => $eventType,
            'event_id' => $eventId,
            'status' => 'processing',
            'payload' => $event,
        ]);

        try {
            $this->handleStripeEvent($event, $log);
            $log->markCompleted();
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('Stripe webhook processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * 支付宝 Webhook 入口
     */
    public function alipay(Request $request): Response|JsonResponse
    {
        $payload = $request->all();

        // 验证签名
        $verified = app(AlipayPaymentGateway::class)->verifyCallback($payload);
        if (! $verified) {
            Log::warning('Alipay webhook: signature verification failed');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $payload['notify_type'] ?? 'unknown';
        $eventId = $payload['notify_id'] ?? null;

        Log::info('Alipay webhook received', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        // 幂等性检查
        if ($eventId) {
            $existing = PaymentWebhookLog::where('event_id', $eventId)->first();
            if ($existing && $existing->status === 'completed') {
                return response()->json(['status' => 'duplicate', 'message' => 'Already processed']);
            }
        }

        $log = PaymentWebhookLog::create([
            'gateway' => 'alipay',
            'event_type' => $eventType,
            'event_id' => $eventId,
            'status' => 'processing',
            'payload' => $payload,
        ]);

        try {
            $this->handleAlipayEvent($payload, $log);
            $log->markCompleted();
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('Alipay webhook processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            // 支付宝期望收到失败也会重试
            return response()->json(['error' => 'Processing failed'], 500);
        }

        // 支付宝异步通知要求响应纯文本 success
        return response('success', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * 易支付 Webhook 异步回调入口
     *
     * 易支付回调参数:
     *   - pid: 商户ID
     *   - trade_no: 易支付订单号
     *   - out_trade_no: 商户订单号
     *   - type: 支付方式
     *   - name: 商品名称
     *   - money: 金额
     *   - trade_status: 交易状态 (TRADE_SUCCESS)
     *   - sign: 签名
     *   - sign_type: 签名类型
     */
    public function yipay(Request $request): Response|JsonResponse
    {
        $payload = $request->all();

        // 验证签名
        $verified = app(YipayPaymentGateway::class)->verifyCallback($payload);
        if (!$verified) {
            Log::warning('Yipay webhook: signature verification failed');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 易支付回调无唯一 event_id，使用 trade_no 幂等
        $tradeNo = $payload['trade_no'] ?? null;
        $outTradeNo = $payload['out_trade_no'] ?? '';
        $tradeStatus = $payload['trade_status'] ?? '';

        Log::info('Yipay webhook received', [
            'trade_no' => $tradeNo,
            'out_trade_no' => $outTradeNo,
            'trade_status' => $tradeStatus,
        ]);

        // 幂等性检查
        if ($tradeNo) {
            $existing = PaymentWebhookLog::where('event_id', $tradeNo)->first();
            if ($existing && $existing->status === 'completed') {
                return response('success', 200)->header('Content-Type', 'text/plain');
            }
        }

        $log = PaymentWebhookLog::create([
            'gateway' => 'yipay',
            'event_type' => $tradeStatus,
            'event_id' => $tradeNo ?? 'yipay_' . uniqid(),
            'status' => 'processing',
            'payload' => $payload,
        ]);

        try {
            if ($tradeStatus === 'TRADE_SUCCESS') {
                $this->processYipaySuccess($outTradeNo, $tradeNo, $log);
            } else {
                Log::info("Yipay webhook: unhandled trade_status={$tradeStatus}");
            }
            $log->markCompleted();
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('Yipay webhook processing failed', [
                'trade_no' => $tradeNo,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }

        // 易支付异步通知要求响应纯文本 success
        return response('success', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Webhook 日志查看
     */
    public function logs(Request $request): JsonResponse
    {
        $query = PaymentWebhookLog::orderBy('created_at', 'desc');

        if ($gateway = $request->input('gateway')) {
            $query->where('gateway', $gateway);
        }
        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', 'like', "%{$eventType}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $logs]);
    }

    // ========================
    // 事件处理
    // ========================

    protected function handleStripeEvent(array $event, PaymentWebhookLog $log): void
    {
        $billingService = app(\App\Services\BillingService::class);

        match ($event['type']) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event, $log, $billingService),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event, $log, $billingService),
            'charge.refunded' => $this->handleChargeRefunded($event, $log, $billingService),
            'invoice.paid' => $this->handleInvoicePaid($event, $log, $billingService),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event, $log, $billingService),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event, $log, $billingService),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event, $log, $billingService),
            default => Log::info("Stripe webhook: unhandled event type {$event['type']}"),
        };
    }

    protected function handleAlipayEvent(array $payload, PaymentWebhookLog $log): void
    {
        $billingService = app(\App\Services\BillingService::class);

        $tradeStatus = $payload['trade_status'] ?? '';
        $outTradeNo = $payload['out_trade_no'] ?? '';
        $tradeNo = $payload['trade_no'] ?? '';

        match ($tradeStatus) {
            'TRADE_SUCCESS', 'TRADE_FINISHED' => $this->processAlipaySuccess($outTradeNo, $tradeNo, $log, $billingService),
            'TRADE_CLOSED' => $this->processAlipayClosed($outTradeNo, $log, $billingService),
            'WAIT_BUYER_PAY' => Log::info("Alipay: waiting for buyer payment, trade_no={$outTradeNo}"),
            default => Log::info("Alipay webhook: unhandled trade_status={$tradeStatus}"),
        };
    }

    // ========================
    // Stripe 事件处理器
    // ========================

    protected function handlePaymentIntentSucceeded(array $event, PaymentWebhookLog $log, $billingService): void
    {
        $intent = $event['data']['object'] ?? [];
        $metadata = $intent['metadata'] ?? [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (!$invoiceId) {
            Log::warning('Stripe webhook: payment_intent.succeeded missing invoice_id in metadata');
            return;
        }

        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            Log::warning("Stripe webhook: invoice #{$invoiceId} not found");
            return;
        }

        $chargeId = $intent['charges']['data'][0]['id'] ?? null;

        // 标记发票已支付
        $billingService->markInvoicePaid($invoice, [
            'transaction_id' => $intent['id'],
            'charge_id' => $chargeId,
            'payment_method' => 'stripe',
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    protected function handlePaymentFailed(array $event, PaymentWebhookLog $log, $billingService): void
    {
        $intent = $event['data']['object'] ?? [];
        $metadata = $intent['metadata'] ?? [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (!$invoiceId) {
            return;
        }

        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $lastError = $intent['last_payment_error']['message'] ?? 'Unknown error';
        $invoice->update([
            'status' => 'failed',
            'notes' => ($invoice->notes ? $invoice->notes . "\n" : '') . "Stripe payment failed: {$lastError}",
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    protected function handleChargeRefunded(array $event, PaymentWebhookLog $log, $billingService): void
    {
        $charge = $event['data']['object'] ?? [];
        $metadata = $charge['metadata'] ?? [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (!$invoiceId) {
            return;
        }

        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $billingService->markInvoiceRefunded($invoice, [
            'refund_id' => $charge['id'],
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    protected function handleInvoicePaid(array $event, PaymentWebhookLog $log, $billingService): void
    {
        $stripeInvoice = $event['data']['object'] ?? [];
        $subscriptionId = $stripeInvoice['subscription'] ?? null;

        if (!$subscriptionId) {
            return;
        }

        // 找到对应的本地订阅
        $subscription = \App\Models\Subscription::where('gateway_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            Log::info("Stripe webhook: subscription {$subscriptionId} not found locally, may be from another system");
            return;
        }

        // 续期订阅
        $billingService->renewSubscription($subscription);
        $log->update(['processable_type' => \App\Models\Subscription::class, 'processable_id' => $subscription->id]);
    }

    protected function handleInvoicePaymentFailed(array $event, PaymentWebhookLog $log, $billingService): void
    {
        $stripeInvoice = $event['data']['object'] ?? [];
        $subscriptionId = $stripeInvoice['subscription'] ?? null;

        if (!$subscriptionId) {
            return;
        }

        $subscription = \App\Models\Subscription::where('gateway_subscription_id', $subscriptionId)->first();
        if (!$subscription || !$subscription->is_active) {
            return;
        }

        // 进入宽限期
        $subscription->enterGracePeriod();
        $log->update(['processable_type' => \App\Models\Subscription::class, 'processable_id' => $subscription->id]);
    }

    protected function handleSubscriptionUpdated(array $event, PaymentWebhookLog $log, $billingService): void
    {
        $stripeSub = $event['data']['object'] ?? [];
        $subscriptionId = $stripeSub['id'] ?? null;

        if (!$subscriptionId) {
            return;
        }

        $subscription = \App\Models\Subscription::where('gateway_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        // 同步状态
        $status = $stripeSub['status'] ?? '';
        $localStatus = match ($status) {
            'active', 'trialing' => 'active',
            'past_due' => 'past_due',
            'canceled', 'incomplete_expired' => 'canceled',
            'unpaid' => 'suspended',
            default => $subscription->status,
        };

        if ($localStatus !== $subscription->status) {
            $subscription->update(['status' => $localStatus]);
        }

        $log->update(['processable_type' => \App\Models\Subscription::class, 'processable_id' => $subscription->id]);
    }

    protected function handleSubscriptionDeleted(array $event, PaymentWebhookLog $log, $billingService): void
    {
        $stripeSub = $event['data']['object'] ?? [];
        $subscriptionId = $stripeSub['id'] ?? null;

        if (!$subscriptionId) {
            return;
        }

        $subscription = \App\Models\Subscription::where('gateway_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->cancel();
        $log->update(['processable_type' => \App\Models\Subscription::class, 'processable_id' => $subscription->id]);
    }

    // ========================
    // 支付宝 事件处理器
    // ========================

    protected function processAlipaySuccess(string $outTradeNo, string $tradeNo, PaymentWebhookLog $log, $billingService): void
    {
        // out_trade_no 为本地 invoice_no
        $invoice = \App\Models\Invoice::where('invoice_no', $outTradeNo)->first();
        if (!$invoice) {
            Log::warning("Alipay webhook: invoice #{$outTradeNo} not found");
            return;
        }

        $billingService->markInvoicePaid($invoice, [
            'transaction_id' => $tradeNo,
            'payment_method' => 'alipay',
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    protected function processAlipayClosed(string $outTradeNo, PaymentWebhookLog $log, $billingService): void
    {
        $invoice = \App\Models\Invoice::where('invoice_no', $outTradeNo)->first();
        if (!$invoice) {
            return;
        }

        $invoice->update(['status' => 'cancelled']);
        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    /**
     * PayPal Webhook 入口
     *
     * 处理：CHECKOUT.ORDER.APPROVED / PAYMENT.CAPTURE.COMPLETED / PAYMENT.CAPTURE.REFUNDED
     */
    public function paypal(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $event = json_decode($payload, true);

        if (!$event || !isset($event['event_type'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $eventType = $event['event_type'];
        $eventId = $event['id'] ?? null;

        Log::info('PayPal webhook received', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        // 幂等性检查
        if ($eventId) {
            $existing = PaymentWebhookLog::where('event_id', $eventId)->first();
            if ($existing && $existing->status === 'completed') {
                return response()->json(['status' => 'duplicate']);
            }
        }

        $log = PaymentWebhookLog::create([
            'gateway' => 'paypal',
            'event_type' => $eventType,
            'event_id' => $eventId,
            'status' => 'processing',
            'payload' => $event,
        ]);

        try {
            $this->handlePayPalEvent($event, $log);
            $log->markCompleted();
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('PayPal webhook processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * 微信支付 Webhook 入口
     *
     * 微信支付 V3 API 回调：支付成功通知 / 退款通知
     */
    public function wechat(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $headers = $request->headers->all();

        // 微信支付 V3 回调：验签（简化处理，生产环境需用微信平台证书验签）
        $wechatSignature = $request->header('Wechatpay-Signature', '');
        $wechatSerial = $request->header('Wechatpay-Serial', '');
        $wechatTimestamp = $request->header('Wechatpay-Timestamp', '');
        $wechatNonce = $request->header('Wechatpay-Nonce', '');

        Log::info('WeChat Pay webhook received', [
            'serial' => $wechatSerial,
            'has_signature' => !empty($wechatSignature),
        ]);

        $event = json_decode($payload, true);
        if (!$event || !isset($event['event_type'])) {
            return response()->json(['code' => 'FAIL', 'message' => 'Invalid payload'], 400);
        }

        $eventType = $event['event_type'];
        // 微信使用 id 作为幂等键
        $eventId = $event['id'] ?? $event['resource']['out_trade_no'] ?? null;

        // 幂等性检查
        if ($eventId) {
            $existing = PaymentWebhookLog::where('event_id', $eventId)->first();
            if ($existing && $existing->status === 'completed') {
                return response()->json(['code' => 'SUCCESS', 'message' => __('app.api.payment.duplicate')]);
            }
        }

        $log = PaymentWebhookLog::create([
            'gateway' => 'wechat',
            'event_type' => $eventType,
            'event_id' => $eventId,
            'status' => 'processing',
            'payload' => $event,
        ]);

        try {
            $this->handleWeChatEvent($event, $log);
            $log->markCompleted();
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('WeChat Pay webhook processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            // 微信支付期望收到失败时返回 FAIL，会重试
            return response()->json(['code' => 'FAIL', 'message' => __('app.api.payment.fail')], 500);
        }

        // 微信支付要求返回 SUCCESS 字符串
        return response()->json(['code' => 'SUCCESS', 'message' => __('app.api.payment.ok')]);
    }

    // ========================
    // 签名验证
    // ========================

    protected function verifyStripeSignature(string $payload, string $sigHeader): bool
    {
        $endpointSecret = config('payment.channels.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET', ''));
        if (empty($endpointSecret)) {
            // 开发模式：无密钥则跳过验证
            if (app()->environment('local', 'testing')) {
                return true;
            }
            Log::warning('Stripe webhook secret not configured');
            return false;
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            return $event !== null;
        } catch (\Throwable $e) {
            Log::warning('Stripe signature verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ========================
    // PayPal 事件处理器
    // ========================

    protected function handlePayPalEvent(array $event, PaymentWebhookLog $log): void
    {
        $billingService = app(\App\Services\BillingService::class);
        $resource = $event['resource'] ?? [];

        match ($event['event_type']) {
            'CHECKOUT.ORDER.APPROVED' => $this->processPayPalOrderApproved($resource, $log, $billingService),
            'PAYMENT.CAPTURE.COMPLETED' => $this->processPayPalCaptureCompleted($resource, $log, $billingService),
            'PAYMENT.CAPTURE.REFUNDED' => $this->processPayPalCaptureRefunded($resource, $log, $billingService),
            'PAYMENT.CAPTURE.DENIED' => $this->processPayPalCaptureDenied($resource, $log, $billingService),
            default => Log::info("PayPal webhook: unhandled event type {$event['event_type']}"),
        };
    }

    protected function processPayPalOrderApproved(array $resource, PaymentWebhookLog $log, $billingService): void
    {
        $orderId = $resource['id'] ?? '';
        $invoiceId = $resource['purchase_units'][0]['reference_id'] ?? null;

        if (!$invoiceId) {
            Log::warning('PayPal webhook: order.approved missing reference_id');
            return;
        }

        // PayPal 订单已批准，等待 capture
        Log::info("PayPal: order {$orderId} approved, awaiting capture for invoice #{$invoiceId}");
    }

    protected function processPayPalCaptureCompleted(array $resource, PaymentWebhookLog $log, $billingService): void
    {
        $invoiceId = $resource['invoice_id'] ?? null;
        $customId = $resource['custom_id'] ?? $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        // 尝试从 purchase_units 获取
        if (!$invoiceId) {
            $invoiceId = $resource['purchase_units'][0]['reference_id'] ?? null;
        }

        if (!$invoiceId) {
            Log::warning('PayPal webhook: capture.completed cannot identify invoice');
            return;
        }

        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            Log::warning("PayPal webhook: invoice #{$invoiceId} not found");
            return;
        }

        $captureId = $resource['id'] ?? '';
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? '';

        $billingService->markInvoicePaid($invoice, [
            'transaction_id' => $orderId,
            'capture_id' => $captureId,
            'payment_method' => 'paypal',
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    protected function processPayPalCaptureRefunded(array $resource, PaymentWebhookLog $log, $billingService): void
    {
        $captureId = $resource['id'] ?? '';
        $invoiceId = $resource['invoice_id'] ?? null;

        if (!$invoiceId) {
            Log::warning('PayPal webhook: capture.refunded missing invoice_id');
            return;
        }

        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            Log::warning("PayPal webhook: invoice #{$invoiceId} not found for refund");
            return;
        }

        $billingService->markInvoiceRefunded($invoice, [
            'refund_id' => $captureId,
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    protected function processPayPalCaptureDenied(array $resource, PaymentWebhookLog $log, $billingService): void
    {
        $invoiceId = $resource['invoice_id'] ?? $resource['purchase_units'][0]['reference_id'] ?? null;

        if (!$invoiceId) {
            return;
        }

        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $invoice->update(['status' => 'failed']);
        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    // ========================
    // 易支付 事件处理器
    // ========================

    protected function processYipaySuccess(string $outTradeNo, ?string $tradeNo, PaymentWebhookLog $log): void
    {
        $billingService = app(\App\Services\BillingService::class);

        if (empty($outTradeNo)) {
            Log::warning('Yipay webhook: TRADE_SUCCESS missing out_trade_no');
            return;
        }

        // out_trade_no 为本地 invoice_no
        $invoice = \App\Models\Invoice::where('invoice_no', $outTradeNo)->first();
        if (!$invoice) {
            Log::warning("Yipay webhook: invoice #{$outTradeNo} not found");
            return;
        }

        $billingService->markInvoicePaid($invoice, [
            'transaction_id' => $tradeNo ?? 'yipay_' . uniqid(),
            'payment_method' => 'yipay',
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    // ========================
    // 微信支付 事件处理器
    // ========================

    protected function handleWeChatEvent(array $event, PaymentWebhookLog $log): void
    {
        $billingService = app(\App\Services\BillingService::class);
        $eventType = $event['event_type'] ?? '';
        $resource = $event['resource'] ?? [];

        match ($eventType) {
            'TRANSACTION.SUCCESS' => $this->processWeChatSuccess($resource, $log, $billingService),
            'REFUND.SUCCESS' => $this->processWeChatRefund($resource, $log, $billingService),
            default => Log::info("WeChat Pay webhook: unhandled event_type={$eventType}"),
        };
    }

    protected function processWeChatSuccess(array $resource, PaymentWebhookLog $log, $billingService): void
    {
        $outTradeNo = $resource['out_trade_no'] ?? '';
        $transactionId = $resource['transaction_id'] ?? '';

        if (empty($outTradeNo)) {
            Log::warning('WeChat Pay webhook: TRX.SUCCESS missing out_trade_no');
            return;
        }

        $invoice = \App\Models\Invoice::where('invoice_no', $outTradeNo)->first();
        if (!$invoice) {
            Log::warning("WeChat Pay webhook: invoice #{$outTradeNo} not found");
            return;
        }

        $billingService->markInvoicePaid($invoice, [
            'transaction_id' => $transactionId,
            'payment_method' => 'wechat',
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }

    protected function processWeChatRefund(array $resource, PaymentWebhookLog $log, $billingService): void
    {
        $outRefundNo = $resource['out_refund_no'] ?? '';
        $refundId = $resource['refund_id'] ?? '';

        // out_refund_no 格式为 refund_{invoice_no}
        $invoiceNo = str_replace('refund_', '', $outRefundNo);
        $invoice = \App\Models\Invoice::where('invoice_no', $invoiceNo)->first();

        if (!$invoice) {
            Log::warning("WeChat Pay webhook: refund invoice #{$invoiceNo} not found");
            return;
        }

        $billingService->markInvoiceRefunded($invoice, [
            'refund_id' => $refundId,
        ]);

        $log->update(['processable_type' => \App\Models\Invoice::class, 'processable_id' => $invoice->id]);
    }
}
