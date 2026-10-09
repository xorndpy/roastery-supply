<footer class="bg-stone-900 text-stone-300 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">

            {{-- Brand --}}
            <div>
                <h3 class="text-white text-xl font-bold mb-4">{{ $site['site_name'] }}</h3>
                @if($site['tagline'])<p class="text-sm">{{ $site['tagline'] }}</p>@endif
            </div>

            {{-- Katalog --}}
            <div>
                <h4 class="text-white font-semibold mb-4">Katalog</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('products.index') }}" class="hover:text-orange-500">Semua Produk</a></li>
                    <li><a href="{{ route('branches.index') }}" class="hover:text-orange-500">Cabang</a></li>
                </ul>
            </div>

            {{-- Info --}}
            <div>
                <h4 class="text-white font-semibold mb-4">Informasi</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('about') }}" class="hover:text-orange-500">Tentang Kami</a></li>
                    <li><a href="{{ route('contact.index') }}" class="hover:text-orange-500">Kontak</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-orange-500">FAQ</a></li>
                </ul>
            </div>

            {{-- Kontak: hanya yang sudah diisi di Pengaturan --}}
            <div>
                <h4 class="text-white font-semibold mb-4">Kontak</h4>
                <ul class="space-y-2 text-sm">
                    @if($site['contact_address'])<li class="whitespace-pre-line">{{ $site['contact_address'] }}</li>@endif
                    @if($site['contact_email'])<li><a href="mailto:{{ $site['contact_email'] }}" class="hover:text-orange-500">{{ $site['contact_email'] }}</a></li>@endif
                    @if($site['contact_phone'])<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $site['contact_phone']) }}" class="hover:text-orange-500">{{ $site['contact_phone'] }}</a></li>@endif
                    @if($site['contact_whatsapp'])<li><a href="https://wa.me/{{ preg_replace('/\D/', '', $site['contact_whatsapp']) }}" target="_blank" rel="noopener noreferrer" class="hover:text-orange-500">WhatsApp</a></li>@endif
                    @if(!$site['contact_address'] && !$site['contact_email'] && !$site['contact_phone'] && !$site['contact_whatsapp'])
                        <li><a href="{{ route('contact.index') }}" class="hover:text-orange-500">Kirim pesan</a></li>
                    @endif
                </ul>

                @php
                    $socials = ['Instagram' => $site['social_instagram'], 'Facebook' => $site['social_facebook'], 'TikTok' => $site['social_tiktok'], 'YouTube' => $site['social_youtube']];
                @endphp
                @if(collect($socials)->filter()->isNotEmpty())
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-4 text-sm">
                        @foreach($socials as $label => $url)
                            @if($url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="hover:text-orange-500">{{ $label }}</a>@endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="border-t border-stone-800 mt-8 pt-8 text-sm text-center">
            &copy; {{ date('Y') }} {{ $site['site_name'] }}. All rights reserved.
        </div>
    </div>
</footer>
