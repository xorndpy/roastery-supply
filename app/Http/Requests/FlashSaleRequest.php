<?php

namespace App\Http\Requests;

use App\Models\FlashSale;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin dijaga middleware can:*_flash_sales
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:12'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.sale_price' => ['required', 'numeric', 'min:1', 'max:99999999999'],
            'items.*.quota' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.max_per_order' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kampanye wajib diisi.',
            'items.required' => 'Tambahkan minimal satu produk.',
            'items.min' => 'Tambahkan minimal satu produk.',
            'items.max' => 'Maksimal 12 produk per kampanye.',
            'items.*.product_id.distinct' => 'Produk yang sama dipilih dua kali.',
            'items.*.sale_price.required' => 'Harga promo wajib diisi.',
            'items.*.quota.required' => 'Kuota wajib diisi.',
            'items.*.quota.min' => 'Kuota minimal 1.',
        ];
    }

    public function window(): array
    {
        $tz = 'Asia/Jakarta';

        return [
            Carbon::createFromFormat('Y-m-d\TH:i', $this->input('starts_at'), $tz)->utc(),
            Carbon::createFromFormat('Y-m-d\TH:i', $this->input('ends_at'), $tz)->utc(),
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            [$start, $end] = $this->window();

            if ($end->lte($start)) {
                $v->errors()->add('ends_at', 'Waktu selesai harus setelah waktu mulai.');

                return;
            }
            if ($start->diffInDays($end) > 31) {
                $v->errors()->add('ends_at', 'Satu kampanye maksimal 31 hari.');
            }

            $current = $this->route('flash_sale');
            $products = Product::whereIn('id', collect($this->input('items'))->pluck('product_id'))->get()->keyBy('id');

            foreach ($this->input('items', []) as $i => $row) {
                $p = $products->get((int) $row['product_id']);
                if ($p && (float) $row['sale_price'] >= (float) $p->price) {
                    $v->errors()->add("items.$i.sale_price", "Harga promo \"{$p->name}\" harus di bawah harga normal (Rp ".number_format($p->price, 0, ',', '.').').');
                }
                if ($current) {
                    $sold = app(\App\Services\FlashSaleService::class)->soldFor($current->items()->where('product_id', $row['product_id'])->pluck('id')->all());
                    if ($sold && (int) $row['quota'] < array_sum($sold)) {
                        $v->errors()->add("items.$i.quota", 'Kuota tidak boleh di bawah jumlah yang sudah terjual ('.array_sum($sold).').');
                    }
                }
                // Satu produk tidak boleh ada di dua kampanye aktif yang jadwalnya bertumpuk.
                $clash = FlashSale::where('is_active', true)->when($current, fn ($q) => $q->where('id', '!=', $current->id))
                    ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                    ->whereHas('items', fn ($q) => $q->where('product_id', $row['product_id']))->first();
                if ($clash) {
                    $v->errors()->add("items.$i.product_id", "\"".($p->name ?? 'Produk')."\" sudah ada di kampanye \"{$clash->name}\" pada jadwal yang bertumpuk.");
                }
            }
        }];
    }
}
