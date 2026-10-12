{{-- Latar panel brand. Dengan foto: foto + animasi "settle" 5 detik. Tanpa foto: gradasi espresso + pola biji kopi. --}}
@if (!empty($photo))
    <img src="{{ $photo }}" alt="" class="auth-photo" decoding="async">
@else
    <div class="absolute inset-0 bg-gradient-to-br from-[#24150e] via-[#35200f] to-brand"></div>
    <div class="auth-beans absolute inset-0"></div>
    <div class="auth-glow absolute -right-24 top-1/4 h-96 w-96 rounded-full bg-orange-500/30 blur-3xl"></div>
    <div class="auth-glow absolute -left-20 bottom-0 h-80 w-80 rounded-full bg-orange-300/15 blur-3xl"></div>
@endif
