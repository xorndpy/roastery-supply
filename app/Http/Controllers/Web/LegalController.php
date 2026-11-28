<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ReturnService;

/** Halaman kebijakan: privasi, syarat & ketentuan, retur & garansi. */
class LegalController extends Controller
{
    /** Ubah tanggal ini setiap kali isi kebijakan diperbarui. */
    private const UPDATED = '28 November 2026';

    public function privacy()
    {
        return view('customer.legal.privacy', ['updated' => self::UPDATED]);
    }

    public function terms()
    {
        return view('customer.legal.terms', [
            'updated' => self::UPDATED,
            'returnDays' => ReturnService::WINDOW_DAYS,
        ]);
    }
}
