<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

/** Pencarian data untuk palet Ctrl+K: pesanan, produk, pelanggan. Hasil mengikuti hak akses user. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $user = $request->user();
        $out = [];

        if ($user->can('view_orders')) {
            $orders = Order::with('customer')
                ->where(function ($w) use ($like) {
                    $w->where('order_number', 'like', $like)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like))
                        ->orWhereHas('shipments', fn ($s) => $s->where('tracking_number', 'like', $like));
                })
                ->latest('id')->limit(5)->get();
            foreach ($orders as $o) {
                $out[] = [
                    'label' => $o->order_number . ' · ' . ($o->customer->name ?? 'Guest'),
                    'group' => 'Pesanan · ' . $o->status_label,
                    'href' => route('admin.orders.show', $o->id),
                ];
            }
        }

        if ($user->can('view_products')) {
            $products = Product::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                ->orderBy('name')->limit(5)->get();
            foreach ($products as $p) {
                $out[] = [
                    'label' => $p->name,
                    'group' => 'Produk · ' . $p->sku,
                    'href' => $user->can('edit_products')
                        ? route('admin.products.edit', $p->id)
                        : route('admin.products.index', ['search' => $p->sku]),
                ];
            }
        }

        if ($user->can('view_customers')) {
            $customers = Customer::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')->limit(5)->get();
            foreach ($customers as $c) {
                $out[] = [
                    'label' => $c->name,
                    'group' => 'Pelanggan · ' . ($c->phone ?: $c->email),
                    'href' => route('admin.customers.show', $c->id),
                ];
            }
        }

        return response()->json($out);
    }
}
