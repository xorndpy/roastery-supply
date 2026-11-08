<?php

namespace App\Support;

use GdImage;

/**
 * Potong ruang putih/transparan di sekeliling foto produk, lalu beri margin tipis yang seragam.
 * Tujuannya: produk selalu mengisi kartu dengan proporsi yang konsisten, apa pun foto sumbernya.
 * Latar abu-abu/berwarna tidak dipotong (hanya putih dan transparan), jadi foto tidak rusak.
 */
class ImageTrim
{
    /**
     * @param  float  $pad  Margin sebagai fraksi sisi terpanjang produk (0.04 = 4%).
     * @param  int  $tolerance  Toleransi "hampir putih" (0-255). 18 menyaring bayangan/noise JPEG ringan.
     */
    public static function apply(GdImage $src, float $pad = 0.04, int $tolerance = 18): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 40 || $h < 40) {
            return $src;
        }

        // Pindai versi kecil supaya cepat. Transparan dianggap putih.
        $scale = min(1, 400 / max($w, $h));
        $sw = max(1, (int) round($w * $scale));
        $sh = max(1, (int) round($h * $scale));
        $small = imagecreatetruecolor($sw, $sh);
        imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
        imagealphablending($small, true);
        imagecopyresampled($small, $src, 0, 0, 0, 0, $sw, $sh, $w, $h);

        $limit = 255 - $tolerance;
        $minX = $sw;
        $minY = $sh;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $c = imagecolorat($small, $x, $y);
                if ((($c >> 16) & 255) < $limit || (($c >> 8) & 255) < $limit || ($c & 255) < $limit) {
                    if ($x < $minX) { $minX = $x; }
                    if ($x > $maxX) { $maxX = $x; }
                    if ($y < $minY) { $minY = $y; }
                    if ($y > $maxY) { $maxY = $y; }
                }
            }
        }
        imagedestroy($small);

        // Foto kosong: biarkan apa adanya.
        if ($maxX < 0) {
            return $src;
        }

        // Kembalikan ke koordinat asli (dengan sedikit kelonggaran).
        $slack = (int) ceil(1 / $scale);
        $x0 = max(0, (int) floor($minX / $scale) - $slack);
        $y0 = max(0, (int) floor($minY / $scale) - $slack);
        $x1 = min($w, (int) ceil(($maxX + 1) / $scale) + $slack);
        $y1 = min($h, (int) ceil(($maxY + 1) / $scale) + $slack);
        $bw = $x1 - $x0;
        $bh = $y1 - $y0;

        // Sudah mepet: tidak perlu diubah.
        if ($bw >= $w * 0.97 && $bh >= $h * 0.97) {
            return $src;
        }

        $margin = (int) round(max($bw, $bh) * $pad);
        $out = imagecreatetruecolor($bw + 2 * $margin, $bh + 2 * $margin);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagealphablending($out, true);
        imagecopy($out, $src, $margin, $margin, $x0, $y0, $bw, $bh);
        imagedestroy($src);

        return $out;
    }
}
