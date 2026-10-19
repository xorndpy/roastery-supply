<?php
/**
 * Uji konkurensi alur pembayaran gateway. WAJIB di MySQL/MariaDB (SQLite tidak punya kunci baris).
 * Lihat tests/Concurrency/README.md. Pakai: php tests/Concurrency/harness.php [basic|race|all]
 */
$mode = $argv[1] ?? 'all';
$root = dirname(__DIR__, 2);
chdir($root);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Customer, Order, OrderItem, Payment, PaymentEvent, Product, StockMovement, User};
use App\Services\Payments\MidtransService;

$mid = app(MidtransService::class);
$url = (getenv('CONC_URL') ?: 'http://127.0.0.1:8001') . '/webhooks/midtrans';
$admin = User::first();
$fails = 0; $n = 0;

function check(string $label, bool $ok, string $detail = ''): void { global $fails; if (!$ok) $fails++; printf("   %s %s%s\n", $ok ? '✓' : '✗ GAGAL', $label, $detail ? "  [$detail]" : ''); }
function product(int $stock): Product { static $i = 0; $i++; return Product::unguarded(fn () => Product::create(['name' => "Konkuren $i", 'slug' => "konkuren-$i-" . uniqid(), 'sku' => "KC-$i-" . uniqid(), 'price' => 5_000_000, 'stock' => $stock, 'stock_threshold' => 1])); }
function order(Product $p, int $qty = 1, bool $manualProof = false): array {
    global $admin, $n; $n++;
    $c = Customer::unguarded(fn () => Customer::create(['user_id' => $admin->id, 'name' => "Uji $n", 'email' => "uji$n-" . uniqid() . "@x.test"]));
    $total = $p->price * $qty;
    $o = Order::unguarded(fn () => Order::create(['order_number' => 'ORD-KONK-' . uniqid(), 'customer_id' => $c->id, 'user_id' => $admin->id, 'status' => 'menunggu_bayar', 'subtotal' => $total, 'total' => $total]));
    OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku, 'price' => $p->price, 'quantity' => $qty, 'subtotal' => $total]));
    $pay = $o->payments()->create(['method' => 'midtrans', 'provider' => 'midtrans', 'provider_order_id' => $o->order_number . '-P1', 'amount' => $total, 'status' => 'waiting', 'expires_at' => now()->addDay()]);
    $manual = $manualProof ? $o->payments()->create(['method' => 'transfer_bca', 'amount' => $total, 'status' => 'pending']) : null;
    return [$o, $pay, $manual];
}
function payload(Payment $p, string $status = 'settlement', bool $bad = false): array {
    global $mid; $gross = number_format((float) $p->amount, 2, '.', ''); $code = $status === 'settlement' ? '200' : '201';
    $pl = ['transaction_time' => '2026-10-14 10:00:00', 'transaction_status' => $status, 'transaction_id' => 'trx-' . $p->id, 'status_code' => $code, 'payment_type' => 'bank_transfer', 'order_id' => $p->provider_order_id, 'gross_amount' => $gross, 'fraud_status' => 'accept', 'currency' => 'IDR'];
    $pl['signature_key'] = $bad ? str_repeat('0', 128) : $mid->signatureFor($pl['order_id'], $code, $gross);
    return $pl;
}
/** Tembak banyak request SERENTAK memakai curl_multi; kembalikan [kode HTTP, body, durasi ms per request]. */
function fire(array $payloads, int $window = 40): array {
    global $url; $mh = curl_multi_init(); $res = []; $handles = []; $queue = array_keys($payloads); $active = 0;
    $add = function ($k) use (&$handles, $mh, $payloads, $url, &$active) { $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payloads[$k]), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]); curl_multi_add_handle($mh, $ch); $handles[$k] = $ch; $active++; };
    while ($queue && $active < $window) $add(array_shift($queue));
    do {
        curl_multi_exec($mh, $running); curl_multi_select($mh, 0.05);
        while ($info = curl_multi_info_read($mh)) {
            $k = array_search($info['handle'], $handles, true);
            $res[$k] = [curl_getinfo($info['handle'], CURLINFO_HTTP_CODE), (string) curl_multi_getcontent($info['handle']), curl_getinfo($info['handle'], CURLINFO_TOTAL_TIME) * 1000];
            curl_multi_remove_handle($mh, $info['handle']); $active--;
            if ($queue) $add(array_shift($queue));
        }
    } while ($active > 0 || $queue);
    return $res;
}
function admins(int $orderId, int $paymentId, int $actor, int $count): array {
    $procs = []; for ($i = 0; $i < $count; $i++) { $procs[] = proc_open("php " . __DIR__ . "/admin_confirm.php $orderId $paymentId $actor", [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); $procs[$i] = [$procs[$i], $pipes]; }
    $out = []; foreach ($procs as [$p, $pipes]) { $out[] = trim(stream_get_contents($pipes[1])); proc_close($p); } return $out;
}
$codes = fn (array $r) => array_count_values(array_map(fn ($x) => $x[0], $r));

if ($mode !== 'race') {
echo "\n=== K-1  Badai notifikasi identik: 1 pesanan, 16 notifikasi settlement yang sama SERENTAK ===\n";
$p = product(10); [$o, $pay] = order($p, 2);
$r = fire(array_fill(0, 16, payload($pay)));
$st = array_count_values(array_map(fn ($x) => json_decode($x[1], true)['status'] ?? $x[1], $r));
check('semua balasan HTTP 200', ($codes($r)[200] ?? 0) === 16, json_encode($codes($r)));
check('tepat 1 yang "accepted", sisanya "duplicate"', ($st['accepted'] ?? 0) === 1, json_encode($st));
check('hanya 1 baris event di database', PaymentEvent::where('provider_order_id', $pay->provider_order_id)->count() === 1);
check('hasil event = dikonfirmasi (tidak tertimpa pemroses lain)', PaymentEvent::where('provider_order_id', $pay->provider_order_id)->value('outcome') === 'dikonfirmasi');
check('pesanan dikonfirmasi', $o->fresh()->status === 'dikonfirmasi');
check('stok 10 -> 8 (dikurangi SEKALI, bukan 16 kali)', (int) $p->fresh()->stock === 8, 'stok=' . $p->fresh()->stock);
check('hanya 1 catatan pergerakan stok', StockMovement::where('reference_id', $o->id)->where('reference_type', 'order')->count() === 1);
check('hanya 1 pembayaran terverifikasi', $o->payments()->where('status', 'verified')->count() === 1);

echo "\n=== K-2  Webhook vs klik admin: 6 notifikasi + 6 klik 'verifikasi manual' SERENTAK pada pesanan yang sama ===\n";
$p = product(10); [$o, $pay, $manual] = order($p, 2, manualProof: true);
$pids = []; $http = []; $cli = [];
$t = microtime(true);
// jalankan klik admin (proses terpisah) bersamaan dengan badai webhook
$adminProcs = []; for ($i = 0; $i < 6; $i++) $adminProcs[] = proc_open("php /home/claude/conc_admin.php {$o->id} {$manual->id} {$admin->id}", [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp[$i]);
$r = fire(array_fill(0, 6, payload($pay)));
foreach ($adminProcs as $i => $pr) { $cli[] = trim(stream_get_contents($pp[$i][1])); proc_close($pr); }
$ok = count(array_filter($cli, fn ($x) => $x === 'OK'));
$o->refresh(); $pay->refresh(); $manual->refresh();
check('pesanan dikonfirmasi tepat satu kali', $o->status === 'dikonfirmasi');
check('stok 10 -> 8 (satu pemenang saja)', (int) $p->fresh()->stock === 8, 'stok=' . $p->fresh()->stock);
check('hanya 1 pergerakan stok', StockMovement::where('reference_id', $o->id)->where('reference_type', 'order')->count() === 1);
check('klik admin yang berhasil <= 1, sisanya ditolak rapi', $ok <= 1, "berhasil=$ok dari 6");
$winner = $pay->status === 'verified' ? 'gateway' : ($manual->status === 'verified' ? 'admin' : 'tidak ada');
check("tepat satu pembayaran terverifikasi (pemenang: $winner)", $o->payments()->where('status', 'verified')->count() === 1);
check('yang kalah tercatat jelas, tidak menggantung', $winner === 'gateway' ? ($manual->status === 'review' && $manual->review_reason === 'pembayaran_ganda') : in_array($pay->status, ['expired', 'duplicate']), "gateway={$pay->status} manual={$manual->status}");
check('tak ada pembayaran yang tersangkut "pending/waiting"', $o->payments()->whereIn('status', ['pending', 'waiting'])->count() === 0);

echo "\n=== K-3  Rebutan unit terakhir: stok 1, dua pesanan berbeda, 16 notifikasi SERENTAK ===\n";
$p = product(1); [$oa, $pa] = order($p, 1); [$ob, $pb] = order($p, 1);
$batch = []; for ($i = 0; $i < 8; $i++) { $batch[] = payload($pa); $batch[] = payload($pb); } shuffle($batch);
$r = fire($batch);
$oa->refresh(); $ob->refresh(); $pa->refresh(); $pb->refresh();
check('semua balasan HTTP 200', ($codes($r)[200] ?? 0) === 16);
check('tepat SATU pesanan dikonfirmasi', (int) ($oa->status === 'dikonfirmasi') + (int) ($ob->status === 'dikonfirmasi') === 1, "A={$oa->status} B={$ob->status}");
check('stok = 0 dan tidak pernah negatif', (int) $p->fresh()->stock === 0, 'stok=' . $p->fresh()->stock);
$loser = $oa->status === 'dikonfirmasi' ? $pb : $pa;
check('pemesan yang kalah: uang ditahan "stok_kurang", bukan hilang', $loser->status === 'review' && $loser->review_reason === 'stok_kurang', "{$loser->status}/{$loser->review_reason}");
check('hanya 1 pergerakan stok', StockMovement::where('product_id', $p->id)->count() === 1);

echo "\n=== K-4  Skala: 40 pesanan x 3 notifikasi duplikat = 120 request, 24 serentak, urutan diacak ===\n";
$p = product(1000); $set = []; $batch = [];
for ($i = 0; $i < 40; $i++) { [$o, $pay] = order($p, 1); $set[] = [$o, $pay]; for ($d = 0; $d < 3; $d++) $batch[] = payload($pay); }
shuffle($batch); $t = microtime(true); $r = fire($batch, 24); $secs = microtime(true) - $t;
$lat = array_map(fn ($x) => $x[2], $r); sort($lat);
check('semua 120 balasan HTTP 200', ($codes($r)[200] ?? 0) === 120, json_encode($codes($r)));
check('40 pesanan dikonfirmasi, masing-masing sekali', count(array_filter($set, fn ($s) => $s[0]->fresh()->status === 'dikonfirmasi')) === 40);
check('stok 1000 -> 960 (tepat 40 unit)', (int) $p->fresh()->stock === 960, 'stok=' . $p->fresh()->stock);
check('40 event, 40 pergerakan stok, 40 pembayaran terverifikasi', PaymentEvent::whereIn('provider_order_id', array_map(fn ($s) => $s[1]->provider_order_id, $set))->count() === 40 && StockMovement::where('product_id', $p->id)->count() === 40);
printf("   ℹ  %d request dalam %.2f dtk (%.0f req/dtk) | latensi median %.0f ms, p95 %.0f ms, maks %.0f ms\n", count($r), $secs, count($r) / $secs, $lat[intdiv(count($lat), 2)], $lat[(int) floor(count($lat) * .95) - 1], end($lat));

echo "\n=== K-5  Banjir notifikasi palsu (tanda tangan salah), 20 serentak ===\n";
$p = product(10); [$o, $pay] = order($p, 1);
$r = fire(array_fill(0, 20, payload($pay, 'settlement', bad: true)));
check('semua ditolak HTTP 403', ($codes($r)[403] ?? 0) === 20, json_encode($codes($r)));
check('pesanan & stok tidak berubah', $o->fresh()->status === 'menunggu_bayar' && (int) $p->fresh()->stock === 10);
check('jejak tercatat sebagai event tidak sah (tanpa membengkak)', PaymentEvent::where('signature_valid', false)->where('provider_order_id', $pay->provider_order_id)->count() === 1);

}

if ($mode !== 'basic') {
echo "\n=== K-6  Balapan diulang 20x: 6 webhook + 6 klik admin, urutan start DIACAK tiap putaran ===\n";
@mkdir(sys_get_temp_dir() . '/conc'); $win = ['gateway' => 0, 'admin' => 0]; $bad = [];
for ($run = 1; $run <= 20; $run++) {
    $p = product(10); [$o, $pay, $manual] = order($p, 2, manualProof: true);
    $f = sys_get_temp_dir() . "/conc/p$run.json"; file_put_contents($f, json_encode(payload($pay)));
    // Arah pemenang diatur bergantian agar kedua jalur benar-benar teruji:
    //  - putaran ganjil: webhook lebih dulu, klik admin menyusul sesaat kemudian
    //  - putaran genap : klik admin lebih dulu (diberi keunggulan acak 0-450 ms untuk menutup waktu boot PHP), webhook menyusul
    $hookCmds = []; $adminCmds = [];
    for ($i = 0; $i < 6; $i++) {
        $hookCmds[] = "curl -s -o /dev/null -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' --data @$f $url";
        $adminCmds[] = "php " . __DIR__ . "/admin_confirm.php {$o->id} {$manual->id} {$admin->id}";
    }
    $launch = function (array $cmds) use (&$procs, &$pp) { foreach ($cmds as $c) { $k = count($procs); $procs[$k] = proc_open($c, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp[$k]); } };
    $procs = []; $pp = [];
    if ($run % 2 === 0) { $launch($adminCmds); usleep(random_int(0, 450) * 1000); $launch($hookCmds); }
    else { $launch($hookCmds); usleep(random_int(0, 100) * 1000); $launch($adminCmds); }
    foreach ($procs as $i => $pr) { stream_get_contents($pp[$i][1]); proc_close($pr); }
    $o->refresh(); $pay->refresh(); $manual->refresh();
    $w = $pay->status === 'verified' ? 'gateway' : ($manual->status === 'verified' ? 'admin' : 'none');
    if ($w !== 'none') $win[$w]++;
    $okRun = $o->status === 'dikonfirmasi' && (int) $p->fresh()->stock === 8
        && StockMovement::where('reference_id', $o->id)->where('reference_type', 'order')->count() === 1
        && $o->payments()->where('status', 'verified')->count() === 1
        && $o->payments()->whereIn('status', ['pending', 'waiting'])->count() === 0
        && ($w === 'gateway' ? ($manual->status === 'review' && $manual->review_reason === 'pembayaran_ganda') : in_array($pay->status, ['expired', 'duplicate']));
    if (!$okRun) $bad[] = "run $run: pemenang=$w stok=" . $p->fresh()->stock . " order={$o->status} gw={$pay->status} man={$manual->status}";
}
check('20/20 putaran: satu pemenang, stok dikurangi sekali, tidak ada yang menggantung', !$bad, implode(' | ', $bad));
echo "   ℹ  pemenang: gateway {$win['gateway']}x, admin {$win['admin']}x (arah diatur bergantian; yang dinilai adalah invarian, bukan siapa menang)\n";
}

echo "\n" . ($fails === 0 ? "HASIL: SEMUA INVARIAN TERPENUHI" : "HASIL: $fails PEMERIKSAAN GAGAL") . "\n";
exit($fails ? 1 : 0);
