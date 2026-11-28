{{ \App\Models\Setting::get('site_name', config('app.name')) }}
@if(\App\Models\Setting::get('contact_address')){{ \App\Models\Setting::get('contact_address') }}
@endif
@if(\App\Models\Setting::get('contact_email')){{ \App\Models\Setting::get('contact_email') }}
@endif
Kebijakan Privasi: {{ route('legal.privacy') }}
Syarat & Ketentuan: {{ route('legal.terms') }}
