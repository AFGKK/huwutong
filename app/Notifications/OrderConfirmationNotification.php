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
        $channels = ['mail'];

        // D-28: FCM 推送
        if ($notifiable->fcm_token ?? null) {
            $channels[] = 'fcm';
        }

        return $channels;
    }

    /**
     * D-28: FCM 推送消息
     */
    public function toFcm($notifiable): array
    {
        return [
            'title' => __('app.notifications.order.fcm_title'),
            'body' => __('app.notifications.order.fcm_body', [
                'no' => $this->order->order_no,
                'amount' => number_format($this->order->final_amount, 2),
            ]),
            'data' => [
                'type' => 'order_confirmation',
                'order_id' => (string) $this->order->id,
                'order_no' => $this->order->order_no,
                'route' => '/build/orders/' . $this->order->id,
                'category' => 'order',
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $order = $this->order;
        $order->load(['items.sku.product', 'deliveries']);

        $message = (new MailMessage)
            ->subject(__('app.notifications.order.subject', ['no' => $order->order_no]))
            ->greeting(__('app.notifications.greeting_generic'))
            ->line(__('app.notifications.order.paid_ok'))
            ->line(__('app.notifications.order.order_no', ['no' => $order->order_no]))
            ->line(__('app.notifications.order.amount', [
                'amount' => number_format($order->final_amount, 2),
            ]))
            ->line(__('app.notifications.order.created_at', [
                'time' => $order->created_at->format('Y-m-d H:i'),
            ]))
            ->line('---');

        foreach ($order->items as $item) {
            $message->line(__('app.notifications.order.item', [
                'name' => ($item->sku?->product?->name ?? '') . ' - ' . $item->name,
            ]));
            $message->line(__('app.notifications.order.qty_price', [
                'qty' => $item->quantity,
                'price' => number_format($item->unit_price, 2),
            ]));
        }

        // License 信息
        $licenses = $order->deliveries()
            ->where('delivery_type', 'license_key')
            ->get();

        if ($licenses->isNotEmpty()) {
            $message->line('---');
            $message->line(__('app.notifications.order.license_keys'));
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
            ->line(__('app.notifications.order.thanks'))
            ->action(__('app.notifications.order.view_order'), url('/build/orders/' . $order->id));

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
