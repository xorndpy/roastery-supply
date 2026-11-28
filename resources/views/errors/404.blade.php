@extends('errors.layout')

@section('code', '404')
@section('title', 'Halaman tidak ditemukan')
@section('message', 'Tautan yang Anda buka mungkin salah ketik atau halamannya sudah dipindahkan. Coba cari produk yang Anda butuhkan lewat katalog kami.')
@section('secondary')
    <a class="btn btn-ghost" href="{{ url('/katalog') }}">Lihat katalog</a>
@endsection
