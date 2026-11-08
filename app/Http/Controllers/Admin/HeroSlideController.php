<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use Illuminate\Http\Request;

/** Foto latar slideshow di hero beranda. Teks hero tetap diatur di Pengaturan > Tampilan. */
class HeroSlideController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        return view('admin.hero.index', [
            'slides' => HeroSlide::orderBy('sort_order')->orderBy('id')->get(),
            'max' => HeroSlide::MAX,
        ]);
    }

    /** Boleh pilih banyak foto sekaligus. */
    public function store(Request $request)
    {
        $request->validate([
            'images' => 'required|array|min:1|max:' . HeroSlide::MAX,
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:3072',
        ], [
            'images.required' => 'Pilih minimal satu foto.',
            'images.max' => 'Maksimal ' . HeroSlide::MAX . ' foto sekali unggah.',
            'images.*.image' => 'File harus berupa gambar.',
            'images.*.mimes' => 'Format foto harus JPG, PNG, atau WEBP.',
            'images.*.max' => 'Ukuran tiap foto maksimal 3 MB.',
        ]);

        $room = HeroSlide::MAX - HeroSlide::count();
        if ($room <= 0) {
            return back()->withErrors(['images' => 'Sudah ' . HeroSlide::MAX . ' foto. Hapus salah satu dulu untuk menambah.']);
        }

        $files = $request->file('images');
        $accepted = array_slice($files, 0, $room);
        $order = (int) HeroSlide::max('sort_order');

        foreach ($accepted as $file) {
            HeroSlide::create(['image' => $this->storeImage($file, 'hero'), 'sort_order' => ++$order, 'is_active' => true]);
        }

        $skipped = count($files) - count($accepted);

        return back()->with('success', count($accepted) . ' foto ditambahkan.' . ($skipped ? " {$skipped} foto dilewati (batas " . HeroSlide::MAX . ' foto).' : ''));
    }

    public function toggle(HeroSlide $slide)
    {
        $slide->update(['is_active' => !$slide->is_active]);

        return back();
    }

    public function destroy(HeroSlide $slide)
    {
        $this->deleteImage($slide->image);
        $slide->delete();

        return back()->with('success', 'Foto dihapus.');
    }

    /** Urutan hasil seret-lepas: ids[] berurutan dari kiri-atas. */
    public function reorder(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:hero_slides,id'])['ids'];

        foreach (array_values($ids) as $position => $id) {
            HeroSlide::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
