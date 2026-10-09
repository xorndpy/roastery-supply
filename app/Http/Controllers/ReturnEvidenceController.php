<?php

namespace App\Http\Controllers;

use App\Models\ReturnOrder;
use Illuminate\Support\Facades\Storage;

/**
 * Foto bukti retur bersifat privat: hanya pemilik retur atau admin/staff berizin view_returns.
 */
class ReturnEvidenceController extends Controller
{
    public function admin(ReturnOrder $returnOrder, int $n)
    {
        return $this->serve($returnOrder, $n);
    }

    public function customer(int $id, int $n)
    {
        $return = ReturnOrder::whereHas('order', fn ($q) => $q->where('user_id', auth()->id()))->findOrFail($id);

        return $this->serve($return, $n);
    }

    private function serve(ReturnOrder $return, int $n)
    {
        $path = ($return->evidence ?? [])[$n] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
