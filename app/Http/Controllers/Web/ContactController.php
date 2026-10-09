<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index()
    {
        return view('customer.kontak.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        // Honeypot: kolom "website" disembunyikan dari manusia. Bot yang mengisinya
        // dianggap sukses (agar tidak belajar) tetapi pesannya tidak disimpan.
        if (filled($request->input('website'))) {
            return back()->with('success', 'Pesan Anda berhasil dikirim. Kami akan segera menghubungi Anda.');
        }

        ContactMessage::create($validated);

        return back()->with('success', 'Pesan Anda berhasil dikirim. Kami akan segera menghubungi Anda.');
    }
}
