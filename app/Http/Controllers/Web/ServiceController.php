<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceTicketRequest;
use App\Models\ServiceTicket;
use App\Models\Warranty;
use App\Services\ServiceTicketService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Garansi & servis dari sisi pelanggan. Semua data dibatasi ke pemiliknya. */
class ServiceController extends Controller
{
    public function __construct(private ServiceTicketService $service) {}

    private function ticketOf(Request $request, string $number): ServiceTicket
    {
        return ServiceTicket::where('ticket_number', $number)->where('user_id', $request->user()->id)->firstOrFail();
    }

    public function index(Request $request)
    {
        $uid = $request->user()->id;

        return view('customer.service.index', [
            'warranties' => Warranty::where('user_id', $uid)->orderByDesc('expires_at')->get(),
            'tickets' => ServiceTicket::where('user_id', $uid)->latest()->get(),
        ]);
    }

    public function create(Request $request)
    {
        $warranties = Warranty::where('user_id', $request->user()->id)->where('status', 'active')->orderByDesc('expires_at')->get();

        return view('customer.service.create', ['warranties' => $warranties, 'selected' => (int) $request->query('warranty')]);
    }

    public function store(StoreServiceTicketRequest $request)
    {
        try {
            $ticket = $this->service->create($request->user(), $request->validated(), $request->file('photos', []));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['warranty_id' => $e->getMessage()]);
        }

        return redirect()->route('customer.service.show', $ticket->ticket_number)->with('success', 'Tiket servis dibuat. Kami akan menghubungimu.');
    }

    public function show(Request $request, string $number)
    {
        $ticket = $this->ticketOf($request, $number);
        $events = $ticket->events()->where('is_public', true)->with('actor:id,name')->get();

        return view('customer.service.show', compact('ticket', 'events'));
    }

    public function cancel(Request $request, string $number)
    {
        $ticket = $this->ticketOf($request, $number);
        try {
            $this->service->transition($ticket, 'dibatalkan', 'Dibatalkan oleh pelanggan.', $request->user());
        } catch (DomainException $e) {
            return back()->with('error', 'Tiket ini sudah tidak bisa dibatalkan.');
        }

        return back()->with('success', 'Tiket dibatalkan.');
    }

    /** Setujui/tolak estimasi biaya (hanya tiket di luar garansi yang menunggu persetujuan). */
    public function respond(Request $request, string $number)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,decline']]);
        $ticket = $this->ticketOf($request, $number);

        if ($ticket->status !== 'menunggu_persetujuan') {
            return back()->with('error', 'Tiket ini tidak sedang menunggu persetujuan.');
        }

        $approve = $data['decision'] === 'approve';
        $this->service->transition($ticket, $approve ? 'dikerjakan' : 'dibatalkan', $approve ? 'Estimasi biaya disetujui pelanggan.' : 'Estimasi biaya ditolak pelanggan.', $request->user());

        return back()->with('success', $approve ? 'Terima kasih, perbaikan akan dikerjakan.' : 'Tiket dibatalkan.');
    }

    public function photo(Request $request, string $number, int $n)
    {
        $path = ($this->ticketOf($request, $number)->photos ?? [])[$n - 1] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
