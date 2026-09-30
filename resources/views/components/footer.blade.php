<footer class="bg-stone-900 text-stone-300 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            
            {{-- Brand --}}
            <div>
                <h3 class="text-white text-xl font-bold mb-4">ROASTERY<span class="text-orange-500">.</span></h3>
                <p class="text-sm">Mesin kopi profesional untuk kedai kopi serius.</p>
            </div>

            {{-- Katalog --}}
            <div>
                <h4 class="text-white font-semibold mb-4">Katalog</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('products.index') }}" class="hover:text-orange-500">Semua Produk</a></li>
                    <li><a href="{{ route('products.index') }}?category=espresso-machine" class="hover:text-orange-500">Espresso Machine</a></li>
                    <li><a href="{{ route('products.index') }}?category=grinder" class="hover:text-orange-500">Grinder</a></li>
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

            {{-- Kontak --}}
            <div>
                <h4 class="text-white font-semibold mb-4">Kontak</h4>
                <ul class="space-y-2 text-sm">
                    <li>📍 Jakarta, Indonesia</li>
                    <li>✉️ hello@roastery.id</li>
                    <li>📞 +62 21 1234 5678</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-stone-800 mt-8 pt-8 text-sm text-center">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</footer>