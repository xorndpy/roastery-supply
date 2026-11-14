<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceTicket;
use App\Services\ServiceTicketService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceTicketController extends Controller
{
    public function __construct(private ServiceTicketService $service) {}

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'string', 'max:30'], 'search' => ['nullable', 'string', 'max:100'], 'warranty' => ['nullable', 'in:1,0']]);

        $q = ServiceTicket::query()->with('user:id,name');
        if (array_key_exists((string) $request->query('status'), ServiceTicket::STATUSES)) {
            $q->where('status', $request->query('status'));
        }
        if ($request->query('warranty') !== null && $request->query('warranty') !== '') {
            $q->where('in_warranty', $request->query('warranty') === '1');
        }
        if ($s = trim((string) $request->query('search'))) {
            $like = '%'.addcslashes($s, '%_\\').'%';
            $q->where(fn ($w) => $w->where('ticket_number', 'like', $like)->orWhere('product_name', 'like', $like)->orWhere('serial_number', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)));
        }

        return view('admin.service.index', [
            'tickets' => $q->latest()->paginate(20)->withQueryString(),
            'counts' => ServiceTicket::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function show(ServiceTicket $ticket)
    {
        $ticket->load(['user:id,name,email', 'warranty', 'order:id,order_number', 'events.actor:id,name']);

        return view('admin.service.show', compact('ticket'));
    }

    public function update(Request $request, ServiceTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(ServiceTicket::STATUSES))],
            'note' => ['nullable', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'estimate_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'final_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ]);

        $ticket->update(['admin_note' => $data['admin_note'] ?? null]);

        if ($data['status'] !== $ticket->status) {
            try {
                $this->service->transition($ticket, $data['status'], $data['note'] ?? null, $request->user(), [
                    'estimate_cost' => $data['estimate_cost'] ?? null, 'final_cost' => $data['final_cost'] ?? null,
                ]);
            } catch (DomainException $e) {
                return back()->withInput()->withErrors(['status' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'Tiket diperbarui.');
    }

    public function pay(Request $request, ServiceTicket $ticket)
    {
        try {
            $this->service->markPaid($ticket, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran servis dicatat.');
    }

    public function photo(ServiceTicket $ticket, int $n)
    {
        $path = ($ticket->photos ?? [])[$n - 1] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
