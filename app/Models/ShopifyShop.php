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
        'erp_integration_id',
    ];

    public function erpIntegration()
    {
        return $this->belongsTo(ErpIntegration::class);
    }
}
