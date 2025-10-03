<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyShop extends Model
{
    protected $fillable = [
        'shop_domain',
        'access_token',
        'scope',
        'name',
        'email',
        'currency',
        'timezone',
        'is_active',
        'erp_secret',
    ];

    // public function erpIntegration()
    // {
    //     return $this->belongsTo(ErpIntegration::class);
    // }

    public function erpIntegration()
    {
        return $this->hasOne(ErpIntegration::class, 'shop_id');
    }
}
