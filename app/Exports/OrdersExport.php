<?php

namespace App\Exports;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithCustomStartCell, WithEvents
{
    private int $no = 0;

    public function __construct(private Collection $orders, private bool $withMoney = true) {}

    public function collection(): Collection { return $this->orders; }
    public function title(): string { return 'Daftar Pesanan'; }
    public function startCell(): string { return 'A5'; }

    public function headings(): array
    {
        return array_values(array_filter([
            'No', 'No. Pesanan', 'Tanggal', 'Pelanggan', 'Telepon', 'Status', 'Pembayaran', 'Metode', 'Kurir', 'Resi',
            $this->withMoney ? 'Total' : null, 'Terlambat (jam)',
        ], fn ($v) => $v !== null));
    }

    public function map($o): array
    {
        $row = [
            ++$this->no, $o->order_number, wib($o->created_at, 'd M Y H:i', true), $o->customer->name ?? 'Guest', $o->customer->phone ?? '-',
            $o->status_label, $o->payment?->statusLabel() ?? 'Belum bayar', $o->payment?->methodLabel() ?? '-',
            $o->shipment?->courier ?? '-', $o->shipment?->tracking_number ?? '-',
        ];
        if ($this->withMoney) {
            $row[] = (float) $o->total;
        }
        $row[] = $o->late_hours ?? '';

        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        return [5 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1C1917']]]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $name = Setting::get('site_name') ?: config('app.name');
            $ws = $e->sheet->getDelegate();
            $ws->setCellValue('A1', strtoupper($name));
            $ws->setCellValue('A2', 'DAFTAR PESANAN');
            $ws->setCellValue('A3', 'Dicetak: ' . now('Asia/Jakarta')->format('d M Y H:i') . ' - ' . $this->orders->count() . ' pesanan');
            $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $ws->getStyle('A2')->getFont()->setBold(true)->setSize(12);
            if ($this->withMoney) {
                $ws->getStyle('K6:K' . max(6, $ws->getHighestRow()))->getNumberFormat()->setFormatCode('#,##0');
            }
            $ws->freezePane('A6');
            $ws->getParent()->getProperties()->setCreator($name)->setLastModifiedBy($name)->setCompany($name)->setTitle("Daftar Pesanan - $name");
        }];
    }
}
