<?php

namespace Tests\Unit;

use App\Support\ImageBackground;
use PHPUnit\Framework\TestCase;

class ImageBackgroundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD tidak tersedia.');
        }
    }

    /** Gambar $size x $size dengan latar $bg dan lingkaran $fg di tengah. */
    private function logo(array $bg, array $fg, int $size = 200): string
    {
        $im = imagecreatetruecolor($size, $size);
        imagefill($im, 0, 0, imagecolorallocate($im, ...$bg));
        imagefilledellipse($im, intdiv($size, 2), intdiv($size, 2), 90, 90, imagecolorallocate($im, ...$fg));
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    private function decode(string $png): \GdImage
    {
        $im = imagecreatefromstring($png);
        imagealphablending($im, false);

        return $im;
    }

    private function alphaAt(\GdImage $im, int $x, int $y): int
    {
        return (imagecolorat($im, $x, $y) >> 24) & 0x7F;
    }

    public function test_latar_abu_terang_dibuang_dan_logo_dipangkas(): void
    {
        $png = ImageBackground::process($this->logo([236, 236, 236], [40, 40, 40]));

        $this->assertNotNull($png);
        $im = $this->decode($png);
        $this->assertLessThan(200, imagesx($im));                                    // dipangkas
        $this->assertSame(127, $this->alphaAt($im, 0, 0));                           // sudut transparan
        $this->assertSame(0, $this->alphaAt($im, intdiv(imagesx($im), 2), intdiv(imagesy($im), 2))); // isi logo utuh
    }

    public function test_latar_putih_dan_berwarna_terang_juga_dibuang(): void
    {
        foreach ([[255, 255, 255], [226, 230, 240], [250, 240, 220]] as $bg) {
            $this->assertNotNull(ImageBackground::process($this->logo($bg, [30, 60, 120])), 'latar ' . implode(',', $bg));
        }
    }

    public function test_latar_gelap_dibiarkan(): void
    {
        $this->assertNull(ImageBackground::process($this->logo([25, 25, 25], [255, 255, 255])));
    }

    public function test_logo_putih_di_latar_abu_tidak_diubah_karena_akan_hilang_di_kartu_putih(): void
    {
        $this->assertNull(ImageBackground::process($this->logo([120, 120, 120], [255, 255, 255])));
    }

    public function test_latar_gradasi_atau_foto_dibiarkan(): void
    {
        $im = imagecreatetruecolor(200, 200);
        for ($x = 0; $x < 200; $x++) {
            imageline($im, $x, 0, $x, 199, imagecolorallocate($im, 255 - $x, 200 - intdiv($x, 2), 100 + $x / 2));
        }
        ob_start();
        imagepng($im);

        $this->assertNull(ImageBackground::process(ob_get_clean()));
    }

    public function test_gambar_yang_sudah_transparan_dibiarkan(): void
    {
        $im = imagecreatetruecolor(200, 200);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledellipse($im, 100, 100, 90, 90, imagecolorallocate($im, 200, 30, 30));
        ob_start();
        imagepng($im);

        $this->assertNull(ImageBackground::process(ob_get_clean()));
    }

    public function test_gambar_satu_warna_atau_bukan_gambar_dibiarkan(): void
    {
        $im = imagecreatetruecolor(100, 100);
        imagefill($im, 0, 0, imagecolorallocate($im, 240, 240, 240));
        ob_start();
        imagepng($im);

        $this->assertNull(ImageBackground::process(ob_get_clean()));
        $this->assertNull(ImageBackground::process('bukan gambar'));
        $this->assertNull(ImageBackground::process(''));
    }

    public function test_bagian_dalam_huruf_tidak_ikut_dibuang(): void
    {
        // Cincin gelap di latar abu: bagian dalam cincin (latar terkurung) tetap utuh.
        $im = imagecreatetruecolor(200, 200);
        imagefill($im, 0, 0, imagecolorallocate($im, 235, 235, 235));
        imagefilledellipse($im, 100, 100, 120, 120, imagecolorallocate($im, 30, 30, 30));
        imagefilledellipse($im, 100, 100, 50, 50, imagecolorallocate($im, 235, 235, 235));
        ob_start();
        imagepng($im);

        $out = $this->decode(ImageBackground::process(ob_get_clean()));
        $this->assertSame(0, $this->alphaAt($out, intdiv(imagesx($out), 2), intdiv(imagesy($out), 2)));
    }
}
