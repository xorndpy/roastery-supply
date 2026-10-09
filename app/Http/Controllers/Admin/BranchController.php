<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::orderBy('name')->get();

        return view('admin.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('admin.branches.form', ['branch' => new Branch(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        Branch::create($this->validated($request));

        return redirect()->route('admin.branches.index')->with('success', 'Cabang ditambahkan.');
    }

    public function edit(Branch $branch)
    {
        return view('admin.branches.form', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $branch->update($this->validated($request));

        return redirect()->route('admin.branches.index')->with('success', 'Cabang diperbarui.');
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return back()->with('success', 'Cabang dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'address'   => 'required|string|max:500',
            'phone'     => 'nullable|string|max:30',
            // koordinat harus berpasangan agar peta bisa ditampilkan
            'latitude'  => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'maps_url'  => 'nullable|url:http,https|max:500',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
