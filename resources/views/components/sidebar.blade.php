@php
    $menus = [
        [
            'group' => 'Utama',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => '📊'],
            ],
        ],
        [
            'group' => 'Katalog',
            'items' => [
                ['label' => 'Produk', 'route' => 'admin.products.index', 'icon' => '📦'],
                ['label' => 'Kategori', 'route' => 'admin.categories.index', 'icon' => '🏷️'],
                ['label' => 'Merek', 'route' => 'admin.brands.index', 'icon' => '🔖'],
                ['label' => 'Stok', 'route' => 'admin.stock.index', 'icon' => '📁'],
            ],
        ],
        [
            'group' => 'Penjualan',
            'items' => [
                ['label' => 'Pesanan', 'route' => 'admin.orders.index', 'icon' => '🛒'],
                ['label' => 'Pembayaran', 'route' => 'admin.payments.index', 'icon' => '💳'],
                ['label' => 'Pengiriman', 'route' => 'admin.shipments.index', 'icon' => '🚚'],
                ['label' => 'Retur', 'route' => 'admin.returns.index', 'icon' => '🔄'],
                ['label' => 'Pelanggan', 'route' => 'admin.customers.index', 'icon' => '👥'],
            ],
        ],
        [
            'group' => 'Pemasaran',
            'items' => [
                ['label' => 'Voucher', 'route' => 'admin.vouchers.index', 'icon' => '🎫'],
                ['label' => 'Banner', 'route' => 'admin.banners.index', 'icon' => '🖼️'],
                ['label' => 'Testimoni', 'route' => 'admin.testimonials.index', 'icon' => '⭐'],
            ],
        ],
        [
            'group' => 'CMS',
            'items' => [
                ['label' => 'FAQ', 'route' => 'admin.faqs.index', 'icon' => '❓'],
                ['label' => 'Partner', 'route' => 'admin.partners.index', 'icon' => '💼'],
                ['label' => 'Cabang', 'route' => 'admin.branches.index', 'icon' => '📍'],
                ['label' => 'Pesan Masuk', 'route' => 'admin.messages.index', 'icon' => '✉️'],
                ['label' => 'Pengaturan', 'route' => 'admin.settings.index', 'icon' => '⚙️'],
            ],
        ],
        [
            'group' => 'Laporan',
            'items' => [
                ['label' => 'Laporan', 'route' => 'admin.reports.index', 'icon' => '📈'],
            ],
        ],
        [
            'group' => 'Administrasi',
            'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => '👤'],
                ['label' => 'Roles', 'route' => 'admin.roles.index', 'icon' => '🛡️'],
                ['label' => 'Activity Log', 'route' => 'admin.logs.index', 'icon' => '📋'],
            ],
        ],
    ];
@endphp

<div class="flex flex-col h-full">
    {{-- Logo --}}
    <div class="h-16 flex items-center px-6 border-b border-stone-800 flex-shrink-0">
        <a href="{{ route('admin.dashboard') }}" class="text-xl font-bold tracking-tight">
            ROASTERY<span class="text-orange-500">.</span>
            <span class="text-xs text-stone-500 font-normal ml-1">admin</span>
        </a>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-6">

        @foreach($menus as $menu)
            <div>
                <div class="px-3 mb-2 text-xs font-semibold text-stone-500 uppercase tracking-wider">
                    {{ $menu['group'] }}
                </div>
                <div class="space-y-1">
                    @foreach($menu['items'] as $item)
                        @php
                            $isActive = request()->routeIs($item['route']);
                        @endphp
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
                                  {{ $isActive
                                      ? 'bg-orange-500 text-white font-medium'
                                      : 'text-stone-300 hover:bg-stone-800 hover:text-white' }}">
                            <span class="text-base">{{ $item['icon'] }}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    {{-- Footer --}}
    <div class="p-4 border-t border-stone-800 text-xs text-stone-500 flex-shrink-0">
        v1.0 — {{ now()->format('Y') }}
    </div>
</div>