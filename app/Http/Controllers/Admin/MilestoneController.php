<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function index()
    {
        return view('admin.milestones.index', ['milestones' => Milestone::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.milestones.form', ['milestone' => new Milestone(['sort_order' => (int) Milestone::max('sort_order') + 1, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $m = Milestone::create($this->validated($request));
        activity('milestone')->performedOn($m)->causedBy(auth()->user())->log('Milestone ditambah: ' . $m->title);

        return redirect()->route('admin.milestones.index')->with('success', 'Milestone ditambahkan.');
    }

    public function edit(Milestone $milestone)
    {
        return view('admin.milestones.form', compact('milestone'));
    }

    public function update(Request $request, Milestone $milestone)
    {
        $milestone->update($this->validated($request));
        activity('milestone')->performedOn($milestone)->causedBy(auth()->user())->log('Milestone diubah: ' . $milestone->title);

        return redirect()->route('admin.milestones.index')->with('success', 'Milestone diperbarui.');
    }

    public function destroy(Milestone $milestone)
    {
        $milestone->delete();

        return back()->with('success', 'Milestone dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'year'        => 'required|string|max:30',
            'title'       => 'required|string|max:120',
            'description' => 'nullable|string|max:400',
            'sort_order'  => 'nullable|integer|min:0|max:65000',
        ], ['year.required' => 'Tahun/periode wajib diisi.', 'title.required' => 'Judul wajib diisi.']);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
