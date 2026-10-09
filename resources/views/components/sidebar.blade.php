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
                            @if($item['route'] === 'admin.messages.index' && $unreadMessages > 0)
                                <span class="ml-auto bg-orange-500 text-white text-xs font-semibold rounded-full px-2 py-0.5">{{ $unreadMessages }}</span>
                            @endif
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