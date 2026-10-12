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
                ['label' => 'Tarif Kirim', 'route' => 'admin.shipping.index', 'icon' => '💲'],
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

    // Hanya tampilkan menu yang boleh diakses user ini (permission sama dengan yang menjaga route-nya).
    $menuPermissions = [
        'admin.products.index' => 'view_products', 'admin.categories.index' => 'view_categories',
        'admin.brands.index' => 'view_brands', 'admin.stock.index' => 'view_stock',
        'admin.orders.index' => 'view_orders', 'admin.payments.index' => 'verify_payments',
        'admin.shipments.index' => 'manage_shipments', 'admin.shipping.index' => 'view_shipping_rates', 'admin.returns.index' => 'view_returns',
        'admin.customers.index' => 'view_customers', 'admin.vouchers.index' => 'view_vouchers',
        'admin.banners.index' => 'view_banners', 'admin.testimonials.index' => 'view_testimonials',
        'admin.faqs.index' => 'view_faqs', 'admin.partners.index' => 'view_partners',
        'admin.branches.index' => 'view_branches', 'admin.messages.index' => 'view_contact_messages',
        'admin.settings.index' => 'view_settings', 'admin.reports.index' => 'view_reports',
        'admin.users.index' => 'view_users', 'admin.roles.index' => 'view_roles',
        'admin.logs.index' => 'view_activity_logs',
    ];
    $unreadMessages = auth()->user()->can('view_contact_messages') ? \App\Models\ContactMessage::where('is_read', false)->count() : 0;

    $menus = collect($menus)
        ->map(function ($menu) use ($menuPermissions) {
            $menu['items'] = collect($menu['items'])
                ->filter(fn ($i) => !isset($menuPermissions[$i['route']]) || auth()->user()->can($menuPermissions[$i['route']]))
                ->values()->all();
            return $menu;
        })
        ->filter(fn ($m) => count($m['items']) > 0)
        ->values()->all();
@endphp

<div class="flex h-full flex-col">
    {{-- Logo --}}
    <div class="flex h-16 shrink-0 items-center border-b border-white/10 px-6">
        <a href="{{ route('admin.dashboard') }}" class="heading text-lg text-white">
            {{ $site['site_name'] ?? 'Roastery' }}<span class="ml-2 font-sans text-[10px] font-medium uppercase tracking-[0.14em] text-stone-400">admin</span>
        </a>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 space-y-5 overflow-y-auto py-4" aria-label="Menu admin">
        @foreach($menus as $menu)
            <div>
                <div class="mb-1.5 px-6 text-[10px] font-semibold uppercase tracking-[0.14em] text-stone-500">{{ $menu['group'] }}</div>
                @foreach($menu['items'] as $item)
                    @php $isActive = request()->routeIs(str_replace('.index', '.*', $item['route'])) || request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center border-l-[3px] px-6 py-2 text-[13.5px] transition
                              {{ $isActive ? 'border-orange-400 bg-white/10 font-medium text-white' : 'border-transparent text-stone-300 hover:bg-white/5 hover:text-white' }}">
                        <span>{{ $item['label'] }}</span>
                        @if($item['route'] === 'admin.messages.index' && $unreadMessages > 0)
                            <span class="ml-auto rounded-full bg-orange-500 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $unreadMessages }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-white/10 px-6 py-4 text-xs text-stone-500">v1.0 — {{ now()->format('Y') }}</div>
</div>
