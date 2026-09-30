<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('customer.profile');
    }

    public function update(Request $request)
    {
        return redirect()->back()->with('success', 'Profil berhasil diperbarui.');
    }
}
