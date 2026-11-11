<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Permintaan penawaran (quotation) disimpan sebagai pesan kontak, jadi muncul di menu Pesan admin.
 */
class QuoteController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $product = Product::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $v = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'regex:/^[0-9+\s-]{8,20}$/'],
            'quantity' => 'required|integer|min:1|max:999',
            'note' => 'nullable|string|max:1000',
            'payment' => 'nullable|in:tunai,cicilan',
        ], ['phone.regex' => 'Nomor telepon tidak valid.']);

        // Honeypot: bot yang mengisi kolom "website" dianggap sukses tanpa menyimpan.
        if (filled($request->input('website'))) {
            return back()->with('success', 'Permintaan penawaran terkirim.');
        }

        $variant = $request->filled('variant') ? Str::limit(strip_tags((string) $request->input('variant')), 80, '') : null;

        ContactMessage::create([
            'name' => $v['name'],
            'email' => $v['email'],
            'phone' => $v['phone'],
            'subject' => Str::limit('Permintaan Penawaran: ' . $product->name, 250, ''),
            'message' => implode("\n", array_filter([
                'Produk: ' . $product->name . ' (SKU ' . $product->sku . ')',
                $variant ? 'Varian: ' . $variant : null,
                'Jumlah: ' . $v['quantity'],
                'Pembayaran: ' . ($v['payment'] ?? 'tunai'),
                $v['note'] ? "Catatan: " . $v['note'] : null,
            ])),
        ]);

        return back()->with('success', 'Permintaan penawaran terkirim. Tim kami akan menghubungi kamu.');
    }
}
