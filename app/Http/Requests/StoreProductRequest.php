<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'weight' => 'nullable|integer|min:0',
            'stock' => 'required|integer|min:0',
            'stock_threshold' => 'required|integer|min:0',
            'model_3d' => 'nullable|file|mimes:glb,gltf|max:10240',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }
}