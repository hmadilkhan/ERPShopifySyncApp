<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProductVariant extends Model
{
    protected $fillable = [
        'shopify_product_id',
        'shop_id',
        'erp_variant_id',
        'shopify_variant_id',
        'inventory_item_id',
        'sku',
        'title',
        'price',
        'stock',
        'image_url',
    ];

    public function shop()
    {
        return $this->belongsTo(ShopifyShop::class, 'shop_id');
    }

    public function product()
    {
        return $this->belongsTo(ShopifyProduct::class, 'shopify_product_id');
    }
}
