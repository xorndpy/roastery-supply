<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlashSaleRequest;
use App\Models\FlashSale;
use App\Models\Product;
use App\Services\FlashSaleService;
use Illuminate\Support\Facades\DB;

/** Kampanye flash sale (CRUD). Harga promo ditegakkan di server (CartService + FlashSaleService::reserve). */
class FlashSaleController extends Controller
{
    public function __construct(private FlashSaleService $service) {}

    public function index()
    {
        $sales = FlashSale::withCount('items')->with('items')->orderByDesc('starts_at')->paginate(15);
        $sold = $this->service->soldFor($sales->getCollection()->flatMap->items->pluck('id')->all());

        return view('admin.flash-sales.index', compact('sales', 'sold'));
    }

    public function create()
    {
        return view('admin.flash-sales.form', ['sale' => null, 'products' => $this->products(), 'sold' => []]);
    }

    public function store(FlashSaleRequest $request)
    {
        [$start, $end] = $request->window();

        DB::transaction(function () use ($request, $start, $end) {
            $sale = FlashSale::create(['name' => $request->input('name'), 'starts_at' => $start, 'ends_at' => $end, 'is_active' => $request->boolean('is_active')]);
            foreach ($request->input('items') as $row) {
                $sale->items()->create($this->itemData($row));
            }
        });
        $this->service->flush();

        return redirect()->route('admin.flash-sales.index')->with('success', 'Kampanye flash sale dibuat.');
    }

    public function edit(FlashSale $flashSale)
    {
        $flashSale->load('items');

        return view('admin.flash-sales.form', ['sale' => $flashSale, 'products' => $this->products(), 'sold' => $this->service->soldFor($flashSale->items->pluck('id')->all())]);
    }

    public function update(FlashSaleRequest $request, FlashSale $flashSale)
    {
        [$start, $end] = $request->window();
        $sold = $this->service->soldFor($flashSale->items()->pluck('id')->all());
        $submitted = collect($request->input('items'))->keyBy(fn ($r) => (int) $r['product_id']);

        // Item yang sudah laku tidak boleh dihapus (riwayat pesanan menunjuk ke sana).
        $blocked = $flashSale->items()->whereNotIn('product_id', $submitted->keys())->get()->first(fn ($i) => ($sold[$i->id] ?? 0) > 0);
        if ($blocked) {
            return back()->withInput()->withErrors(['items' => 'Produk yang sudah terjual dalam kampanye ini tidak bisa dihapus. Nonaktifkan kampanyenya bila perlu.']);
        }

        DB::transaction(function () use ($request, $flashSale, $start, $end, $submitted) {
            $flashSale->update(['name' => $request->input('name'), 'starts_at' => $start, 'ends_at' => $end, 'is_active' => $request->boolean('is_active')]);
            $flashSale->items()->whereNotIn('product_id', $submitted->keys())->delete();
            foreach ($submitted as $productId => $row) {
                $flashSale->items()->updateOrCreate(['product_id' => $productId], $this->itemData($row));
            }
        });
        $this->service->flush();

        return redirect()->route('admin.flash-sales.index')->with('success', 'Kampanye diperbarui.');
    }

    public function destroy(FlashSale $flashSale)
    {
        if (array_sum($this->service->soldFor($flashSale->items()->pluck('id')->all())) > 0) {
            return back()->with('error', 'Kampanye ini sudah punya penjualan, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }
        $flashSale->delete();
        $this->service->flush();

        return redirect()->route('admin.flash-sales.index')->with('success', 'Kampanye dihapus.');
    }

    private function itemData(array $row): array
    {
        return ['product_id' => (int) $row['product_id'], 'sale_price' => round((float) $row['sale_price'], 2), 'quota' => (int) $row['quota'], 'max_per_order' => ($row['max_per_order'] ?? null) ?: null];
    }

    private function products()
    {
        return Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price', 'stock'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price, 'stock' => $p->stock]);
    }
}
