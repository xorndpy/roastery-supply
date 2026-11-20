@php
  $s = fn ($k) => \App\Models\Setting::get($k);
  $siteName = $s('site_name') ?: config('app.name');
  $logo = pdf_image_data_uri($s('invoice_logo') ?: $s('site_logo'), 520);
@endphp
<table style="width:100%;border-collapse:collapse"><tr>
  <td style="width:58%;vertical-align:top">
    @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}" style="height:54px">@else<div style="font-size:24px;font-weight:bold;letter-spacing:2px">{{ strtoupper($siteName) }}</div>@endif
    @if($s('tagline'))<div style="font-size:9.5px;letter-spacing:1.4px;margin-top:6px">{{ $s('tagline') }}</div>@endif
  </td>
  <td style="text-align:right;font-size:9.5px;vertical-align:top">
    @if($s('contact_address')){!! nl2br(e($s('contact_address'))) !!}<br>@endif
    @if($s('contact_email')){{ $s('contact_email') }}<br>@endif
    @if($s('contact_phone')){{ $s('contact_phone') }}@endif
  </td>
</tr></table>
<div style="border-top:1px solid #1c1917;margin:14px 0 12px"></div>
<div style="font-size:26px;font-weight:bold">{{ $title }}</div>
<div style="font-size:11px;margin-top:3px">{{ $subtitle ?? '' }}</div>
