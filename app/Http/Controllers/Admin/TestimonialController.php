<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    use HandlesImageUploads;

    public function index(Request $request)
    {
        $query = Testimonial::query()->latest('id');

        match ($request->status) {
            'pending'  => $query->where('is_approved', false),
            'approved' => $query->where('is_approved', true),
            default    => null,
        };
        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('customer_name', 'like', "%{$search}%")->orWhere('content', 'like', "%{$search}%"));
        }

        $testimonials = $query->paginate(15)->withQueryString();
        $pending = Testimonial::where('is_approved', false)->count();

        return view('admin.testimonials.index', compact('testimonials', 'pending'));
    }

    public function create()
    {
        return view('admin.testimonials.form', ['testimonial' => new Testimonial(['rating' => 5, 'is_approved' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('customer_photo')) {
            $data['customer_photo'] = $this->storeImage($request->file('customer_photo'), 'testimonials');
        }

        Testimonial::create($data);

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimoni ditambahkan.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.form', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $data = $this->validated($request);
        if ($request->hasFile('customer_photo')) {
            $this->deleteImage($testimonial->customer_photo);
            $data['customer_photo'] = $this->storeImage($request->file('customer_photo'), 'testimonials');
        }

        $testimonial->update($data);

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimoni diperbarui.');
    }

    /** Approve / sembunyikan. Hanya yang approved tampil di website (PRD 6.4). */
    /** Sematkan / lepas sematan di beranda. Hanya testimoni yang tampil, dan maksimal 6 yang disematkan. */
    public function toggleFeatured(Testimonial $testimonial)
    {
        if (!$testimonial->is_featured) {
            if (!$testimonial->is_approved) {
                return back()->with('error', 'Setujui testimoni ini dulu sebelum disematkan.');
            }
            if (Testimonial::where('is_featured', true)->count() >= 6) {
                return back()->with('error', 'Maksimal 6 testimoni disematkan. Lepas salah satu terlebih dulu.');
            }
        }

        $testimonial->update(['is_featured' => !$testimonial->is_featured]);

        activity('testimoni')->performedOn($testimonial)
            ->log($testimonial->is_featured ? 'Testimoni disematkan di beranda' : 'Sematan testimoni dilepas');

        return back()->with('success', $testimonial->is_featured ? 'Testimoni disematkan di beranda.' : 'Sematan dilepas.');
    }

    public function toggleApproval(Testimonial $testimonial)
    {
        // Disembunyikan = otomatis tidak lagi disematkan.
        $testimonial->update(['is_approved' => !$testimonial->is_approved] + ($testimonial->is_approved ? ['is_featured' => false] : []));

        activity('testimoni')->performedOn($testimonial)
            ->log($testimonial->is_approved ? 'Testimoni disetujui' : 'Testimoni disembunyikan');

        return back()->with('success', $testimonial->is_approved ? 'Testimoni ditampilkan di website.' : 'Testimoni disembunyikan.');
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return back()->with('success', 'Testimoni dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_name'  => 'required|string|max:100',
            'content'        => 'required|string|min:10|max:1000',
            'rating'         => 'required|integer|between:1,5',
            'customer_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        $data['is_approved'] = $request->boolean('is_approved');
        unset($data['customer_photo']);

        return $data;
    }
}
