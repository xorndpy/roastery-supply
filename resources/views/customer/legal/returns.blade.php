@extends('layouts.app')

@section('title', 'Kebijakan Retur & Garansi')
@section('description', 'Cara mengajukan retur, masa garansi, dan layanan servis di ' . ($site['site_name'] ?? '') . '.')

@section('content')
@php $warrantyMonths = (int) ($site['warranty_default_months'] ?? 12); @endphp
<x-legal-page title="Kebijakan Retur & Garansi" :updated="$updated"
    lead="Peralatan kopi adalah investasi usaha. Halaman ini menjelaskan kapan retur bisa diajukan, bagaimana garansi bekerja, dan cara meminta servis."
    :toc="['retur' => 'Retur', 'cara' => 'Cara mengajukan retur', 'proses' => 'Proses dan pengembalian dana', 'garansi' => 'Garansi', 'servis' => 'Servis', 'batasan' => 'Hal yang tidak ditanggung']">

    <h2 id="retur">1. Retur</h2>
    <div class="legal-note">Pengajuan retur dibuka <strong>{{ $returnDays }} hari</strong> sejak pesanan berstatus Selesai, dan hanya satu kali per pesanan.</div>
    <p>Alasan yang dapat diajukan:</p>
    <ul>
        <li>barang rusak saat diterima;</li>
        <li>barang tidak sesuai pesanan;</li>
        <li>barang cacat atau tidak berfungsi;</li>
        <li>alasan lain, yang kami tinjau satu per satu.</li>
    </ul>

    <h2 id="cara">2. Cara mengajukan retur</h2>
    <ol>
        <li>Masuk ke akun Anda, lalu buka menu <strong>Retur</strong>.</li>
        <li>Pilih pesanan yang memenuhi syarat.</li>
        <li>Tulis alasan dengan jelas (minimal 10 karakter) dan pilih jenis masalahnya.</li>
        <li>Unggah <strong>1 sampai 3 foto</strong> sebagai bukti (JPG, PNG, atau WebP, maksimal 4 MB per foto).</li>
        <li>Isi data rekening tujuan pengembalian dana, lalu kirim.</li>
    </ol>
    <p>Foto bukti disimpan privat dan hanya dapat dilihat oleh Anda dan tim kami.</p>

    <h2 id="proses">3. Proses dan pengembalian dana</h2>
    <ul>
        <li>Tim kami meninjau pengajuan Anda dan memberi tahu hasilnya lewat email dan notifikasi di akun.</li>
        <li>Bila disetujui, dana dikembalikan ke rekening yang Anda isi sesuai nilai yang disetujui. Kami dapat meminta barang dikembalikan untuk diperiksa.</li>
        <li>Bila ditolak, kami sertakan alasannya. Anda dapat menghubungi kami bila ada hal yang ingin dijelaskan.</li>
    </ul>

    <h2 id="garansi">4. Garansi</h2>
    <ul>
        <li>Setiap unit pada pesanan yang selesai otomatis dicatat garansinya di akun Anda, pada menu <strong>Garansi &amp; Servis</strong>.</li>
        <li>Masa garansi dihitung sejak tanggal pesanan selesai. Masa garansi mengikuti produknya. Bila tidak ditentukan khusus, masa garansi bawaan adalah <strong>{{ $warrantyMonths }} bulan</strong>.</li>
        <li>Produk yang tidak bergaransi ditandai pada halaman produknya.</li>
    </ul>

    <h2 id="servis">5. Servis</h2>
    <ol>
        <li>Buka menu <strong>Garansi &amp; Servis</strong> di akun Anda dan pilih unit yang bermasalah.</li>
        <li>Ajukan tiket servis dan jelaskan keluhannya sedetail mungkin.</li>
        <li>Pantau perkembangannya langsung di halaman tiket.</li>
    </ol>
    <p>Bila masa garansi sudah habis atau kerusakan di luar cakupan garansi, servis dapat dikenakan biaya. Rinciannya kami sampaikan sebelum pekerjaan dimulai.</p>

    <h2 id="batasan">6. Hal yang tidak ditanggung</h2>
    <p>Retur dan garansi tidak berlaku bila:</p>
    <ul>
        <li>pengajuan melewati batas waktu yang disebutkan di atas;</li>
        <li>kerusakan disebabkan pemakaian tidak wajar, kelalaian, atau kecelakaan setelah barang diterima;</li>
        <li>unit dibongkar, dimodifikasi, atau diperbaiki pihak lain tanpa sepengetahuan kami;</li>
        <li>kerusakan berasal dari kondisi lingkungan di luar spesifikasi pemakaian, misalnya tegangan listrik atau kualitas air yang tidak sesuai.</li>
    </ul>
</x-legal-page>
@endsection
