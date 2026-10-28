<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Order $order,
        private string $status
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_status',
            'order_id' => (int) $this->order->id,
            'status' => $this->status,
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
        ];
    }

    private function getTitle(): string
    {
        return match ($this->status) {
            'confirmed' => 'Order confirmed',
            'processing' => 'Order is being prepared',
            'shipped' => 'Order shipped',
            'delivered' => 'Order delivered',
            'cancelled' => 'Order cancelled',
            default => 'Order update',
        };
    }

    private function getMessage(): string
    {
        return match ($this->status) {
            'confirmed' =>
                "Your order #{$this->order->id} has been confirmed.",

            'processing' =>
                "Your order #{$this->order->id} is being prepared.",

            'shipped' =>
                "Your order #{$this->order->id} has been shipped.",

            'delivered' =>
                "Your order #{$this->order->id} has been delivered.",

            'cancelled' =>
                "Your order #{$this->order->id} has been cancelled.",

            default =>
                "Your order #{$this->order->id} has been updated.",
        };
    }
}