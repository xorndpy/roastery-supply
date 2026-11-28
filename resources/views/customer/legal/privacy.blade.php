@extends('layouts.app')

@section('title', 'Kebijakan Privasi')
@section('description', 'Data apa yang dikumpulkan ' . ($site['site_name'] ?? '') . ', untuk apa dipakai, dan hak Anda atas data tersebut.')

@section('content')
<x-legal-page title="Kebijakan Privasi" :updated="$updated"
    lead="Halaman ini menjelaskan data apa saja yang kami kumpulkan saat Anda memakai situs ini, untuk apa data itu dipakai, dan pilihan yang Anda miliki."
    :toc="['data' => 'Data yang kami kumpulkan', 'tujuan' => 'Tujuan penggunaan', 'dasar' => 'Dasar pemrosesan', 'berbagi' => 'Dengan siapa data dibagikan', 'keamanan' => 'Keamanan data', 'penyimpanan' => 'Lama penyimpanan', 'hak' => 'Hak Anda', 'cookie' => 'Cookie dan analitik', 'anak' => 'Anak di bawah umur', 'perubahan' => 'Perubahan kebijakan']">

    <h2 id="data">1. Data yang kami kumpulkan</h2>
    <ul>
        <li><strong>Akun.</strong> Nama, alamat email, kata sandi (disimpan dalam bentuk terenkripsi satu arah, sehingga kami tidak bisa membacanya), dan nomor telepon bila Anda mengisinya. Jika Anda masuk dengan Google, kami menerima nama, email, dan foto profil dari akun Google Anda.</li>
        <li><strong>Pesanan dan pengiriman.</strong> Nama penerima, nomor telepon, alamat lengkap, kota, provinsi, kode pos, isi pesanan, dan catatan pesanan.</li>
        <li><strong>Pembayaran.</strong> Status dan referensi pembayaran. Untuk transfer manual, bukti transfer yang Anda unggah. Pembayaran online diproses oleh penyedia pembayaran pihak ketiga; kami tidak menyimpan nomor kartu Anda.</li>
        <li><strong>Retur, garansi, dan servis.</strong> Alasan pengajuan, foto bukti, data rekening untuk pengembalian dana, nomor seri unit, dan riwayat tiket servis.</li>
        <li><strong>Pesan Anda.</strong> Isi pesan yang Anda kirim lewat formulir kontak atau WhatsApp.</li>
        <li><strong>Data teknis.</strong> Alamat IP, jenis perangkat dan peramban, halaman yang dibuka, serta catatan keamanan seperti percobaan masuk yang gagal.</li>
    </ul>

    <h2 id="tujuan">2. Tujuan penggunaan</h2>
    <ul>
        <li>Memproses, memverifikasi pembayaran, mengirim, dan melacak pesanan Anda.</li>
        <li>Mengelola garansi, servis, dan retur.</li>
        <li>Mengirim pemberitahuan terkait pesanan Anda lewat email, notifikasi, atau WhatsApp.</li>
        <li>Menjaga keamanan situs, misalnya mencegah bot dan penyalahgunaan akun.</li>
        <li>Membuat invoice dan memenuhi kewajiban pembukuan serta perpajakan.</li>
        <li>Memahami cara pengunjung memakai situs agar layanan kami semakin baik.</li>
    </ul>

    <h2 id="dasar">3. Dasar pemrosesan</h2>
    <p>Kami memproses data Anda berdasarkan persetujuan Anda (saat mendaftar atau memesan), untuk melaksanakan transaksi dengan Anda, dan untuk memenuhi kewajiban hukum. Anda dapat menarik persetujuan kapan saja. Perlu diketahui bahwa sebagian layanan, seperti pemesanan, tidak dapat dilanjutkan tanpa data yang diperlukan.</p>

    <h2 id="berbagi">4. Dengan siapa data dibagikan</h2>
    <p>Kami tidak menjual data pribadi Anda. Data hanya dibagikan seperlunya kepada:</p>
    <ul>
        <li>kurir dan ekspedisi, untuk mengantar pesanan (nama, telepon, dan alamat);</li>
        <li>penyedia pembayaran, untuk memproses pembayaran online;</li>
        <li>penyedia layanan email dan notifikasi, untuk mengirim pemberitahuan;</li>
        <li>Cloudflare, untuk keamanan situs dan verifikasi anti-bot;</li>
        <li>Google dan Meta, bila fitur masuk dengan Google atau analitik dan iklan sedang diaktifkan;</li>
        <li>pihak berwenang, bila diwajibkan oleh hukum.</li>
    </ul>

    <h2 id="keamanan">5. Keamanan data</h2>
    <ul>
        <li>Situs diakses lewat koneksi terenkripsi (HTTPS).</li>
        <li>Kata sandi disimpan dalam bentuk terenkripsi satu arah.</li>
        <li>Akun staf yang dapat mengakses panel admin dilindungi verifikasi dua langkah, dan akses dibatasi sesuai peran.</li>
        <li>Bukti pembayaran dan foto retur disimpan di penyimpanan privat dan tidak bisa dibuka lewat tautan publik.</li>
    </ul>
    <p>Tidak ada sistem yang sepenuhnya kebal. Jika Anda mencurigai ada akses tanpa izin ke akun Anda, segera hubungi kami.</p>

    <h2 id="penyimpanan">6. Lama penyimpanan</h2>
    <p>Data akun disimpan selama akun Anda aktif. Data transaksi disimpan selama diperlukan untuk garansi, pembukuan, dan kewajiban hukum, lalu dihapus atau dianonimkan. Anda dapat meminta penghapusan akun; data transaksi yang wajib kami simpan menurut hukum tetap disimpan sampai kewajiban itu berakhir.</p>

    <h2 id="hak">7. Hak Anda</h2>
    <p>Anda berhak untuk:</p>
    <ul>
        <li>mengetahui dan meminta salinan data pribadi Anda;</li>
        <li>memperbaiki data yang keliru (nama, email, telepon, dan alamat dapat diubah langsung dari akun);</li>
        <li>meminta penghapusan data atau akun Anda;</li>
        <li>menarik persetujuan, serta mengajukan keberatan atau meminta pembatasan pemrosesan.</li>
    </ul>
    <p>Untuk menggunakan hak tersebut, hubungi kami lewat kontak di bagian bawah halaman ini. Kami dapat meminta Anda membuktikan identitas terlebih dahulu demi melindungi akun Anda.</p>

    <h2 id="cookie">8. Cookie dan analitik</h2>
    <ul>
        <li><strong>Cookie penting:</strong> menjaga sesi masuk, keranjang, fitur &ldquo;ingat saya&rdquo;, dan perlindungan keamanan formulir. Situs tidak dapat berfungsi tanpa cookie ini.</li>
        <li><strong>Keamanan:</strong> Cloudflare memasang cookie atau skrip untuk memeriksa bahwa pengunjung adalah manusia.</li>
        <li><strong>Analitik dan iklan:</strong> bila diaktifkan oleh toko, kami memakai Google Analytics dan Meta Pixel untuk memahami kunjungan dan mengukur iklan. Keduanya <strong>baru aktif setelah Anda menekan &ldquo;Terima&rdquo;</strong> pada banner cookie. Bila Anda menolak, tidak ada cookie pelacak yang dipasang.</li>
        <li><strong>Statistik tanpa cookie:</strong> Cloudflare Web Analytics, bila diaktifkan, menghitung kunjungan tanpa memasang cookie atau mengenali Anda secara pribadi.</li>
    </ul>
    <p>Anda dapat mengubah pilihan kapan saja lewat tautan &ldquo;Pengaturan cookie&rdquo; di bagian bawah halaman, atau menghapus cookie lewat pengaturan peramban Anda.</p>

    <h2 id="anak">9. Anak di bawah umur</h2>
    <p>Layanan kami ditujukan bagi pelaku usaha dan pembeli dewasa (18 tahun ke atas). Kami tidak dengan sengaja mengumpulkan data anak-anak. Jika Anda mengetahui ada data anak yang kami simpan, hubungi kami agar dapat dihapus.</p>

    <h2 id="perubahan">10. Perubahan kebijakan</h2>
    <p>Kami dapat memperbarui kebijakan ini dari waktu ke waktu. Tanggal pembaruan terbaru tertera di bagian atas halaman. Untuk perubahan yang penting, kami akan memberi tahu lewat situs.</p>
</x-legal-page>
@endsection
