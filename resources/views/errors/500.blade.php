@extends('errors.layout')

@section('code', '500')
@section('title', 'Terjadi kesalahan di sistem kami')
@section('message', 'Ini bukan salah Anda, dan masalahnya sudah kami catat. Coba muat ulang beberapa saat lagi. Bila Anda sedang membuat pesanan, cek dulu halaman pesanan di akun Anda sebelum mengulang agar tidak terjadi pesanan ganda.')
@section('secondary')
    <button type="button" class="btn btn-ghost" onclick="location.reload()">Coba lagi</button>
@endsection
