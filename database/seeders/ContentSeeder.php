<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Milestone;
use Illuminate\Database\Seeder;

/**
 * Contoh konten awal (artikel & milestone). Aman dijalankan berulang (tidak menimpa yang sudah ada).
 * Isi milestone adalah CONTOH berdasarkan fitur situs; sesuaikan di Admin > Milestone dengan perjalanan sebenarnya.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $milestones = [
            ['2026', 'Katalog online dibuka', 'Etalase peralatan kopi profesional dengan harga, stok, dan spesifikasi yang jelas.'],
            ['2026', 'Pembayaran online otomatis', 'Virtual account, QRIS, e-wallet, dan kartu terkonfirmasi otomatis tanpa perlu mengunggah bukti.'],
            ['2026', 'Layanan konsultasi dan purna jual', 'Rekomendasi peralatan, instalasi, pelatihan barista, garansi resmi, dan suku cadang.'],
            ['Berikutnya', 'Insight dan edukasi', 'Artikel, tips perawatan, dan panduan memilih mesin untuk membantu usaha kopi Anda bertumbuh.'],
        ];
        foreach ($milestones as $i => [$year, $title, $desc]) {
            Milestone::firstOrCreate(['title' => $title], ['year' => $year, 'description' => $desc, 'sort_order' => $i, 'is_active' => true]);
        }

        $articles = [
            [
                'title' => 'Cara Memilih Mesin Espresso untuk Kedai Kopi Baru',
                'category' => 'Mesin',
                'excerpt' => 'Kapasitas, jumlah group, jenis boiler, dan daya listrik: empat hal yang perlu dihitung sebelum membeli mesin espresso pertama Anda.',
                'body' => "Mesin espresso adalah investasi terbesar sebuah kedai kopi. Memilih yang tepat berarti menyesuaikannya dengan **cara Anda berjualan**, bukan sekadar membeli yang paling mahal.\n\n## 1. Hitung kapasitas harian\nPerkirakan jumlah gelas pada jam tersibuk. Kedai dengan antrean singkat dan menu terbatas biasanya cukup dengan mesin **1 group**, sedangkan kedai dengan jam sibuk padat lebih nyaman memakai **2 group** agar dua barista bisa bekerja bersamaan.\n\n## 2. Pahami jenis boiler\n- **Single boiler**: sederhana dan terjangkau, tetapi kurang cocok bila harus menyeduh dan menguap susu bersamaan.\n- **Heat exchanger**: bisa menyeduh dan menguap susu bersamaan; umum dipakai kedai kecil hingga menengah.\n- **Dual boiler**: suhu seduh dan uap diatur terpisah sehingga lebih stabil; pilihan untuk volume tinggi.\n\n## 3. Periksa kebutuhan listrik dan air\nMesin komersial sering membutuhkan daya besar dan sambungan air bersih. Pastikan instalasi listrik dan saluran air lokasi Anda sanggup menampungnya **sebelum** membeli.\n\n## 4. Pastikan dukungan purna jual\nTanyakan ketersediaan teknisi, suku cadang, dan garansi resmi. Mesin yang berhenti beroperasi berarti kehilangan pendapatan.\n\n> Ragu menentukan pilihan? Konsultasikan kapasitas dan anggaran Anda dengan tim kami.",
            ],
            [
                'title' => 'Perawatan Harian Grinder agar Rasa Tetap Konsisten',
                'category' => 'Mesin',
                'excerpt' => 'Grinder yang bersih menghasilkan ekstraksi yang konsisten. Berikut rutinitas sederhana yang bisa dilakukan setiap hari.',
                'body' => "Rasa espresso yang berubah-ubah sering bukan salah biji kopinya, melainkan **grinder yang kotor atau tidak terkalibrasi**.\n\n## Rutinitas harian\n- Kosongkan sisa biji di hopper saat tutup, lalu bersihkan dengan kuas.\n- Bersihkan sisa bubuk di sekitar pintu keluar (chute) agar tidak menggumpal.\n- Lap bagian luar dan area dosing.\n\n## Rutinitas berkala\n- Bersihkan burr secara menyeluruh sesuai anjuran pabrikan, terutama bila memakai biji beraroma atau berminyak.\n- Kalibrasi ulang ukuran giling bila suhu dan kelembapan ruangan berubah.\n- Ganti burr sesuai usia pakai yang dianjurkan; burr yang tumpul menghasilkan giling tidak seragam.\n\n## Hindari\nBenda asing seperti kerikil atau plastik dapat merusak burr. Periksa biji sebelum dimasukkan ke hopper.\n\nCatat tanggal pembersihan agar jadwalnya tidak terlewat.",
            ],
            [
                'title' => 'Backflush dan Descaling: Kapan dan Mengapa?',
                'category' => 'Pelatihan',
                'excerpt' => 'Dua perawatan yang sering tertukar. Pahami perbedaannya supaya mesin espresso Anda awet dan rasanya tetap bersih.',
                'body' => "Dua istilah ini sering dianggap sama, padahal fungsinya berbeda.\n\n## Backflush: membersihkan sisa kopi\nBackflush mengalirkan air (dan cairan pembersih khusus) ke arah sebaliknya melalui group head untuk membersihkan **sisa minyak kopi dan ampas halus** di katup tiga arah. Lakukan **setiap hari** setelah operasional, dan gunakan cairan pembersih secukupnya sesuai petunjuk pabrikan.\n\n## Descaling: membersihkan kerak\nDescaling membersihkan **kerak kapur (scale)** di boiler dan pipa akibat air sadah. Frekuensinya bergantung pada kesadahan air Anda; air yang difilter dan dilunakkan memperpanjang jarak antar descaling. Ikuti prosedur dan cairan yang dianjurkan pabrikan mesin Anda.\n\n## Tips\n- Gunakan air yang sudah difilter sejak awal.\n- Jangan mengabaikan jadwal; kerak yang menumpuk dapat menurunkan kinerja pemanas.\n- Bila ragu, minta teknisi memeriksa dan melatih tim barista Anda.",
            ],
        ];
        foreach ($articles as $i => $a) {
            Article::firstOrCreate(
                ['title' => $a['title']],
                $a + ['slug' => Article::uniqueSlug($a['title']), 'author_name' => 'Tim Redaksi', 'is_published' => true, 'is_featured' => false, 'published_at' => now()->subDays(3 - $i)],
            );
        }
    }
}
