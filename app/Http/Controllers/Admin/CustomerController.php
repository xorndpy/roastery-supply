<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Pelanggan (PRD 6.3): daftar + pencarian, segmentasi, detail + riwayat belanja, export.
 *
 * "Belanja" = order yang pembayarannya sudah dikonfirmasi (bukan menunggu_bayar / batal).
 * Segmen:
 *  - baru       : terdaftar <= 30 hari
 *  - aktif      : ada belanja dalam 90 hari terakhir
 *  - dormant    : pernah belanja, tapi tidak ada dalam 90 hari terakhir
 *  - belum_beli : belum pernah ada belanja
 */
class CustomerController extends Controller
{
    private const PAID = ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'];
    private const NEW_DAYS = 30;
    private const ACTIVE_DAYS = 90;

    public function index(Request $request)
    {
        $customers = $this->query($request)->paginate(20)->withQueryString();

        $segments = $this->segmentLabels();
        $counts = [
            'all' => Customer::count(),
            'baru' => $this->applySegment(Customer::query(), 'baru')->count(),
            'aktif' => $this->applySegment(Customer::query(), 'aktif')->count(),
            'dormant' => $this->applySegment(Customer::query(), 'dormant')->count(),
            'belum_beli' => $this->applySegment(Customer::query(), 'belum_beli')->count(),
        ];

        return view('admin.customers.index', compact('customers', 'segments', 'counts'));
    }

    public function show(Customer $customer)
    {
        $paid = $customer->orders()->whereIn('status', self::PAID);

        $summary = [
            'orders' => $customer->orders()->count(),
            'paid_orders' => (clone $paid)->count(),
            'total_spent' => (clone $paid)->sum('total'),
            'last_order_at' => (clone $paid)->max('created_at'),
        ];
        $segment = $this->segmentOf($customer, $summary['paid_orders'], $summary['last_order_at']);

        $orders = $customer->orders()->latest('id')->paginate(10);

        return view('admin.customers.show', compact('customer', 'summary', 'segment', 'orders') + ['segments' => $this->segmentLabels()]);
    }

    /** Export CSV (dibuka langsung di Excel). Mengikuti filter yang sedang aktif. */
    public function export(Request $request)
    {
        $rows = $this->query($request)->get();
        $filename = 'pelanggan-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            fputcsv($out, ['Nama', 'Email', 'Telepon', 'Kota', 'Provinsi', 'Jumlah Belanja', 'Total Belanja (Rp)', 'Belanja Terakhir', 'Segmen', 'Terdaftar']);

            foreach ($rows as $c) {
                fputcsv($out, array_map([$this, 'safeCell'], [
                    $c->name, $c->email, $c->phone, $c->city, $c->province,
                    (int) $c->paid_orders_count,
                    (int) $c->total_spent,
                    $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->format('Y-m-d') : '',
                    $this->segmentLabels()[$this->segmentOf($c, $c->paid_orders_count, $c->last_order_at)],
                    $c->created_at->format('Y-m-d'),
                ]));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------

    private function query(Request $request): Builder
    {
        $query = Customer::query()
            ->withCount(['orders as paid_orders_count' => fn ($q) => $q->whereIn('status', self::PAID)])
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereIn('status', self::PAID)], 'total')
            ->withMax(['orders as last_order_at' => fn ($q) => $q->whereIn('status', self::PAID)], 'created_at')
            ->latest('id');

        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        if (array_key_exists((string) $request->segment, $this->segmentLabels())) {
            $this->applySegment($query, $request->segment);
        }

        return $query;
    }

    private function applySegment(Builder $query, string $segment): Builder
    {
        $paid = fn ($q) => $q->whereIn('status', self::PAID);
        $recent = fn ($q) => $q->whereIn('status', self::PAID)->where('created_at', '>=', now()->subDays(self::ACTIVE_DAYS));

        return match ($segment) {
            'baru'       => $query->where('created_at', '>=', now()->subDays(self::NEW_DAYS)),
            'aktif'      => $query->whereHas('orders', $recent),
            'dormant'    => $query->whereHas('orders', $paid)->whereDoesntHave('orders', $recent),
            'belum_beli' => $query->whereDoesntHave('orders', $paid),
            default      => $query,
        };
    }

    /** Segmen utama satu pelanggan (urutan prioritas: aktif > dormant > baru > belum_beli). */
    private function segmentOf(Customer $c, int|string|null $paidCount, mixed $lastOrderAt): string
    {
        if ((int) $paidCount > 0) {
            return \Illuminate\Support\Carbon::parse($lastOrderAt)->gte(now()->subDays(self::ACTIVE_DAYS)) ? 'aktif' : 'dormant';
        }

        return $c->created_at->gte(now()->subDays(self::NEW_DAYS)) ? 'baru' : 'belum_beli';
    }

    private function segmentLabels(): array
    {
        return ['baru' => 'Baru', 'aktif' => 'Aktif', 'dormant' => 'Dormant', 'belum_beli' => 'Belum beli'];
    }

    /** Cegah CSV injection: sel yang diawali = + - @ dianggap rumus oleh Excel. */
    private function safeCell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
