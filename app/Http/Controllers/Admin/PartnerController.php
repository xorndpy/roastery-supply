<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        $partners = Partner::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.partners.index', compact('partners'));
    }

    public function create()
    {
        return view('admin.partners.form', ['partner' => new Partner(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['logo'] = $this->storeImage($request->file('logo'), 'partners');

        Partner::create($data);

        return redirect()->route('admin.partners.index')->with('success', 'Partner ditambahkan.');
    }

    public function edit(Partner $partner)
    {
        return view('admin.partners.form', compact('partner'));
    }

    public function update(Request $request, Partner $partner)
    {
        $data = $this->validated($request, false);

        if ($request->hasFile('logo')) {
            $this->deleteImage($partner->logo);
            $data['logo'] = $this->storeImage($request->file('logo'), 'partners');
        }

        $partner->update($data);

        return redirect()->route('admin.partners.index')->with('success', 'Partner diperbarui.');
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();

        return back()->with('success', 'Partner dihapus.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'logo'       => ($creating ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png,webp|max:2048',
            'link'       => 'nullable|url:http,https|max:255',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        unset($data['logo']);

        return $data;
    }
}
