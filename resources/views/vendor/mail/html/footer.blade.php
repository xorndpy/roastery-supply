@php
    $siteName = \App\Models\Setting::get('site_name', config('app.name'));
    $address = \App\Models\Setting::get('contact_address');
    $email = \App\Models\Setting::get('contact_email');
    $phone = \App\Models\Setting::get('contact_phone');
    $hours = \App\Models\Setting::get('contact_hours');
    $npwp = \App\Models\Setting::get('company_npwp');
@endphp
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<p style="margin: 0 0 6px; font-size: 13px;"><strong>{{ $siteName }}</strong></p>
@if($address)<p style="margin: 0 0 4px; font-size: 12px;">{{ $address }}</p>@endif
@if($email || $phone)<p style="margin: 0 0 4px; font-size: 12px;">@if($email){{ $email }}@endif @if($email && $phone) &middot; @endif @if($phone){{ $phone }}@endif</p>@endif
@if($hours)<p style="margin: 0 0 4px; font-size: 12px;">Jam operasional: {{ $hours }}</p>@endif
@if($npwp)<p style="margin: 0 0 4px; font-size: 12px;">NPWP: {{ $npwp }}</p>@endif
<p style="margin: 10px 0 0; font-size: 12px;">
<a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a> &middot;
<a href="{{ route('legal.terms') }}">Syarat &amp; Ketentuan</a> &middot;
<a href="{{ route('legal.returns') }}">Retur &amp; Garansi</a>
</p>
<p style="margin: 10px 0 0; font-size: 11px;">Email ini dikirim otomatis terkait akun atau pesanan Anda di {{ $siteName }}.</p>
</td>
</tr>
</table>
</td>
</tr>
