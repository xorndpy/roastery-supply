<?php

namespace App\Http\Requests;

use App\Models\ServiceTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warranty_id' => ['nullable', 'integer'],
            'product_name' => ['required_without:warranty_id', 'nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:60'],
            'issue_type' => ['required', Rule::in(array_keys(ServiceTicket::ISSUE_TYPES))],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'contact_phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_name.required_without' => 'Pilih unit dari daftar garansi atau isi nama produk.',
            'issue_type.required' => 'Pilih jenis masalah.',
            'description.required' => 'Jelaskan masalahnya.',
            'description.min' => 'Jelaskan masalahnya minimal 10 karakter agar teknisi paham.',
            'contact_phone.required' => 'Nomor yang bisa dihubungi wajib diisi.',
            'contact_phone.regex' => 'Format nomor telepon tidak valid.',
            'photos.max' => 'Maksimal 3 foto.',
            'photos.*.image' => 'Berkas harus berupa foto.',
            'photos.*.mimes' => 'Foto harus JPG atau PNG.',
            'photos.*.max' => 'Tiap foto maksimal 3 MB.',
        ];
    }
}
