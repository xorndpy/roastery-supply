<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu notifikasi dari gateway (atau hasil pengecekan terjadwal). Lihat migrasi untuk alasan desainnya.
 */
class PaymentEvent extends Model
{
    protected $fillable = [
        'provider', 'event_key', 'provider_order_id', 'payment_id', 'transaction_status', 'fraud_status',
        'source', 'signature_valid', 'payload', 'outcome', 'outcome_note', 'attempts', 'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Label singkat untuk tampilan admin. */
    public function outcomeLabel(): string
    {
        return match ($this->outcome) {
            'dikonfirmasi'          => 'Pesanan dikonfirmasi',
            'sudah_diproses'        => 'Sudah diproses sebelumnya (diabaikan)',
            'pembayaran_ganda'      => 'Dicatat sebagai pembayaran ganda',
            'perlu_perhatian'       => 'Ditahan, menunggu keputusan admin',
            'menunggu_pembayaran'   => 'Menunggu pelanggan membayar',
            'kedaluwarsa'           => 'Sesi pembayaran kedaluwarsa',
            'ditolak_gateway'       => 'Ditolak/dibatalkan di gateway',
            'diabaikan_sudah_final' => 'Diabaikan (pembayaran sudah final)',
            'order_tidak_dikenal'   => 'Pesanan tidak dikenal (diabaikan)',
            'status_tidak_dikenal'  => 'Status tidak dikenal (diabaikan)',
            null                    => $this->signature_valid ? 'Menunggu diproses' : 'Ditolak: tanda tangan tidak valid',
            default                 => $this->outcome,
        };
    }
}
