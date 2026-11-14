<?php

namespace App\Services;

use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\Warranty;
use App\Notifications\NewServiceTicketAlert;
use App\Notifications\ServiceTicketUpdated;
use App\Support\StoreContacts;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Pembuatan tiket servis dan perpindahan statusnya (dengan aturan alur, riwayat, dan notifikasi). */
class ServiceTicketService
{
    public function __construct(private WhatsAppService $wa) {}

    /** @param array<int,UploadedFile> $photos */
    public function create(User $user, array $data, array $photos = []): ServiceTicket
    {
        $warranty = null;
        if (! empty($data['warranty_id'])) {
            $warranty = Warranty::where('user_id', $user->id)->find($data['warranty_id']);
            if (! $warranty) {
                throw new DomainException('Garansi tidak ditemukan.');
            }
            if (ServiceTicket::where('warranty_id', $warranty->id)->whereNotIn('status', ['selesai', 'ditolak', 'dibatalkan'])->exists()) {
                throw new DomainException('Masih ada tiket servis yang berjalan untuk unit ini.');
            }
        }

        $paths = [];
        foreach (array_slice($photos, 0, 3) as $photo) {
            $paths[] = $photo->store('service', 'local');
        }

        $ticket = DB::transaction(function () use ($user, $data, $warranty, $paths) {
            $ticket = ServiceTicket::create([
                'ticket_number' => ServiceTicket::generateNumber(),
                'user_id' => $user->id,
                'warranty_id' => $warranty?->id,
                'order_id' => $warranty?->order_id,
                'product_name' => $warranty?->product_name ?? $data['product_name'],
                'serial_number' => $warranty?->serial_number ?? ($data['serial_number'] ?? null),
                'issue_type' => $data['issue_type'],
                'description' => $data['description'],
                'photos' => $paths ?: null,
                'contact_phone' => $data['contact_phone'],
                'status' => 'baru',
                'in_warranty' => $warranty?->isActive() ?? false,
            ]);
            $ticket->events()->create(['status' => 'baru', 'note' => 'Tiket dibuat oleh pelanggan.', 'actor_id' => $user->id]);

            return $ticket;
        });

        $this->alertStore($ticket);

        return $ticket;
    }

    /**
     * @param array{estimate_cost?:mixed,final_cost?:mixed} $extra
     */
    public function transition(ServiceTicket $ticket, string $to, ?string $note, ?User $actor, array $extra = []): ServiceTicket
    {
        $ticket = DB::transaction(function () use ($ticket, $to, $note, $actor, $extra) {
            $ticket = ServiceTicket::lockForUpdate()->findOrFail($ticket->id);

            if (! in_array($to, ServiceTicket::FLOW[$ticket->status] ?? [], true)) {
                throw new DomainException('Status '.$ticket->statusLabel().' tidak bisa langsung menjadi '.(ServiceTicket::STATUSES[$to] ?? $to).'.');
            }

            $attrs = ['status' => $to];

            if ($to === 'menunggu_persetujuan') {
                if ($ticket->in_warranty) {
                    throw new DomainException('Tiket masih dalam garansi: tidak perlu persetujuan biaya, lanjutkan ke Dikerjakan.');
                }
                $estimate = (float) ($extra['estimate_cost'] ?? 0);
                if ($estimate <= 0) {
                    throw new DomainException('Isi estimasi biaya (lebih dari 0) sebelum meminta persetujuan pelanggan.');
                }
                $attrs['estimate_cost'] = $estimate;
            }

            if ($to === 'selesai') {
                // Garansi = gratis. Di luar garansi: biaya akhir (bawaan = estimasi yang disetujui, bisa Rp 0).
                $attrs['final_cost'] = $ticket->in_warranty ? 0 : max(0, (float) ($extra['final_cost'] ?? $ticket->estimate_cost ?? 0));
            }

            if (in_array($to, ['selesai', 'ditolak', 'dibatalkan'], true)) {
                $attrs['closed_at'] = now();
            }

            $ticket->update($attrs);
            $ticket->events()->create(['status' => $to, 'note' => $note, 'actor_id' => $actor?->id]);

            return $ticket;
        });

        $this->alertCustomer($ticket, $note);

        return $ticket;
    }

    /** Tandai biaya servis sudah dibayar (masuk laporan laba rugi sebagai jasa servis). */
    public function markPaid(ServiceTicket $ticket, ?User $actor): ServiceTicket
    {
        if ($ticket->status !== 'selesai') {
            throw new DomainException('Pembayaran hanya bisa dicatat setelah servis selesai.');
        }
        if ($ticket->in_warranty || (float) $ticket->final_cost <= 0) {
            throw new DomainException('Tiket ini tidak ada biaya yang perlu dibayar.');
        }
        if ($ticket->paid_at) {
            throw new DomainException('Sudah ditandai dibayar.');
        }

        $ticket->update(['paid_at' => now()]);
        $ticket->events()->create(['status' => 'selesai', 'note' => 'Pembayaran biaya servis diterima.', 'actor_id' => $actor?->id]);

        return $ticket;
    }

    private function alertStore(ServiceTicket $ticket): void
    {
        $ticket->loadMissing('user');
        if ($to = StoreContacts::emails()) {
            try {
                Notification::route('mail', $to)->notify(new NewServiceTicketAlert($ticket));
            } catch (Throwable $e) {
                report($e);
            }
        }
        $this->wa->toStore("Tiket servis baru {$ticket->ticket_number}\n{$ticket->product_name}".($ticket->in_warranty ? ' (MASIH GARANSI)' : '')."\nMasalah: {$ticket->issueLabel()}\nPelanggan: ".($ticket->user->name ?? '-').' '.$ticket->contact_phone);
    }

    private function alertCustomer(ServiceTicket $ticket, ?string $note): void
    {
        $ticket->loadMissing('user');
        if ($ticket->user) {
            try {
                $ticket->user->notify(new ServiceTicketUpdated($ticket, $note));
            } catch (Throwable $e) {
                report($e);
            }
        }
        $msg = "Servis {$ticket->ticket_number} ({$ticket->product_name}): {$ticket->statusLabel()}.";
        if ($ticket->status === 'menunggu_persetujuan' && $ticket->estimate_cost) {
            $msg .= ' Estimasi biaya Rp '.number_format($ticket->estimate_cost, 0, ',', '.').' - mohon setujui/tolak di '.route('customer.service.show', $ticket->ticket_number);
        } elseif ($note) {
            $msg .= " Catatan: {$note}";
        }
        $this->wa->send($ticket->contact_phone, $msg);
    }
}
