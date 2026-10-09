<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactMessage::query()->latest('id');

        if ($request->status === 'unread') {
            $query->where('is_read', false);
        }
        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%"));
        }

        $messages = $query->paginate(20)->withQueryString();
        $unread = ContactMessage::where('is_read', false)->count();

        return view('admin.messages.index', compact('messages', 'unread'));
    }

    /** Membuka pesan otomatis menandainya sudah dibaca. */
    public function show(ContactMessage $message)
    {
        if (!$message->is_read) {
            $message->update(['is_read' => true]);
        }

        return view('admin.messages.show', compact('message'));
    }

    /** Toggle dibaca / belum dibaca (PRD 6.7). */
    public function markAsRead(ContactMessage $message)
    {
        $message->update(['is_read' => !$message->is_read]);

        return back()->with('success', $message->is_read ? 'Ditandai sudah dibaca.' : 'Ditandai belum dibaca.');
    }

    public function markReplied(ContactMessage $message)
    {
        $message->update(['replied_at' => $message->replied_at ? null : now(), 'is_read' => true]);

        return back()->with('success', $message->replied_at ? 'Ditandai sudah dibalas.' : 'Tanda dibalas dibatalkan.');
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('success', 'Pesan dihapus.');
    }
}
