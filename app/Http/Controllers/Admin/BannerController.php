<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        $banners = Banner::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.form', ['banner' => new Banner(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['image'] = $this->storeImage($request->file('image'), 'banners');
        $data['sort_order'] = $data['sort_order'] ?? ((int) Banner::max('sort_order') + 1);

        Banner::create($data);

        return redirect()->route('admin.banners.index')->with('success', 'Banner ditambahkan.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.form', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $this->validated($request, false);

        if ($request->hasFile('image')) {
            $this->deleteImage($banner->image);
            $data['image'] = $this->storeImage($request->file('image'), 'banners');
        }
        $data['sort_order'] = $data['sort_order'] ?? $banner->sort_order;

        $banner->update($data);

        return redirect()->route('admin.banners.index')->with('success', 'Banner diperbarui.');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();

        return back()->with('success', 'Banner dihapus.');
    }

    /** Simpan urutan hasil drag-drop: ids[] berurutan dari atas. */
    public function reorder(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:banners,id'])['ids'];

        foreach (array_values($ids) as $position => $id) {
            Banner::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'title'      => 'required|string|max:150',
            'image'      => ($creating ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png,webp|max:4096',
            // hanya URL http(s) atau path internal: mencegah javascript: pada href
            'link'       => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/|\/)/'],
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'start_at'   => 'nullable|date',
            'end_at'     => 'nullable|date|after_or_equal:start_at',
        ], ['link.regex' => 'Link harus diawali http://, https:// atau /.']);

        $data['is_active'] = $request->boolean('is_active');
        unset($data['image'] );

        return $data;
    }
}
