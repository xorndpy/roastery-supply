<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithCustomStartCell, WithEvents
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
            (float) $order->subtotal,
            (float) $order->discount,
            (float) $order->shipping_cost,
            (float) $order->total,
            str_replace('_', ' ', strtoupper($order->status)),
        ];
    }

    public function title(): string { return 'Laporan Penjualan'; }

    public function startCell(): string { return 'A6'; }

    public function styles(Worksheet $sheet): array
    {
        return [
            6 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1C1917']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $name = \App\Models\Setting::get('site_name') ?: config('app.name');
            $ws = $e->sheet->getDelegate();
            $ws->setCellValue('A1', strtoupper($name));
            $ws->setCellValue('A2', 'LAPORAN PENJUALAN');
            $ws->setCellValue('A3', 'Periode: ' . $this->startDate->format('d M Y') . ' s/d ' . $this->endDate->format('d M Y'));
            $ws->setCellValue('A4', 'Dicetak: ' . now('Asia/Jakarta')->format('d M Y H:i'));
            $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $ws->getStyle('A2')->getFont()->setBold(true)->setSize(12);

            $last = $ws->getHighestRow();
            if ($last >= 7) {
                $t = $last + 1;
                $ws->setCellValue("F$t", 'TOTAL');
                foreach (['G', 'H', 'I', 'J'] as $c) {
                    $ws->setCellValue("$c$t", "=SUM({$c}7:{$c}{$last})");
                }
                $ws->getStyle("F$t:J$t")->getFont()->setBold(true);
                $ws->getStyle("A$t:K$t")->getBorders()->getTop()->setBorderStyle('thin');
                $ws->getStyle("G7:J$t")->getNumberFormat()->setFormatCode('#,##0');
            }
            $ws->freezePane('A7');
            $ws->getParent()->getProperties()->setCreator($name)->setLastModifiedBy($name)
                ->setCompany($name)->setTitle("Laporan Penjualan - $name");
        }];
    }
}
