@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesi Anda sudah berakhir')
@section('message', 'Halaman ini terlalu lama dibiarkan terbuka, jadi demi keamanan sesinya kami akhiri. Kembali ke halaman sebelumnya lalu kirim ulang. Data yang belum tersimpan perlu diisi lagi.')
@section('secondary')
    <button type="button" class="btn btn-ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">Kembali ke halaman sebelumnya</button>
@endsection
