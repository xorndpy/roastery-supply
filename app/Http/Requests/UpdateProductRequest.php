<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super_admin']);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'weight' => $this->input('weight') ?? 0,
        ]);
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:products,sku,' . $productId,
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'weight' => 'nullable|integer|min:0',
            'stock' => 'nullable|integer|min:0', // diabaikan: stok diubah lewat menu Stok
            'stock_threshold' => 'required|integer|min:0',
            'model_3d' => 'nullable|file|mimes:glb,gltf|max:10240',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'keep_images' => 'nullable|array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'specs' => 'nullable|array|max:30',
            'specs.*.key' => 'nullable|string|max:40',
            'specs.*.value' => 'nullable|string|max:120',
            'variants' => 'nullable|array|max:12',
            'variants.*.name' => 'nullable|string|max:60',
            'variants.*.type' => 'nullable|string|max:30',
            'variants.*.product_image_id' => 'nullable|integer',
            'variants.*.color_hex' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'variants.*.price_adjust' => 'nullable|numeric|between:-1000000000,1000000000',
        ];
    }
}