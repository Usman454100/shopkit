<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductExpired extends Notification
{
    use Queueable;

    public function __construct(private readonly Product $product) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Product expired',
            'body' => "\"{$this->product->name}\" passed its expiry date and was taken off the catalog.",
            'product_id' => $this->product->id,
        ];
    }
}
