<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Exceptions\ShippingUnavailable;
use App\Models\City;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\ShippingService;
use App\Services\VoucherService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(private CartService $carts, private VoucherService $vouchers, private ShippingService $shipping)
    {
    }

    public function index()
    {
        ['cart' => $cart, 'total' => $total, 'notices' => $notices] = $this->carts->refresh();

        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->with('error', 'Keranjang kosong.');
        }

        if ($notices) {
            session()->flash('warning', implode(' ', $notices));
        }

        // Kunci anti-duplikat: dipakai ulang selama belum ada order yang jadi.
        $idempotencyKey = session()->get('checkout_key');
        if (!$idempotencyKey) {
            $idempotencyKey = (string) Str::uuid();
            session()->put('checkout_key', $idempotencyKey);
        }

        $customer = auth()->user()->customer;

        // Voucher dari session divalidasi ulang tiap halaman dibuka (bisa saja kedaluwarsa/habis).
        $voucher = null;
        $discount = 0;
        if ($code = session('checkout_voucher')) {
            try {
                $voucher = $this->vouchers->validate($code, $total, auth()->user());
                $discount = $this->vouchers->discount($voucher, $total);
            } catch (DomainException $e) {
                session()->forget('checkout_voucher');
                session()->flash('warning', 'Voucher dilepas: ' . $e->getMessage());
            }
        }
        $grandTotal = $total - $discount;

        $shippingConfigured = $this->shipping->configured();
        $afterDiscount = $total - $discount;

        return view('customer.checkout.index', compact('cart', 'total', 'customer', 'idempotencyKey', 'voucher', 'discount', 'grandTotal', 'shippingConfigured', 'afterDiscount'));
    }

    /**
     * Opsi layanan + ongkir untuk kota tujuan (JSON, dipanggil saat customer memilih kota).
     * Angka ini hanya tampilan; ongkir yang berlaku dihitung ulang di server saat pesanan dibuat.
     */
    public function shippingOptions(Request $request)
    {
        $cityId = (int) $request->query('city_id');
        $city = City::find($cityId);
        ['cart' => $cart, 'total' => $total] = $this->carts->refresh();

        if (!$city || empty($cart)) {
            return response()->json(['options' => [], 'message' => 'Pilih kota tujuan terlebih dahulu.']);
        }

        $discount = 0;
        if ($code = session('checkout_voucher')) {
            try {
                $discount = $this->vouchers->discount($this->vouchers->validate($code, $total, auth()->user()), $total);
            } catch (DomainException) {
                // voucher tidak lagi valid: abaikan untuk perhitungan tampilan
            }
        }

        $options = $this->shipping->options($city, $this->shipping->totalWeight($cart), $total - $discount);

        return response()->json([
            'options' => $options,
            'message' => $options ? null : 'Belum ada layanan pengiriman ke daerah ini. Silakan hubungi kami.',
        ]);
    }

    /** Pasang kode voucher (maks 1 per order, tidak bisa digabung). */
    public function applyVoucher(Request $request)
    {
        $code = $request->validate(['code' => 'required|string|max:50'])['code'];
        ['cart' => $cart, 'total' => $total] = $this->carts->refresh();

        if (empty($cart)) {
            return redirect()->route('customer.cart.index')->with('error', 'Keranjang kosong.');
        }

        try {
            $voucher = $this->vouchers->validate($code, $total, auth()->user());
        } catch (DomainException $e) {
            return redirect()->route('customer.checkout.index')->with('voucher_error', $e->getMessage());
        }

        session()->put('checkout_voucher', $voucher->code); // mengganti voucher sebelumnya (tidak digabung)

        return redirect()->route('customer.checkout.index')->with('success', "Voucher {$voucher->code} dipakai.");
    }

    public function removeVoucher()
    {
        session()->forget('checkout_voucher');

        return redirect()->route('customer.checkout.index')->with('success', 'Voucher dilepas.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'idempotency_key' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'province_id' => 'required|integer|exists:provinces,id',
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('province_id', $request->input('province_id'))],
            'postal_code' => 'required|digits:5',
            'shipping_method_id' => [Rule::requiredIf(fn () => $this->shipping->configured()), 'nullable', 'integer'],
            'note' => 'nullable|string',
        ], [
            'city_id.exists' => 'Kota tidak sesuai dengan provinsi yang dipilih.',
            'postal_code.digits' => 'Kode pos harus 5 digit angka.',
            'shipping_method_id.required' => 'Pilih layanan pengiriman.',
        ]);

        $key = $validated['idempotency_key'];

        // Request ulang (double-click, refresh, dua tab) -> arahkan ke order yang sudah jadi.
        if ($existing = $this->findByKey($key)) {
            return $this->toOrder($existing, 'Pesanan ini sudah dibuat.');
        }

        if ($key !== session()->get('checkout_key')) {
            return redirect()->route('customer.checkout.index')
                ->with('error', 'Sesi checkout kedaluwarsa. Silakan periksa data dan kirim ulang.');
        }

        ['cart' => $cart, 'notices' => $notices] = $this->carts->refresh();

        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->with('error', 'Keranjang kosong atau produk sudah tidak tersedia.');
        }

        // Harga/stok berubah sejak halaman checkout dibuka -> minta konfirmasi ulang.
        if ($notices) {
            return redirect()->route('customer.checkout.index')
                ->with('warning', implode(' ', $notices) . ' Periksa kembali pesananmu.');
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $order = DB::transaction(fn () => $this->createOrder($validated, $cart, $key, session('checkout_voucher')));

                session()->forget(['cart', 'checkout_key', 'checkout_voucher']);

                return $this->toOrder($order, 'Pesanan berhasil dibuat. Silakan lakukan pembayaran sebelum ' . $order->payment_deadline->format('d M Y H:i') . '.');
            } catch (ShippingUnavailable $e) {
                return back()->withInput()->with('error', $e->getMessage());
            } catch (DomainException $e) {
                // Voucher tidak lolos validasi ulang (habis/kedaluwarsa/dipakai di tab lain): transaksi sudah di-rollback.
                session()->forget('checkout_voucher');

                return redirect()->route('customer.checkout.index')
                    ->with('warning', 'Voucher tidak bisa dipakai: ' . $e->getMessage() . ' Total belum termasuk diskon, periksa kembali pesananmu.');
            } catch (QueryException $e) {
                // Request kembar yang menang duluan -> pakai order miliknya.
                if ($existing = $this->findByKey($key)) {
                    return $this->toOrder($existing, 'Pesanan ini sudah dibuat.');
                }
                // Selain itu: kemungkinan nomor order bentrok -> coba lagi dengan nomor baru.
                if ($attempt === 3) {
                    report($e);

                    return back()->withInput()->with('error', 'Gagal membuat pesanan. Silakan coba lagi.');
                }
            }
        }

        return back()->withInput()->with('error', 'Gagal membuat pesanan. Silakan coba lagi.');
    }

    private function createOrder(array $data, array $cart, string $key, ?string $voucherCode = null): Order
    {
        $city = City::with('province')->findOrFail($data['city_id']);
        $province = $city->province;

        $customer = Customer::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'name' => $data['name'],
                'email' => auth()->user()->email,
                'phone' => $data['phone'],
                'address' => $data['address'],
                'province_id' => $province->id,
                'city_id' => $city->id,
                'city' => $city->name,
                'province' => $province->name,
                'postal_code' => $data['postal_code'],
            ]
        );

        $subtotal = collect($cart)->sum(fn ($i) => $i['price'] * $i['quantity']);

        $voucher = null;
        $discount = 0;
        if ($voucherCode) {
            // reserve(): kunci baris voucher, validasi ulang dengan data terbaru, lalu naikkan used_count.
            ['voucher' => $voucher, 'discount' => $discount] = $this->vouchers->reserve($voucherCode, $subtotal, auth()->user());
        }

        // Ongkir dihitung ulang di server (angka dari browser tidak dipercaya), setelah diskon diketahui.
        $weight = $this->shipping->totalWeight($cart);
        $quote = null;
        if ($this->shipping->configured()) {
            $quote = $this->shipping->quote((int) $data['shipping_method_id'], $city, $weight, $subtotal - $discount);
            if (!$quote) {
                throw new ShippingUnavailable('Layanan pengiriman yang dipilih tidak tersedia untuk tujuan ini. Silakan pilih ulang.');
            }
        }
        $shippingCost = $quote['cost'] ?? 0;

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'idempotency_key' => $key,
            'customer_id' => $customer->id,
            'user_id' => auth()->id(),
            'status' => 'menunggu_bayar',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'voucher_id' => $voucher?->id,
            'voucher_code' => $voucher?->code,
            'shipping_cost' => $shippingCost,
            'shipping_method' => $quote['name'] ?? null,
            'shipping_weight' => $weight,
            'shipping_etd' => $quote['etd'] ?? null,
            'total' => $subtotal - $discount + $shippingCost,
            'note' => $data['note'] ?? null,
            // Snapshot alamat: tidak ikut berubah kalau profil diedit nanti.
            'shipping_name' => $data['name'],
            'shipping_phone' => $data['phone'],
            'shipping_address' => $data['address'],
            'shipping_province' => $province->name,
            'shipping_city' => $city->name,
            'shipping_postal_code' => $data['postal_code'],
        ]);

        foreach ($cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'product_sku' => $item['sku'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['price'] * $item['quantity'],
            ]);
        }

        return $order;
    }

    private function findByKey(string $key): ?Order
    {
        return Order::where('idempotency_key', $key)
            ->where('user_id', auth()->id())
            ->first();
    }

    private function toOrder(Order $order, string $message)
    {
        return redirect()->route('customer.orders.show', $order->order_number)
            ->with('success', $message);
    }
}
