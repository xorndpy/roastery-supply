<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $query = Faq::query()->orderBy('category')->orderBy('sort_order')->orderBy('id');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('question', 'like', "%{$search}%")->orWhere('answer', 'like', "%{$search}%"));
        }

        $faqs = $query->get()->groupBy('category');
        $categories = Faq::select('category')->distinct()->orderBy('category')->pluck('category');

        return view('admin.faqs.index', compact('faqs', 'categories'));
    }

    public function create()
    {
        return view('admin.faqs.form', ['faq' => new Faq(['is_active' => true]), 'categories' => $this->categories()]);
    }

    public function store(Request $request)
    {
        Faq::create($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ ditambahkan.');
    }

    public function edit(Faq $faq)
    {
        return view('admin.faqs.form', ['faq' => $faq, 'categories' => $this->categories()]);
    }

    public function update(Request $request, Faq $faq)
    {
        $faq->update($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ diperbarui.');
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return back()->with('success', 'FAQ dihapus.');
    }

    private function categories()
    {
        return Faq::select('category')->distinct()->orderBy('category')->pluck('category');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category'   => 'required|string|max:100',
            'question'   => 'required|string|max:255',
            'answer'     => 'required|string|max:5000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
