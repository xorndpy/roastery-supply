<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Enumerable
    {
        return Order::with(['customer', 'items'])
            ->whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'No. Order',
            'Tanggal',
            'Customer',
            'Telepon',
            'Jumlah Item',
            'Subtotal',
            'Diskon',
            'Ongkir',
            'Total',
            'Status',
        ];
    }

    public function map($order): array
    {
        static $no = 0;
        $no++;

        $totalItems = $order->items->sum('quantity');

        return [
            $no,
            $order->order_number,
            $order->created_at->format('d M Y, H:i'),
            $order->customer->name ?? 'Guest',
            $order->customer->phone ?? '-',
            $totalItems,
            $order->subtotal,
            $order->discount,
            $order->shipping_cost,
            $order->total,
            str_replace('_', ' ', strtoupper($order->status)),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F97316']],
            ],
        ];
    }
}