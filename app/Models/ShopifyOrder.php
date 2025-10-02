<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyOrder extends Model
{
    protected $fillable = [
        'shop_id',
        'erp_order_id',
        'shopify_order_id',
        'name',
        'status',
        'financial_status',
        'fulfillment_status',
        'total_price',
        'currency',
        'raw_payload',
        'synced_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function shop()
    {
        return $this->belongsTo(ShopifyShop::class, 'shop_id');
    }
}
