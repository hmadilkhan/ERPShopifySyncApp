<?php

namespace App\Http\Controllers;

use App\Models\ShopifyShop;
use App\Models\ErpIntegration;
use Illuminate\Http\Request;

class ShopifyErpController extends Controller
{
    public function show($shopId)
    {
        $shop = ShopifyShop::with('erpIntegration')->findOrFail($shopId);
        return view('shopify.erp_setup', compact('shop'));
    }

    public function save(Request $request, $shopId)
    {
        $request->validate([
            'name' => 'required|string',
            'erp_url' => 'required|url',
            'erp_secret' => 'required|string',
        ]);
     
        $shop = ShopifyShop::findOrFail($shopId);

        $shop->erpIntegration()->updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'name' => $request->name,
                'erp_url' => $request->erp_url,
                'erp_secret' => $request->erp_secret, // will be encrypted by model cast
            ]
        );

        return redirect()->route('shopify.erp.show', $shopId)
            ->with('success', 'ERP settings saved successfully!');
    }
}
