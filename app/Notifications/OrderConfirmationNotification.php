<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Order $order;
    protected string $recipientEmail;

    public function __construct(Order $order, string $recipientEmail)
    {
        $this->order = $order;
        $this->recipientEmail = $recipientEmail;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $order = $this->order;
        $order->load(['items.sku.product', 'deliveries']);

        $message = (new MailMessage)
            ->subject('订单确认 - #' . $order->order_no)
            ->greeting('您好！')
            ->line('您的订单已支付成功，以下为订单详情：')
            ->line('订单编号：' . $order->order_no)
            ->line('订单金额：¥' . number_format($order->final_amount, 2))
            ->line('下单时间：' . $order->created_at->format('Y-m-d H:i'))
            ->line('---');

        foreach ($order->items as $item) {
            $message->line('商品：' . ($item->sku?->product?->name ?? '') . ' - ' . $item->name);
            $message->line('数量：' . $item->quantity . ' × ¥' . number_format($item->unit_price, 2));
        }

        // License 信息
        $licenses = $order->deliveries()
            ->where('delivery_type', 'license_key')
            ->get();

        if ($licenses->isNotEmpty()) {
            $message->line('---');
            $message->line('您的授权码（License Key）：');
            foreach ($licenses as $delivery) {
                $content = json_decode($delivery->content, true) ?? [];
                foreach ($content as $lic) {
                    $key = $lic['license_key'] ?? '';
                    if ($key) {
                        $message->line('🔑 `' . $key . '`');
                    }
                }
            }
        }

        $message->line('---')
            ->line('感谢您的购买！如有问题请联系客服。')
            ->action('查看订单', url('/build/orders/' . $order->id));

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
        ];
    }
}
