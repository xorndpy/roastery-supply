@extends('errors.layout')

@section('code', '429')
@section('title', 'Terlalu banyak percobaan')
@section('message', 'Kami menerima terlalu banyak permintaan dari perangkat Anda dalam waktu singkat. Tunggu satu atau dua menit, lalu coba lagi.')
@section('secondary')
    <button type="button" class="btn btn-ghost" onclick="location.reload()">Coba lagi</button>
@endsection
