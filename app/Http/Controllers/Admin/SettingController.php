<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        $groups = config('cms.settings');
        $values = Setting::stored();

        // Hanya baca: kunci API tetap di environment server, tidak pernah ditampilkan utuh.
        $mid = app(\App\Services\Payments\MidtransService::class);
        $ck = (string) $mid->clientKey();
        $midtrans = [
            'configured' => $mid->configured(),
            'production' => $mid->isProduction(),
            'client_key' => $ck === '' ? null : (strlen($ck) > 14 ? substr($ck, 0, 9) . '••••' . substr($ck, -4) : '••••'),
            'webhook' => route('webhooks.midtrans'),
            'expiry_hours' => round(((int) config('services.midtrans.expiry_minutes', 1440)) / 60, 1),
            'inline' => (bool) config('services.midtrans.process_inline'),
        ];

        return view('admin.settings.index', compact('groups', 'values', 'midtrans'));
    }

    public function update(Request $request)
    {
        $definitions = Setting::definitions();
        $rules = [];

        foreach ($definitions as $key => $def) {
            $max = $def['max'] ?? 255;
            $rules[$key] = match ($def['type']) {
                'image'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'number'   => "nullable|integer|min:0|max:{$max}",
                'select'   => ['nullable', \Illuminate\Validation\Rule::in(array_keys($def['options'] ?? []))],
                'url'      => "nullable|url:http,https|max:{$max}",
                'email'    => "nullable|email|max:{$max}",
                'textarea' => "nullable|string|max:{$max}",
                default    => "nullable|string|max:{$max}",
            };
        }
        $rules['contact_whatsapp'] = 'nullable|regex:/^[0-9]{8,16}$/';
        $rules['remove_site_logo'] = 'nullable|boolean';
        $rules['ga4_id'] = ['nullable', 'regex:/^G-[A-Z0-9]{6,12}$/'];
        $rules['meta_pixel_id'] = ['nullable', 'regex:/^[0-9]{8,20}$/'];
        $rules['installment_rate'] = 'nullable|numeric|between:0,10';

        $data = $request->validate($rules, [
            'contact_whatsapp.regex' => 'WhatsApp hanya angka, format 628xxxxxxxxxx.',
        ]);

        $changed = [];
        $stored = Setting::stored();

        foreach ($definitions as $key => $def) {
            $group = collect(config('cms.settings'))->search(fn ($fields) => isset($fields[$key])) ?: 'general';

            if ($def['type'] === 'image') {
                $old = $stored[$key] ?? null;

                if ($request->hasFile($key)) {
                    $this->deleteImage($old);
                    Setting::set($key, $this->storeImage($request->file($key), 'settings'), $group);
                    $changed[] = $key;
                } elseif ($request->boolean('remove_' . $key) && $old) {
                    $this->deleteImage($old);
                    Setting::set($key, null, $group);
                    $changed[] = $key;
                }

                continue;
            }

            $new = isset($data[$key]) ? trim((string) $data[$key]) : null;
            $new = $new === '' ? null : $new;

            if (($stored[$key] ?? null) !== $new) {
                Setting::set($key, $new, $group);
                $changed[] = $key;
            }
        }

        if ($changed) {
            activity('pengaturan')->causedBy(auth()->user())
                ->withProperties(['keys' => $changed])
                ->log('Pengaturan situs diubah: ' . implode(', ', $changed));
        }

        // Tetap di tab yang sedang dibuka.
        return redirect()->route('admin.settings.index', array_filter(['tab' => $request->input('tab')]))
            ->with('success', $changed ? 'Pengaturan disimpan.' : 'Tidak ada perubahan.');
    }
}
