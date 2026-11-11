<?php

namespace App\Services;

use App\Models\Product;

/**
 * Keranjang disimpan di session (guest boleh belanja, login baru saat checkout).
 * Isi session hanya dipercaya sebagai daftar id + qty; harga, nama, dan stok
 * selalu disegarkan dari database supaya tidak basi.
 */
class CartService
{
    /**
     * @return array{cart: array<int,array>, total: float|int, notices: array<int,string>}
     */
    public function refresh(): array
    {
        $cart = session()->get('cart', []);
        $notices = [];

        if (empty($cart)) {
            return ['cart' => [], 'total' => 0, 'notices' => []];
        }

        $products = Product::with(['images', 'variants'])
            ->whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $fresh = [];
        $total = 0;

        foreach ($cart as $id => $line) {
            $product = $products->get((int) $id);

            if (!$product || $product->stock <= 0) {
                $name = $line['name'] ?? 'Produk';
                $notices[] = "\"{$name}\" sudah tidak tersedia dan dihapus dari keranjang.";
                continue;
            }

            $qty = max(1, (int) $line['quantity']);
            if ($qty > $product->stock) {
                $notices[] = "Jumlah \"{$product->name}\" disesuaikan menjadi {$product->stock} (stok tersisa).";
                $qty = $product->stock;
            }

            // Varian dibaca ulang dari database; bila sudah dihapus admin, kembali ke produk dasar.
            $variant = !empty($line['variant_id']) ? $product->variants->firstWhere('id', (int) $line['variant_id']) : null;
            $unitPrice = $product->price;
            $label = $variant ? "{$product->name} ({$variant->type}: {$variant->name})" : $product->name;

            if ((float) $line['price'] !== (float) $unitPrice) {
                $notices[] = "Harga \"{$product->name}\" berubah.";
            }

            $fresh[$product->id] = [
                'id'       => $product->id,
                'name'     => $label,
                'slug'     => $product->slug,
                'sku'      => $product->sku,
                'weight'   => (int) $product->weight,   // gram, untuk ongkir
                'price'    => $unitPrice,
                'variant_id' => $variant?->id,
                'quantity' => $qty,
                'image'    => $product->images->first()->image ?? ($line['image'] ?? null),
                'stock'    => $product->stock,
            ];

            $total += $unitPrice * $qty;
        }

        session()->put('cart', $fresh);

        return ['cart' => $fresh, 'total' => $total, 'notices' => $notices];
    }
}
