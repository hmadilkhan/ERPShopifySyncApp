<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    protected $fillable = [
        'shop_id',
        'erp_product_id',
        'shopify_product_id',
        'shopify_variant_id',
        'inventory_item_id',
        'sku',
        'title',
        'status',
        'stock',
        'price',
        'synced_at',
    ];

    public function shop()
    {
        return $this->belongsTo(ShopifyShop::class, 'shop_id');
    }
}
