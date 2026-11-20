<?php

namespace App\Exports;

use App\Models\Expense;
use App\Models\Setting;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ProfitLossExport implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(private array $r) {}

    public function title(): string { return 'Laba Rugi'; }

    public function array(): array
    {
        $r = $this->r;
        $name = Setting::get('site_name') ?: config('app.name');
        $rows = [
            [strtoupper($name)], ['LAPORAN LABA RUGI'], ["Periode: {$r['from']} s/d {$r['to']}"], [],
            ['Keterangan', 'Jumlah (Rp)'],
            ['Penjualan produk (kotor)', $r['gross_sales']],
            ['Diskon voucher', -$r['discount']],
            ['Retur / refund disetujui', -$r['refund']],
            ['Jasa servis (berbayar)', $r['service_revenue']],
            ['PPN keluaran', -$r['ppn']],
            ['Pendapatan bersih', $r['revenue']],
            ['HPP', -$r['cogs']],
            ['Laba kotor', $r['gross_profit']],
        ];
        foreach ($r['expenses_by_category'] as $c => $v) {
            $rows[] = ['Biaya: ' . (Expense::CATEGORIES[$c] ?? $c), -$v];
        }
        $rows[] = ["Biaya payment gateway ({$r['gateway_count']} transaksi)", -$r['gateway_fees']];
        $rows[] = ['Laba bersih', $r['net_profit']];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $ws = $e->sheet->getDelegate();
            $name = Setting::get('site_name') ?: config('app.name');
            $last = $ws->getHighestRow();
            $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $ws->getStyle('A2')->getFont()->setBold(true)->setSize(12);
            $ws->getStyle('A5:B5')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1C1917']],
            ]);
            $ws->getStyle("B6:B$last")->getNumberFormat()->setFormatCode('#,##0;-#,##0');
            for ($i = 6; $i <= $last; $i++) {
                if (in_array($ws->getCell("A$i")->getValue(), ['Pendapatan bersih', 'Laba kotor', 'Laba bersih'], true)) {
                    $ws->getStyle("A$i:B$i")->getFont()->setBold(true);
                    $ws->getStyle("A$i:B$i")->getBorders()->getTop()->setBorderStyle('thin');
                }
            }
            $ws->getParent()->getProperties()->setCreator($name)->setLastModifiedBy($name)
                ->setCompany($name)->setTitle("Laporan Laba Rugi - $name");
        }];
    }
}
