@props(['url'])
@php
    $siteName = \App\Models\Setting::get('site_name', config('app.name'));
    $logo = \App\Models\Setting::get('site_logo');
@endphp
<tr>
<td class="header">
<a href="{{ url('/') }}" style="display: inline-block;">
@if($logo)
<img src="{{ media_url($logo) }}" alt="{{ $siteName }}" height="48" style="height: 48px; width: auto; border: 0;">
@else
{{ $siteName }}
@endif
</a>
</td>
</tr>
