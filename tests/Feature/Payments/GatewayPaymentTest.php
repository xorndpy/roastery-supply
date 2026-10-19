<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\MidtransService;
use App\Services\Payments\PaymentReconciler;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pengujian alur pembayaran gateway (Midtrans). Setiap test = satu risiko nyata.
 * Kode kasus (P-01 dst.) dipakai di docs/ALUR_PEMBAYARAN.md.
 */
class GatewayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Order $order;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config([
            'services.midtrans.server_key' => 'SB-Mid-server-TESTKEY',
            'services.midtrans.client_key' => 'SB-Mid-client-TESTKEY',
            'services.midtrans.is_production' => false,
            'services.midtrans.verify_status_api' => false,
        ]);
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);

        $this->user = User::factory()->create();
        $this->product = $this->makeProduct(stock: 10);
        [$this->order, $this->payment] = $this->makeOrderWithAttempt($this->product, qty: 2);
    }

    // ------------------------------------------------------------------ helper

    private function makeProduct(int $stock): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create([
            'name' => "Mesin {$n}", 'slug' => "mesin-{$n}", 'sku' => "SKU-{$n}", 'price' => 5_000_000, 'stock' => $stock, 'stock_threshold' => 2,
        ]));
    }

    private function makeOrderWithAttempt(Product $product, int $qty, string $suffix = 'P1'): array
    {
        static $seq = 0;
        $seq++;
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $this->user->id, 'name' => 'Budi', 'email' => "c{$seq}@x.test"]));
        $total = $product->price * $qty;
        $order = Order::unguarded(fn () => Order::create([
            'order_number' => 'ORD-TEST-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT), 'customer_id' => $customer->id, 'user_id' => $this->user->id,
            'status' => 'menunggu_bayar', 'subtotal' => $total, 'total' => $total,
        ]));
        OrderItem::unguarded(fn () => OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'product_sku' => $product->sku,
            'price' => $product->price, 'quantity' => $qty, 'subtotal' => $total,
        ]));

        return [$order, $this->attempt($order, $suffix)];
    }

    private function attempt(Order $order, string $suffix): Payment
    {
        return $order->payments()->create([
            'method' => 'midtrans', 'provider' => 'midtrans', 'provider_order_id' => $order->order_number . '-' . $suffix,
            'amount' => $order->total, 'status' => 'waiting', 'expires_at' => now()->addHours(24),
        ]);
    }

    /** Notifikasi bertanda tangan sah seperti kiriman Midtrans. */
    private function notif(Payment $p, string $status, array $o = []): array
    {
        $gross = $o['gross_amount'] ?? number_format((float) $p->amount, 2, '.', '');
        $code = $o['status_code'] ?? match ($status) { 'pending' => '201', 'settlement', 'capture' => '200', default => '202' };
        $payload = array_merge([
            'transaction_time' => '2026-10-14 10:00:00', 'transaction_status' => $status, 'transaction_id' => 'trx-' . $p->id,
            'status_code' => $code, 'payment_type' => 'bank_transfer', 'order_id' => $p->provider_order_id,
            'gross_amount' => $gross, 'fraud_status' => 'accept', 'currency' => 'IDR',
        ], $o);
        $payload['signature_key'] = app(MidtransService::class)->signatureFor($payload['order_id'], $payload['status_code'], $payload['gross_amount']);

        return $payload;
    }

    private function send(array $payload)
    {
        return $this->postJson(route('webhooks.midtrans'), $payload);
    }

    private function stock(Product $p = null): int
    {
        return (int) Product::find(($p ?? $this->product)->id)->stock;
    }

    // ------------------------------------------------------------------ P-01 .. P-03: alur normal & keamanan

    public function test_P01_settlement_confirms_order_and_deducts_stock_once(): void
    {
        $this->send($this->notif($this->payment, 'settlement'))->assertOk();

        $this->assertSame('dikonfirmasi', $this->order->fresh()->status);
        $this->assertSame('verified', $this->payment->fresh()->status);
        $this->assertNull($this->payment->fresh()->verified_by, 'otomatis = tanpa admin');
        $this->assertSame(8, $this->stock());
        $this->assertSame(1, StockMovement::where('reference_type', 'order')->where('reference_id', $this->order->id)->count());
        $this->assertSame('dikonfirmasi', PaymentEvent::first()->outcome);
    }

    public function test_P02_same_notification_sent_many_times_is_processed_exactly_once(): void
    {
        $payload = $this->notif($this->payment, 'settlement');

        $this->send($payload)->assertOk()->assertJson(['status' => 'accepted']);
        $this->send($payload)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->send($payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, PaymentEvent::count(), 'satu kejadian = satu baris event');
        $this->assertSame(8, $this->stock(), 'stok dikurangi sekali, bukan tiga kali');
        $this->assertSame(1, StockMovement::count());
    }

    public function test_P03_invalid_signature_is_rejected_and_changes_nothing(): void
    {
        $payload = $this->notif($this->payment, 'settlement');
        $payload['signature_key'] = str_repeat('a', 128);

        $this->send($payload)->assertForbidden();

        $this->assertSame('menunggu_bayar', $this->order->fresh()->status);
        $this->assertSame(10, $this->stock());
        $event = PaymentEvent::first();
        $this->assertFalse($event->signature_valid);
        $this->assertSame('ditolak_signature', $event->outcome);
    }

    public function test_P03b_tampered_amount_invalidates_signature(): void
    {
        $payload = $this->notif($this->payment, 'settlement');
        $payload['gross_amount'] = '1.00';       // tanda tangan dihitung dari 10000000.00

        $this->send($payload)->assertForbidden();
        $this->assertSame('menunggu_bayar', $this->order->fresh()->status);
    }

    public function test_P03c_unknown_order_returns_200_so_gateway_does_not_retry(): void
    {
        $fake = new Payment(['provider_order_id' => 'TIDAK-ADA-P1', 'amount' => 1000]);
        $this->send($this->notif($fake, 'settlement'))->assertOk();

        $this->assertSame('order_tidak_dikenal', PaymentEvent::first()->outcome);
    }

    // ------------------------------------------------------------------ P-04 .. P-06: satu jalur, tidak bentrok

    public function test_P04_webhook_then_admin_click_cannot_confirm_twice(): void
    {
        $this->send($this->notif($this->payment, 'settlement'))->assertOk();

        // Admin klik "verifikasi" belakangan: pintu yang sama, status sudah final -> ditolak, stok tidak berubah.
        $this->expectException(DomainException::class);
        try {
            app(OrderService::class)->confirmPayment($this->order->fresh(), 1);
        } finally {
            $this->assertSame(8, $this->stock());
            $this->assertSame(1, StockMovement::count());
        }
    }

    public function test_P05_settle_called_twice_in_a_row_is_idempotent(): void
    {
        $svc = app(OrderService::class);
        $a = $svc->settleGatewayPayment($this->payment->id);
        $b = $svc->settleGatewayPayment($this->payment->id);

        $this->assertSame('dikonfirmasi', $a['outcome']);
        $this->assertSame('sudah_diproses', $b['outcome']);
        $this->assertSame(8, $this->stock());
    }

    public function test_P06_customer_pays_twice_on_two_sessions_second_is_flagged_duplicate(): void
    {
        $second = $this->attempt($this->order, 'P2');      // pelanggan membuka dua tab -> dua sesi

        $this->send($this->notif($this->payment, 'settlement'))->assertOk();
        $this->send($this->notif($second, 'settlement'))->assertOk();

        $this->assertSame('dikonfirmasi', $this->order->fresh()->status);
        $this->assertSame('verified', $this->payment->fresh()->status);
        $this->assertSame('duplicate', $second->fresh()->status);
        $this->assertSame('pembayaran_ganda', $second->fresh()->review_reason);
        $this->assertSame(8, $this->stock(), 'stok tetap dikurangi satu kali');
        $this->assertTrue($second->fresh()->requiresAttention(), 'harus muncul di antrean admin untuk refund');
    }

    public function test_P06b_other_open_session_is_closed_when_one_is_paid(): void
    {
        $second = $this->attempt($this->order, 'P2');
        Http::fake(['*' => Http::response(['status_code' => '200'])]);

        $this->send($this->notif($this->payment, 'settlement'))->assertOk();

        $this->assertSame('expired', $second->fresh()->status);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/' . $second->provider_order_id . '/expire'));
    }

    // ------------------------------------------------------------------ P-07 .. P-10: kasus tepi uang masuk

    public function test_P07_paid_but_out_of_stock_is_held_for_admin_not_lost(): void
    {
        Product::whereKey($this->product->id)->update(['stock' => 1]);     // dipesan 2

        $this->send($this->notif($this->payment, 'settlement'))->assertOk();

        $p = $this->payment->fresh();
        $this->assertSame('review', $p->status);
        $this->assertSame('stok_kurang', $p->review_reason);
        $this->assertSame('menunggu_bayar', $this->order->fresh()->status);
        $this->assertSame(1, $this->stock(), 'stok tidak disentuh');
        $this->assertSame(0, StockMovement::count());
    }

    public function test_P07b_admin_approves_after_restock(): void
    {
        Product::whereKey($this->product->id)->update(['stock' => 1]);
        $this->send($this->notif($this->payment, 'settlement'))->assertOk();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Product::whereKey($this->product->id)->update(['stock' => 5]);
        $this->actingAs($admin)->post(route('admin.payments.approve', $this->payment->id))->assertSessionHas('success');

        $this->assertSame('verified', $this->payment->fresh()->status);
        $this->assertSame($admin->id, $this->payment->fresh()->verified_by);
        $this->assertSame('dikonfirmasi', $this->order->fresh()->status);
        $this->assertSame(3, $this->stock());
        $this->assertFalse($this->payment->fresh()->requiresAttention());
    }

    public function test_P08_paid_after_order_cancelled_is_flagged_for_refund(): void
    {
        app(OrderService::class)->cancel($this->order, 'tes', null, ['menunggu_bayar']);
        $this->assertSame('expired', $this->payment->fresh()->status, 'sesi ditutup saat pesanan batal');

        $this->send($this->notif($this->payment, 'settlement'))->assertOk();       // pelanggan sempat membayar

        $p = $this->payment->fresh();
        $this->assertSame('review', $p->status);
        $this->assertSame('dibayar_setelah_batal', $p->review_reason);
        $this->assertSame('batal', $this->order->fresh()->status);
        $this->assertSame(10, $this->stock());
    }

    public function test_P09_amount_mismatch_is_held(): void
    {
        $payload = $this->notif($this->payment, 'settlement', ['gross_amount' => '9000000.00']);
        $this->send($payload)->assertOk();

        $this->assertSame('nominal_tidak_cocok', $this->payment->fresh()->review_reason);
        $this->assertSame('menunggu_bayar', $this->order->fresh()->status);
    }

    public function test_P10_credit_card_challenge_is_held_then_auto_confirmed_after_approval(): void
    {
        $this->send($this->notif($this->payment, 'capture', ['fraud_status' => 'challenge']))->assertOk();
        $this->assertSame('fraud_challenge', $this->payment->fresh()->review_reason);
        $this->assertSame('menunggu_bayar', $this->order->fresh()->status);

        // Admin menyetujui di dashboard Midtrans -> Midtrans mengirim settlement -> otomatis terkonfirmasi.
        $this->send($this->notif($this->payment, 'settlement'))->assertOk();

        $this->assertSame('dikonfirmasi', $this->order->fresh()->status);
        $this->assertFalse($this->payment->fresh()->requiresAttention());
    }

    // ------------------------------------------------------------------ P-11 .. P-13: urutan & status akhir

    public function test_P11_late_pending_after_settlement_does_not_move_status_backwards(): void
    {
        $this->send($this->notif($this->payment, 'settlement'))->assertOk();
        $this->send($this->notif($this->payment, 'pending'))->assertOk();           // tiba terlambat / tidak berurutan

        $this->assertSame('verified', $this->payment->fresh()->status);
        $this->assertSame('dikonfirmasi', $this->order->fresh()->status);
        $this->assertSame('diabaikan_sudah_final', PaymentEvent::where('transaction_status', 'pending')->first()->outcome);
    }

    public function test_P12_expire_closes_attempt_and_customer_can_start_again(): void
    {
        $this->send($this->notif($this->payment, 'expire'))->assertOk();

        $this->assertSame('expired', $this->payment->fresh()->status);
        $this->assertSame('menunggu_bayar', $this->order->fresh()->status);

        Http::fake(['*snap/v1/transactions' => Http::response(['token' => 'tok-2', 'redirect_url' => 'https://pay.test/2'])]);
        $this->actingAs($this->user)->postJson(route('customer.orders.pay-online', $this->order->order_number))
            ->assertOk()->assertJson(['token' => 'tok-2']);

        $this->assertSame(2, $this->order->payments()->where('provider', 'midtrans')->count());
        $this->assertSame($this->order->order_number . '-P2', $this->order->payments()->latest('id')->first()->provider_order_id);
    }

    public function test_P13_refund_after_paid_is_flagged_and_admin_can_close_it(): void
    {
        $this->send($this->notif($this->payment, 'settlement'))->assertOk();
        $this->send($this->notif($this->payment, 'refund', ['status_code' => '200']))->assertOk();

        $p = $this->payment->fresh();
        $this->assertSame('verified', $p->status, 'status uang tidak diubah diam-diam');
        $this->assertSame('refund_chargeback', $p->review_reason);
        $this->assertTrue($p->requiresAttention());

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->post(route('admin.payments.resolve', $p->id), ['action' => 'dismiss'])->assertSessionHas('success');
        $this->assertFalse($p->fresh()->requiresAttention());
    }

    // ------------------------------------------------------------------ P-14 .. P-16: sisi pelanggan

    public function test_P14_double_click_on_pay_creates_one_session_and_reuses_token(): void
    {
        $this->payment->delete();                                                  // mulai dari nol
        Http::fake(['*snap/v1/transactions' => Http::response(['token' => 'tok-1', 'redirect_url' => 'https://pay.test/1'])]);

        $r1 = $this->actingAs($this->user)->postJson(route('customer.orders.pay-online', $this->order->order_number))->assertOk();
        $r2 = $this->actingAs($this->user)->postJson(route('customer.orders.pay-online', $this->order->order_number))->assertOk();

        $this->assertSame($r1->json('token'), $r2->json('token'));
        $this->assertSame(1, $this->order->payments()->where('provider', 'midtrans')->count());
        Http::assertSentCount(1);                                                  // gateway hanya dipanggil sekali
    }

    public function test_P15_cannot_start_online_payment_while_manual_proof_is_being_verified(): void
    {
        $this->payment->delete();
        $this->order->payments()->create(['method' => 'transfer_bca', 'amount' => $this->order->total, 'status' => 'pending']);

        $this->actingAs($this->user)->postJson(route('customer.orders.pay-online', $this->order->order_number))
            ->assertStatus(409);
    }

    public function test_P16_status_endpoint_reports_each_state_and_is_private_to_owner(): void
    {
        $url = route('customer.orders.payment-status', $this->order->order_number);

        $this->actingAs($this->user)->getJson($url)->assertOk()->assertJson(['state' => 'waiting', 'done' => false]);

        $this->send($this->notif($this->payment, 'settlement'))->assertOk();
        $this->actingAs($this->user)->getJson($url)->assertOk()->assertJson(['state' => 'paid', 'done' => true, 'confirmed' => true]);

        $other = User::factory()->create();
        $this->actingAs($other)->getJson($url)->assertNotFound();
    }

    public function test_P21_online_payment_wins_while_a_manual_proof_was_also_uploaded(): void
    {
        $manual = $this->order->payments()->create(['method' => 'transfer_bca', 'amount' => $this->order->total, 'status' => 'pending']);

        $this->send($this->notif($this->payment, 'settlement'))->assertOk();

        $this->assertSame('dikonfirmasi', $this->order->fresh()->status);
        $m = $manual->fresh();
        $this->assertSame('review', $m->status, 'bukti manual tidak boleh menggantung sebagai pending');
        $this->assertSame('pembayaran_ganda', $m->review_reason);
        $this->assertSame(8, $this->stock());
    }

    public function test_P22_manual_verification_wins_and_closes_the_open_gateway_session(): void
    {
        $manual = $this->order->payments()->create(['method' => 'transfer_bca', 'amount' => $this->order->total, 'status' => 'pending']);
        Http::fake(['*' => Http::response(['status_code' => '200'])]);

        app(OrderService::class)->confirmPayment($this->order, 1, $manual);

        $this->assertSame('expired', $this->payment->fresh()->status);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/' . $this->payment->provider_order_id . '/expire'));

        // Pelanggan yang terlanjur membayar di sesi tadi: tercatat ganda, tidak mengubah pesanan.
        $this->send($this->notif($this->payment, 'settlement'))->assertOk();
        $this->assertSame('duplicate', $this->payment->fresh()->status);
        $this->assertSame(8, $this->stock());
    }

    // ------------------------------------------------------------------ P-17 .. P-19: jaring pengaman terjadwal

    public function test_P17_auto_cancel_spares_orders_with_a_live_gateway_session_and_cancels_when_it_is_over(): void
    {
        Order::whereKey($this->order->id)->update(['created_at' => now()->subHours(30)]);

        $this->assertSame(0, app(OrderService::class)->cancelExpired(), 'sesi gateway masih hidup: jangan dibatalkan');
        $this->assertSame('menunggu_bayar', $this->order->fresh()->status);

        Payment::whereKey($this->payment->id)->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, app(OrderService::class)->cancelExpired());
        $this->assertSame('batal', $this->order->fresh()->status);
    }

    public function test_P18_reconcile_recovers_a_payment_whose_webhook_never_arrived(): void
    {
        $api = $this->notif($this->payment, 'settlement');
        Http::fake(['*/v2/*/status' => Http::response($api)]);

        $r = app(PaymentReconciler::class)->check($this->payment, sync: true);

        $this->assertSame('diproses', $r);
        $this->assertSame('dikonfirmasi', $this->order->fresh()->status);
        $this->assertSame(8, $this->stock());
        $this->assertSame('reconcile', PaymentEvent::first()->source);
    }

    public function test_P18b_reconcile_after_webhook_already_processed_is_a_noop(): void
    {
        $payload = $this->notif($this->payment, 'settlement');
        $this->send($payload)->assertOk();
        Http::fake(['*/v2/*/status' => Http::response($payload)]);

        $this->assertSame('sudah_diproses', app(PaymentReconciler::class)->check($this->payment->fresh(), sync: true));
        $this->assertSame(8, $this->stock());
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_P19_reconcile_treats_midtrans_404_body_as_not_created_and_expires_overdue_session(): void
    {
        // Midtrans membalas HTTP 200 dengan status_code "404" di dalam body untuk transaksi yang belum ada.
        Http::fake(['*/v2/*/status' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."])]);

        $this->assertSame('belum_ada', app(PaymentReconciler::class)->check($this->payment));
        $this->assertSame('waiting', $this->payment->fresh()->status);

        Payment::whereKey($this->payment->id)->update(['expires_at' => now()->subMinute()]);
        $this->assertSame('kedaluwarsa', app(PaymentReconciler::class)->check($this->payment->fresh()));
        $this->assertSame('expired', $this->payment->fresh()->status);
    }

    // ------------------------------------------------------------------ verifikasi ke Status API (produksi)

    public function test_P20_with_status_api_verification_the_gateways_status_wins_over_the_payload(): void
    {
        config(['services.midtrans.verify_status_api' => true]);
        $forged = $this->notif($this->payment, 'settlement');                      // tanda tangan sah, tetapi Midtrans bilang masih pending
        Http::fake(['*/v2/*/status' => Http::response($this->notif($this->payment, 'pending'))]);

        $this->send($forged)->assertOk();

        $this->assertSame('menunggu_bayar', $this->order->fresh()->status);
        $this->assertSame(10, $this->stock());
        $this->assertStringContainsString('Memakai status dari Midtrans', (string) PaymentEvent::first()->outcome_note);
    }

    public function test_P23_queue_mode_accepts_fast_and_processes_when_the_worker_runs(): void
    {
        config(['services.midtrans.process_inline' => false]);
        \Illuminate\Support\Facades\Queue::fake();

        $this->send($this->notif($this->payment, 'settlement'))->assertOk()->assertJson(['status' => 'accepted']);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessPaymentEvent::class);
        $this->assertSame('menunggu_bayar', $this->order->fresh()->status, 'belum diproses sampai worker berjalan');

        // Worker mengambil job-nya (dijalankan manual di sini).
        (new \App\Jobs\ProcessPaymentEvent(PaymentEvent::first()->id))->handle(app(\App\Services\Payments\PaymentEventProcessor::class));
        $this->assertSame('dikonfirmasi', $this->order->fresh()->status);
        $this->assertSame(8, $this->stock());
    }

    // ------------------------------------------------------------------ halaman admin

    public function test_admin_payment_pages_render_for_every_state(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Product::whereKey($this->product->id)->update(['stock' => 0]);
        $this->send($this->notif($this->payment, 'settlement'))->assertOk();     // -> review stok_kurang

        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk()->assertSee('Dibayar, stok tidak cukup');
        foreach (['perhatian', 'menunggu', 'otomatis', 'manual', 'semua'] as $tab) {
            $this->actingAs($admin)->get(route('admin.payments.index', ['tab' => $tab]))->assertOk();
        }
        $this->actingAs($admin)->get(route('admin.payments.show', $this->payment->id))->assertOk()->assertSee('Dibayar, stok tidak cukup')->assertSee('Setujui pembayaran')->assertSee('Riwayat notifikasi');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }
}
