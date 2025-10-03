<?php

namespace App\Http\Requests\Erp;

use Illuminate\Foundation\Http\FormRequest;

class ProductSyncRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product.sku' => 'required|string',
            'product.title' => 'required|string',
            'product.description' => 'nullable|string',
            'product.price' => 'required|numeric|min:0',
            'product.currency' => 'required|string|size:3',
            'product.stock' => 'required|integer|min:0',
            'product.vendor' => 'nullable|string',
            'product.product_type' => 'nullable|string',
            'product.status' => 'required|in:active,draft,archived',
            'product.variants.*.sku' => 'nullable|string',
            'product.variants.*.option' => 'nullable|string',
            'product.variants.*.price' => 'numeric',
            'product.variants.*.stock' => 'integer|min:0',
            'product.images.*' => 'url'
        ];
    }
}
