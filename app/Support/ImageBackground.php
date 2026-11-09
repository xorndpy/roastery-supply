<?php

namespace App\Support;

use GdImage;

/**
 * Hapus latar polos pada logo / foto kecil (merek, rekanan, kategori) supaya semuanya menyatu dengan kartu,
 * apa pun warna latar file yang diunggah. Hasilnya PNG transparan yang dipangkas rapi.
 *
 * Yang SENGAJA tidak diubah (mengembalikan null, file asli dipakai apa adanya):
 *  - sudah transparan,
 *  - latar tidak seragam (foto, gradasi),
 *  - latar gelap (logo biasanya terang di atasnya),
 *  - logo yang kontennya hampir putih (akan hilang di kartu putih setelah latar dibuang).
 */
class ImageBackground
{
    private const MAX_SIDE = 640;

    /** @return string|null isi PNG baru, atau null bila gambar sebaiknya dibiarkan */
    public static function process(string $binary, int $tolerance = 26): ?string
    {
        if ($binary === '' || !function_exists('imagecreatefromstring')) {
            return null;
        }

        $src = @imagecreatefromstring($binary);
        if (!$src instanceof GdImage) {
            return null;
        }

        $out = self::removeBackground($src, $tolerance);
        if ($out === null) {
            return null;
        }

        ob_start();
        imagepng($out, null, 6);
        $png = ob_get_clean();

        return $png === false || $png === '' ? null : $png;
    }

    private static function removeBackground(GdImage $src, int $tol): ?GdImage
    {
        if (!imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 24 || $h < 24) {
            return null;
        }

        // Kecilkan dulu supaya cepat dan hemat memori.
        if (max($w, $h) > self::MAX_SIDE) {
            $k = self::MAX_SIDE / max($w, $h);
            $scaled = imagescale($src, max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
            if (!$scaled instanceof GdImage) {
                return null;
            }
            $src = $scaled;
            $w = imagesx($src);
            $h = imagesy($src);
        }

        // 1) Sudah transparan? Biarkan.
        $transparentBorder = 0;
        $borderTotal = 0;
        foreach (self::borderPoints($w, $h, 24) as [$x, $y]) {
            $borderTotal++;
            if ((imagecolorat($src, $x, $y) >> 24 & 0x7F) >= 100) {
                $transparentBorder++;
            }
        }
        if ($transparentBorder * 2 > $borderTotal) {
            return null;
        }

        // 2) Warna latar = kelompok terbesar dari 12 titik tepi (harus ≥ 75% sepakat).
        $samples = [];
        foreach ([[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1],
                  [intdiv($w, 2), 0], [intdiv($w, 2), $h - 1], [0, intdiv($h, 2)], [$w - 1, intdiv($h, 2)],
                  [intdiv($w, 4), 0], [intdiv($w * 3, 4), 0], [intdiv($w, 4), $h - 1], [intdiv($w * 3, 4), $h - 1]] as [$x, $y]) {
            $c = imagecolorat($src, $x, $y);
            $samples[] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
        }
        $best = [];
        foreach ($samples as $ref) {
            $group = array_values(array_filter($samples, fn ($s) => max(abs($s[0] - $ref[0]), abs($s[1] - $ref[1]), abs($s[2] - $ref[2])) <= (int) ($tol * 1.5)));
            if (count($group) > count($best)) {
                $best = $group;
            }
        }
        if (count($best) < 9) {
            return null;
        }
        $br = (int) round(array_sum(array_column($best, 0)) / count($best));
        $bg = (int) round(array_sum(array_column($best, 1)) / count($best));
        $bb = (int) round(array_sum(array_column($best, 2)) / count($best));

        // 3) Latar gelap: biarkan.
        if (0.299 * $br + 0.587 * $bg + 0.114 * $bb < 80) {
            return null;
        }

        // 4) Banjiri dari tepi: hanya latar yang tersambung ke tepi yang dibuang (bagian dalam huruf/logo tetap aman).
        $n = $w * $h;
        $seen = str_repeat("\0", $n);   // 0 = belum dicek, 1 = latar, 2 = bukan latar
        $stack = [];
        $isBg = static function (int $c) use ($br, $bg, $bb, $tol): bool {
            return ($c >> 24 & 0x7F) < 100
                && abs((($c >> 16) & 255) - $br) <= $tol
                && abs((($c >> 8) & 255) - $bg) <= $tol
                && abs(($c & 255) - $bb) <= $tol;
        };
        foreach (self::borderPoints($w, $h, 0) as [$x, $y]) {
            $i = $y * $w + $x;
            if ($seen[$i] === "\0") {
                if ($isBg(imagecolorat($src, $x, $y))) {
                    $seen[$i] = "\1";
                    $stack[] = $i;
                } else {
                    $seen[$i] = "\2";
                }
            }
        }
        while ($stack) {
            $i = array_pop($stack);
            $x = $i % $w;
            $y = intdiv($i, $w);
            foreach ([[$x > 0, $i - 1], [$x < $w - 1, $i + 1], [$y > 0, $i - $w], [$y < $h - 1, $i + $w]] as [$ok, $j]) {
                if ($ok && $seen[$j] === "\0") {
                    if ($isBg(imagecolorat($src, $j % $w, intdiv($j, $w)))) {
                        $seen[$j] = "\1";
                        $stack[] = $j;
                    } else {
                        $seen[$j] = "\2";
                    }
                }
            }
        }

        $removed = substr_count($seen, "\1");
        if ($removed < $n * 0.03 || $removed > $n * 0.98) {
            return null; // tidak ada latar berarti / gambar hanya latar
        }

        // 5) Susun hasil: tepi dihaluskan, hitung kotak batas, dan cek konten tidak "hilang" di kartu putih.
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        $visible = 0;
        $light = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = $y * $w + $x;
                if ($seen[$i] === "\1") {
                    continue;
                }
                $c = imagecolorat($src, $x, $y);
                $a = ($c >> 24) & 0x7F;
                $r = ($c >> 16) & 255;
                $g = ($c >> 8) & 255;
                $b = $c & 255;

                $edge = ($x > 0 && $seen[$i - 1] === "\1") || ($x < $w - 1 && $seen[$i + 1] === "\1")
                    || ($y > 0 && $seen[$i - $w] === "\1") || ($y < $h - 1 && $seen[$i + $w] === "\1");
                if ($edge) {
                    $d = max(abs($r - $br), abs($g - $bg), abs($b - $bb));
                    $f = max(0.0, min(1.0, ($d - $tol) / ($tol * 2.0)));
                    $a = 127 - (int) round((127 - $a) * $f);
                }
                if ($a >= 127) {
                    continue;
                }

                imagesetpixel($out, $x, $y, ($a << 24) | ($r << 16) | ($g << 8) | $b);

                if ($a < 100) {
                    $visible++;
                    if (0.299 * $r + 0.587 * $g + 0.114 * $b > 235) {
                        $light++;
                    }
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($visible === 0 || $light > $visible * 0.6) {
            return null;
        }

        // 6) Pangkas rapi + margin tipis seragam.
        $bw = $maxX - $minX + 1;
        $bh = $maxY - $minY + 1;
        $pad = (int) round(max($bw, $bh) * 0.06);
        $final = imagecreatetruecolor($bw + 2 * $pad, $bh + 2 * $pad);
        imagealphablending($final, false);
        imagesavealpha($final, true);
        imagefill($final, 0, 0, imagecolorallocatealpha($final, 0, 0, 0, 127));
        imagecopy($final, $out, $pad, $pad, $minX, $minY, $bw, $bh);

        return $final;
    }

    /** Titik-titik di sepanjang tepi gambar (step 0/1 = semua titik; lebih besar = jarang). */
    private static function borderPoints(int $w, int $h, int $count): array
    {
        $points = [];
        $stepX = $count > 0 ? max(1, intdiv($w, $count)) : 1;
        $stepY = $count > 0 ? max(1, intdiv($h, $count)) : 1;
        for ($x = 0; $x < $w; $x += $stepX) {
            $points[] = [$x, 0];
            $points[] = [$x, $h - 1];
        }
        for ($y = 0; $y < $h; $y += $stepY) {
            $points[] = [0, $y];
            $points[] = [$w - 1, $y];
        }

        return $points;
    }
}
