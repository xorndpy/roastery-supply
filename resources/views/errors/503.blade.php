@extends('errors.layout')

@section('code', '503')
@section('title', 'Sedang dalam pemeliharaan')
@section('message', 'Kami sedang memperbarui sistem supaya layanan lebih baik. Mohon kembali beberapa menit lagi, terima kasih atas kesabaran Anda.')
@section('secondary')
    <button type="button" class="btn btn-ghost" onclick="location.reload()">Coba lagi</button>
@endsection
