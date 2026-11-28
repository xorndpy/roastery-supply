@extends('layouts.app')

@section('title', 'Syarat & Ketentuan')
@section('description', 'Ketentuan penggunaan situs, pemesanan, pembayaran, dan pengiriman di ' . ($site['site_name'] ?? '') . '.')

@section('content')
@php $warrantyMonths = (int) ($site['warranty_default_months'] ?? 12); @endphp
<x-legal-page title="Syarat & Ketentuan" :updated="$updated"
    lead="Dengan mendaftar, masuk, atau memesan di situs ini, Anda menyatakan telah membaca dan menyetujui syarat berikut, serta Kebijakan Privasi kami."
    :toc="['akun' => 'Akun', 'harga' => 'Produk, harga, dan PPN', 'pesanan' => 'Pemesanan dan pembayaran', 'pengiriman' => 'Pengiriman', 'purnajual' => 'Retur, garansi, dan servis', 'penggunaan' => 'Penggunaan situs', 'hki' => 'Hak kekayaan intelektual', 'tanggung' => 'Batasan tanggung jawab', 'perubahan' => 'Perubahan syarat', 'hukum' => 'Hukum dan penyelesaian sengketa']">

    <h2 id="akun">1. Akun</h2>
    <ul>
        <li>Anda berusia 18 tahun ke atas, atau mewakili usaha yang cakap hukum.</li>
        <li>Data yang Anda berikan harus benar dan terbaru.</li>
        <li>Anda bertanggung jawab menjaga kerahasiaan kata sandi dan atas aktivitas di akun Anda.</li>
        <li>Kami dapat menangguhkan akun yang melanggar syarat ini atau menunjukkan aktivitas mencurigakan.</li>
    </ul>

    <h2 id="harga">2. Produk, harga, dan PPN</h2>
    <ul>
        <li>Kami berusaha menampilkan spesifikasi, foto, dan stok seakurat mungkin. Warna dan tampilan produk dapat sedikit berbeda dari foto.</li>
        <li>Harga dalam Rupiah dan dapat berubah sewaktu-waktu. Harga yang berlaku untuk Anda adalah harga pada saat pesanan dibuat.</li>
        <li>Bila PPN diberlakukan, harga yang tampil sudah termasuk PPN. Tarif dan nilainya dirinci pada invoice dan tersimpan per pesanan, sehingga perubahan tarif di kemudian hari tidak mengubah invoice lama.</li>
        <li>Bila terjadi kesalahan harga yang jelas (misalnya salah ketik atau gangguan sistem), kami berhak membatalkan pesanan terkait dan mengembalikan dana Anda secara penuh.</li>
        <li>Flash sale dan voucher berlaku sesuai periode, kuota, dan syarat yang tertera pada masing-masing promo.</li>
    </ul>

    <h2 id="pesanan">3. Pemesanan dan pembayaran</h2>
    <ul>
        <li>Pesanan tercatat saat Anda menekan tombol &ldquo;Buat Pesanan&rdquo;. Pesanan diproses setelah pembayaran kami terima dan terverifikasi.</li>
        <li>Selesaikan pembayaran dalam <strong>24 jam</strong>. Pesanan yang belum dibayar melewati batas itu dibatalkan otomatis.</li>
        <li>Metode pembayaran yang tersedia (misalnya virtual account atau transfer bank) ditampilkan saat checkout. Untuk transfer manual, unggah bukti transfer pada halaman pesanan.</li>
        <li>Bila opsi cicilan tersedia, ketentuannya ditampilkan pada halaman pembayaran sebelum Anda mengonfirmasi.</li>
    </ul>

    <h2 id="pengiriman">4. Pengiriman</h2>
    <ul>
        <li>Ongkos kirim dihitung otomatis saat checkout berdasarkan tujuan, berat, dan layanan kurir yang Anda pilih.</li>
        <li>Estimasi waktu tiba berasal dari kurir dan bukan jaminan. Nomor resi dan status pengiriman tersedia di halaman pesanan Anda.</li>
        <li>Kesalahan alamat atau nomor telepon yang Anda isi dapat menyebabkan keterlambatan atau biaya pengiriman ulang.</li>
        <li>Periksa kondisi paket saat diterima. Bila kemasan tampak rusak, sebaiknya rekam video saat membuka paket dan segera hubungi kami.</li>
    </ul>

    <h2 id="purnajual">5. Retur, garansi, dan servis</h2>
    <h3>Retur</h3>
    <div class="legal-note">Pengajuan retur dibuka <strong>{{ $returnDays }} hari</strong> sejak pesanan berstatus Selesai, dan hanya satu kali per pesanan.</div>
    <ul>
        <li>Alasan yang dapat diajukan: barang rusak saat diterima, tidak sesuai pesanan, cacat atau tidak berfungsi, atau alasan lain yang kami tinjau satu per satu.</li>
        <li>Cara mengajukan: masuk ke akun, buka menu <strong>Retur</strong>, pilih pesanan, tulis alasan (minimal 10 karakter), unggah 1 sampai 3 foto bukti (JPG, PNG, atau WebP, maksimal 4 MB per foto), lalu isi rekening tujuan pengembalian dana. Foto disimpan privat.</li>
        <li>Hasil tinjauan kami sampaikan lewat email dan notifikasi di akun. Bila disetujui, dana dikembalikan ke rekening yang Anda isi sesuai nilai yang disetujui, dan kami dapat meminta barang dikembalikan untuk diperiksa. Bila ditolak, kami sertakan alasannya.</li>
    </ul>

    <h3>Garansi</h3>
    <ul>
        <li>Setiap unit pada pesanan yang selesai otomatis dicatat garansinya di menu <strong>Garansi &amp; Servis</strong> pada akun Anda.</li>
        <li>Masa garansi dihitung sejak tanggal pesanan selesai dan mengikuti produknya. Bila tidak ditentukan khusus, masa garansi bawaan adalah <strong>{{ $warrantyMonths }} bulan</strong>. Produk yang tidak bergaransi ditandai pada halaman produknya.</li>
    </ul>

    <h3>Servis</h3>
    <p>Ajukan tiket servis dari menu <strong>Garansi &amp; Servis</strong>, jelaskan keluhannya, lalu pantau perkembangannya di halaman tiket. Bila masa garansi sudah habis atau kerusakan di luar cakupan garansi, servis dapat dikenakan biaya yang kami sampaikan sebelum pekerjaan dimulai.</p>

    <h3>Hal yang tidak ditanggung</h3>
    <p>Retur dan garansi tidak berlaku bila pengajuan melewati batas waktu, kerusakan disebabkan pemakaian tidak wajar, kelalaian, atau kecelakaan setelah barang diterima, unit dibongkar atau diperbaiki pihak lain tanpa sepengetahuan kami, atau kerusakan berasal dari kondisi di luar spesifikasi pemakaian (misalnya tegangan listrik atau kualitas air yang tidak sesuai).</p>

    <h2 id="penggunaan">6. Penggunaan situs</h2>
    <ul>
        <li>Dilarang memakai bot, melakukan scraping, mencoba mengakses sistem atau akun orang lain tanpa izin, atau mengganggu kinerja situs.</li>
        <li>Ulasan dan testimoni yang Anda kirim harus berdasarkan pengalaman asli dan tidak melanggar hukum. Kami dapat memoderasi atau menolak konten yang tidak sesuai.</li>
    </ul>

    <h2 id="hki">7. Hak kekayaan intelektual</h2>
    <p>Teks, foto, logo, dan tampilan situs ini milik kami atau pemberi lisensi kami. Anda tidak boleh menyalin, menyebarkan, atau memakainya untuk tujuan komersial tanpa izin tertulis.</p>

    <h2 id="tanggung">8. Batasan tanggung jawab</h2>
    <p>Sejauh diizinkan hukum, kami tidak bertanggung jawab atas keterlambatan atau kegagalan yang disebabkan hal di luar kendali kami, seperti bencana alam, gangguan jaringan, atau gangguan layanan kurir. Tanggung jawab kami terbatas pada nilai pesanan yang bersangkutan. Ketentuan ini tidak mengurangi hak Anda sebagai konsumen menurut peraturan perundang-undangan yang berlaku.</p>

    <h2 id="perubahan">9. Perubahan syarat</h2>
    <p>Kami dapat memperbarui syarat ini. Versi terbaru selalu tersedia di halaman ini dengan tanggal pembaruannya. Dengan tetap memakai situs setelah perubahan berlaku, Anda dianggap menyetujui versi terbaru.</p>

    <h2 id="hukum">10. Hukum dan penyelesaian sengketa</h2>
    <p>Syarat ini tunduk pada hukum Republik Indonesia. Bila ada perselisihan, kita upayakan menyelesaikannya secara musyawarah lebih dulu. Jika tidak tercapai kesepakatan, perselisihan diselesaikan melalui pengadilan yang berwenang di Indonesia.</p>
</x-legal-page>
@endsection
