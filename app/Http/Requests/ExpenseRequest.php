<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin dijaga middleware can:*_expenses di route
    }

    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:3072'],
            'is_recurring' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_date.required' => 'Tanggal wajib diisi.',
            'expense_date.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'category.in' => 'Kategori tidak valid.',
            'description.required' => 'Keterangan wajib diisi.',
            'amount.required' => 'Jumlah wajib diisi.',
            'amount.min' => 'Jumlah minimal Rp 1.',
            'attachment.mimes' => 'Lampiran harus berupa JPG, PNG, atau PDF.',
            'attachment.max' => 'Lampiran maksimal 3 MB.',
        ];
    }
}
