@extends('errors.layout')

@section('code', '403')
@section('title', 'Akses ditolak')
@section('message', 'Akun Anda tidak punya izin untuk membuka halaman ini. Jika menurut Anda ini keliru, hubungi kami dan kami bantu periksa.')
@section('secondary')
    <a class="btn btn-ghost" href="{{ url('/login') }}">Masuk dengan akun lain</a>
@endsection
