<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function tentang()
    {
        return view('web.pages.tentang');
    }

    public function faq()
    {
        return view('web.pages.faq');
    }

    public function cabang()
    {
        return view('web.pages.cabang');
    }

    public function testimoni()
    {
        return view('web.pages.testimoni');
    }
}
