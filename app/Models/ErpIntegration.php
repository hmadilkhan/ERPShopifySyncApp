<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpIntegration extends Model
{
    protected $fillable = [
        'name',
        'erp_url',
        'erp_secret',
        'is_active',
    ];

    public function shop()
    {
        return $this->belongsTo(ShopifyShop::class, 'shop_id');
    }

    protected $casts = [
        // 'erp_secret' => 'encrypted', // Laravel will encrypt/decrypt automatically
    ];
}
