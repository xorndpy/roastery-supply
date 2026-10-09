<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * FAQ awal yang hanya berisi hal yang benar sesuai cara kerja sistem.
 * Tidak dijalankan ulang jika sudah ada FAQ (tidak menimpa buatan admin).
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        if (Faq::query()->exists()) {
            return;
        }

        $faqs = [
            ['Pemesanan', 'Bagaimana cara memesan?', 'Pilih produk di katalog, masukkan ke keranjang, lalu lanjut ke checkout dan isi alamat pengiriman. Setelah pesanan dibuat, lakukan pembayaran dan unggah bukti transfer.'],
            ['Pemesanan', 'Apakah harus punya akun?', 'Kamu bisa melihat katalog dan mengisi keranjang tanpa akun. Akun dibutuhkan saat checkout agar pesanan bisa dilacak.'],
            ['Pembayaran', 'Bagaimana cara membayar?', 'Pembayaran lewat transfer bank. Nomor rekening tampil di halaman pesanan setelah checkout. Unggah bukti transfer di halaman yang sama, lalu tim kami memverifikasinya.'],
            ['Pembayaran', 'Berapa lama batas waktu pembayaran?', 'Pesanan perlu dibayar dalam 24 jam. Pesanan yang belum dibayar setelah batas waktu itu dibatalkan otomatis.'],
            ['Pembayaran', 'Bukti transfer saya ditolak, apa yang harus dilakukan?', 'Buka halaman pesananmu, baca alasan penolakan, lalu unggah ulang bukti transfer yang jelas dan sesuai jumlah tagihan.'],
            ['Pengiriman', 'Bagaimana cara melacak pesanan?', 'Buka menu Pesanan Saya. Setelah pesanan dikirim, nama kurir dan nomor resi muncul di halaman detail pesanan.'],
            ['Pengiriman', 'Bagaimana jika stok habis setelah saya membayar?', 'Stok dihitung saat pembayaran dikonfirmasi. Jika stok ternyata tidak mencukupi, pesanan tidak dapat dikonfirmasi dan tim kami akan menghubungimu.'],
            ['Dokumen', 'Apakah tersedia invoice?', 'Ya. Unduh invoice PDF dari halaman detail pesananmu.'],
        ];

        foreach ($faqs as $i => [$category, $question, $answer]) {
            Faq::create(['category' => $category, 'question' => $question, 'answer' => $answer, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
