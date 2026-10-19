@php
    $menus = [
        [
            'group' => 'Utama',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'group' => 'Katalog',
            'items' => [
                ['label' => 'Produk', 'route' => 'admin.products.index', 'icon' => 'box'],
                ['label' => 'Kategori', 'route' => 'admin.categories.index', 'icon' => 'tag'],
                ['label' => 'Merek', 'route' => 'admin.brands.index', 'icon' => 'bookmark'],
                ['label' => 'Stok', 'route' => 'admin.stock.index', 'icon' => 'archive'],
            ],
        ],
        [
            'group' => 'Penjualan',
            'items' => [
                ['label' => 'Pesanan', 'route' => 'admin.orders.index', 'icon' => 'bag'],
                ['label' => 'Pembayaran', 'route' => 'admin.payments.index', 'icon' => 'card'],
                ['label' => 'Pengiriman', 'route' => 'admin.shipments.index', 'icon' => 'truck'],
                ['label' => 'Tarif Kirim', 'route' => 'admin.shipping.index', 'icon' => 'coins'],
                ['label' => 'Retur', 'route' => 'admin.returns.index', 'icon' => 'undo'],
                ['label' => 'Pelanggan', 'route' => 'admin.customers.index', 'icon' => 'users'],
            ],
        ],
        [
            'group' => 'Pemasaran',
            'items' => [
                ['label' => 'Voucher', 'route' => 'admin.vouchers.index', 'icon' => 'ticket'],
                ['label' => 'Banner', 'route' => 'admin.banners.index', 'icon' => 'image'],
                ['label' => 'Testimoni', 'route' => 'admin.testimonials.index', 'icon' => 'star'],
            ],
        ],
        [
            'group' => 'CMS',
            'items' => [
                ['label' => 'FAQ', 'route' => 'admin.faqs.index', 'icon' => 'help'],
                ['label' => 'Partner', 'route' => 'admin.partners.index', 'icon' => 'briefcase'],
                ['label' => 'Cabang', 'route' => 'admin.branches.index', 'icon' => 'pin'],
                ['label' => 'Pesan Masuk', 'route' => 'admin.messages.index', 'icon' => 'mail'],
                ['label' => 'Pengaturan', 'route' => 'admin.settings.index', 'icon' => 'sliders'],
            ],
        ],
        [
            'group' => 'Laporan',
            'items' => [
                ['label' => 'Laporan', 'route' => 'admin.reports.index', 'icon' => 'chart'],
            ],
        ],
        [
            'group' => 'Administrasi',
            'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'user'],
                ['label' => 'Roles', 'route' => 'admin.roles.index', 'icon' => 'shield'],
                ['label' => 'Activity Log', 'route' => 'admin.logs.index', 'icon' => 'list'],
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

    // Ikon garis 24x24 (stroke). Kunci di atas.
    $icons = [
        'dashboard' => '<rect x="3.5" y="3.5" width="7" height="8" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="14.5" width="7" height="6" rx="1.5"/>',
        'box' => '<path d="M3.5 7.5l8.5-4 8.5 4v9l-8.5 4-8.5-4v-9z"/><path d="M3.5 7.5L12 11.5l8.5-4M12 11.5v9"/>',
        'tag' => '<path d="M3.5 12.4V4.5h7.9l9.1 9.1-7.9 7.9-9.1-9.1z"/><circle cx="8" cy="9" r="1.2"/>',
        'bookmark' => '<path d="M6.5 3.5h11v17L12 16.5l-5.5 4v-17z"/>',
        'archive' => '<rect x="3.5" y="4" width="17" height="4.5" rx="1"/><path d="M5 8.5v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-10M10 12.5h4"/>',
        'bag' => '<path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
        'card' => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M3 10h18M7 15h3"/>',
        'truck' => '<path d="M3 6.5h11v9H3z"/><path d="M14 9.5h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'coins' => '<circle cx="12" cy="12" r="8.5"/><path d="M14.6 9.3c-.5-.9-1.4-1.3-2.6-1.3-1.4 0-2.4.7-2.4 1.8 0 2.4 5 1.2 5 3.6 0 1.1-1.1 1.9-2.6 1.9-1.2 0-2.2-.4-2.7-1.4M12 6.5V8M12 16v1.5"/>',
        'undo' => '<path d="M4 12a8 8 0 1 0 2.6-5.9"/><path d="M4 4.5v4h4"/>',
        'users' => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3 19c.6-3.3 3-5 6-5s5.4 1.7 6 5"/><path d="M16 5.6a3.2 3.2 0 0 1 0 5.8M18 14.4c1.7.6 2.7 2.1 3 4.6"/>',
        'ticket' => '<path d="M4 7h16v3.2a1.8 1.8 0 0 0 0 3.6V17H4v-3.2a1.8 1.8 0 0 0 0-3.6V7z"/><path d="M14 7v10" stroke-dasharray="2 2"/>',
        'image' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 17l5-4.5 4 3.5 3-2.5 4 3.5"/>',
        'star' => '<path d="M12 3.8l2.5 5.1 5.6.8-4.1 4 1 5.6-5-2.7-5 2.7 1-5.6-4.1-4 5.6-.8L12 3.8z"/>',
        'help' => '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.6a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1.1.9-1.1 1.7M12 16.8v.1"/>',
        'briefcase' => '<rect x="3.5" y="7.5" width="17" height="12" rx="2"/><path d="M9 7.5V6a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 6v1.5M3.5 13h17"/>',
        'pin' => '<path d="M12 21s6.5-5.6 6.5-11a6.5 6.5 0 1 0-13 0c0 5.4 6.5 11 6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
        'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M4 7.5l8 6 8-6"/>',
        'sliders' => '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
        'chart' => '<path d="M4.5 20V4.5M4.5 20h15"/><rect x="8" y="11" width="3" height="6" rx=".6"/><rect x="13.5" y="7" width="3" height="10" rx=".6"/>',
        'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c.8-3.6 3.5-5.5 7-5.5s6.2 1.9 7 5.5"/>',
        'shield' => '<path d="M12 3.5l7.5 3v5.4c0 4.4-3.1 7.6-7.5 8.6-4.4-1-7.5-4.2-7.5-8.6V6.5l7.5-3z"/>',
        'list' => '<rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M9 8.5h6M9 12h6M9 15.5h4"/>',
    ];

    // Badge angka di menu: hal yang menunggu tindakan.
    $navBadges = [
        'admin.messages.index' => $unreadMessages,
        'admin.payments.index' => auth()->user()->can('verify_payments') ? \App\Models\Payment::needsAttention()->count() : 0,
    ];

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
    <div class="flex h-16 shrink-0 items-center border-b border-white/10 px-5">
        <a href="{{ route('admin.dashboard') }}" class="group flex items-center gap-2.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-300">
            <span class="flex h-8 w-8 items-center justify-center rounded-md bg-orange-400/15 text-orange-200 ring-1 ring-inset ring-orange-200/20" aria-hidden="true">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 14h18v6.5a7 7 0 0 1-7 7h-4a7 7 0 0 1-7-7V14z"/><path d="M26 16h1.5a3 3 0 0 1 0 6H25.5"/><path d="M13 9c-1.6-1.4 1.6-2.6 0-4.2M18 9c-1.6-1.4 1.6-2.6 0-4.2M23 9c-1.6-1.4 1.6-2.6 0-4.2"/></svg>
            </span>
            <span class="leading-none">
                <span class="heading block text-[17px] text-white">{{ $site['site_name'] ?? 'Roastery' }}</span>
                <span class="mt-1 block text-[10px] font-medium uppercase tracking-[0.16em] text-stone-400">Admin</span>
            </span>
        </a>
    </div>

    {{-- Nav --}}
    <nav class="dash-scroll flex-1 space-y-4 overflow-y-auto px-3 py-4" aria-label="Menu admin">
        @foreach($menus as $menu)
            <div>
                <div class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">{{ $menu['group'] }}</div>
                @foreach($menu['items'] as $item)
                    @php
                        $isActive = request()->routeIs(str_replace('.index', '.*', $item['route'])) || request()->routeIs($item['route'])
                            || ($item['route'] === 'admin.dashboard' && request()->routeIs('admin.dashboard.alt'));
                        $badge = (int) ($navBadges[$item['route']] ?? 0);
                    @endphp
                    <a href="{{ route($item['route']) }}" data-nav data-nav-group="{{ $menu['group'] }}" data-nav-label="{{ $item['label'] }}"
                       @if($isActive) aria-current="page" @endif
                       class="nav-link group relative flex items-center gap-3 rounded-md px-3 py-[7px] text-[13.5px] transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-300
                              {{ $isActive ? 'bg-white/[0.09] font-medium text-white' : 'text-stone-300 hover:bg-white/[0.05] hover:text-white' }}">
                        @if($isActive)<span class="absolute -left-3 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-r bg-orange-300" aria-hidden="true"></span>@endif
                        <svg class="h-[18px] w-[18px] shrink-0 transition-colors {{ $isActive ? 'text-orange-300' : 'text-stone-500 group-hover:text-stone-300' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$item['icon']] ?? '' !!}</svg>
                        <span>{{ $item['label'] }}</span>
                        @if($badge > 0)
                            <span class="ml-auto min-w-[1.375rem] rounded-full bg-orange-400 px-1.5 py-0.5 text-center text-[11px] font-semibold leading-none text-stone-950 tabular-nums" aria-label="{{ $badge }} menunggu">{{ $badge }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="flex shrink-0 items-center justify-between border-t border-white/10 px-5 py-3.5 text-xs text-stone-500">
        <span>v1.0 — {{ now()->format('Y') }}</span>
        <span class="hidden items-center gap-1.5 lg:flex"><kbd class="rounded border border-white/15 px-1.5 py-0.5 font-sans text-[10px] text-stone-400">Ctrl</kbd><kbd class="rounded border border-white/15 px-1.5 py-0.5 font-sans text-[10px] text-stone-400">K</kbd></span>
    </div>
</div>
