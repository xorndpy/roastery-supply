<?php

use App\Http\Controllers\Webhooks\MidtransWebhookController;
use App\Http\Controllers\Web\OnlinePaymentController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// CONTROLLERS
// =========================================================================

// Public / Web
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\KatalogController;
use App\Http\Controllers\Web\CartController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Web\OrderController as CustomerOrderController;
use App\Http\Controllers\Web\ProfileController as CustomerProfileController;
use App\Http\Controllers\Web\ReturnController as CustomerReturnController;

// Admin
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ReturnEvidenceController;
use App\Http\Controllers\Web\TestimonialController as CustomerTestimonialController;

/*
|--------------------------------------------------------------------------
| Web Routes - Roastery Supply E-Commerce
|--------------------------------------------------------------------------
|
| Struktur:
| 1. Rute Publik (Guest & Umum)
| 2. Dashboard Redirector
| 3. Rute Customer (Portal Pelanggan)
| 4. Rute Admin & Staff (Admin Panel)
| 5. Breeze Authentication & Profile
|
*/

// =========================================================================
// 1. RUTE PUBLIK
// =========================================================================
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/katalog', [KatalogController::class, 'index'])->name('products.index');
Route::get('/produk/{slug}', [KatalogController::class, 'show'])->name('products.show');
Route::get('/tentang', [HomeController::class, 'about'])->name('about');
Route::get('/faq', [HomeController::class, 'faq'])->name('faq');
Route::get('/kontak', [ContactController::class, 'index'])->name('contact.index');
Route::post('/kontak', [ContactController::class, 'store'])->middleware('throttle:5,10')->name('contact.send');
Route::get('/cabang', [HomeController::class, 'branches'])->name('branches.index');


// =========================================================================
// 2. DASHBOARD REDIRECTOR
// =========================================================================
Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user && $user->hasAnyRole(['super_admin', 'admin', 'staff'])) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('customer.dashboard');
})->middleware(['auth'])->name('dashboard');


// Keranjang Belanja: guest boleh belanja (session). Login baru diminta saat checkout.
// Nama route tetap customer.cart.* agar view lama tidak berubah.
Route::prefix('customer/cart')->as('customer.cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/', [CartController::class, 'store'])->name('store');
    Route::patch('/{id}', [CartController::class, 'update'])->name('update');
    Route::delete('/{id}', [CartController::class, 'destroy'])->name('destroy');
});

// =========================================================================
// 3. RUTE CUSTOMER (Portal Pelanggan)
// Middleware: auth
// =========================================================================
Route::prefix('customer')
    ->as('customer.')
    ->middleware(['auth'])
    ->group(function () {

        // Dashboard Customer
        Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('dashboard');

        // Checkout
        Route::prefix('checkout')->as('checkout.')->group(function () {
            Route::get('/', [CheckoutController::class, 'index'])->name('index');
            Route::post('/', [CheckoutController::class, 'store'])->name('store');
            Route::get('/shipping', [CheckoutController::class, 'shippingOptions'])->middleware('throttle:60,1')->name('shipping');
            Route::post('/voucher', [CheckoutController::class, 'applyVoucher'])->middleware('throttle:10,1')->name('voucher');
            Route::delete('/voucher', [CheckoutController::class, 'removeVoucher'])->name('voucher.remove');
        });

        // Riwayat Order & Detail
        Route::prefix('orders')->as('orders.')->group(function () {
            Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
            Route::get('/{orderNumber}', [CustomerOrderController::class, 'show'])->name('show');
            Route::post('/{orderNumber}/pay', [CustomerOrderController::class, 'pay'])->name('pay');
            Route::post('/{orderNumber}/pay-online', [OnlinePaymentController::class, 'start'])->middleware('throttle:20,1')->name('pay-online');
            Route::get('/{orderNumber}/payment-status', [OnlinePaymentController::class, 'status'])->middleware('throttle:90,1')->name('payment-status');
            Route::post('/{orderNumber}/cancel', [CustomerOrderController::class, 'cancel'])->name('cancel');
            Route::post('/{orderNumber}/received', [CustomerOrderController::class, 'received'])->name('received');
            Route::get('/{orderNumber}/invoice', [CustomerOrderController::class, 'invoice'])->name('invoice');
            Route::get('/{orderNumber}/proof', [PaymentProofController::class, 'customer'])->name('proof');
            Route::post('/{orderNumber}/testimonial', [CustomerTestimonialController::class, 'store'])->name('testimonial');
        });

        // Pengajuan Retur
        Route::prefix('returns')->as('returns.')->group(function () {
            Route::get('/', [CustomerReturnController::class, 'index'])->name('index');
            Route::get('/create/{orderNumber?}', [CustomerReturnController::class, 'create'])->name('create');
            Route::post('/', [CustomerReturnController::class, 'store'])->name('store');
            Route::get('/{id}/evidence/{n}', [ReturnEvidenceController::class, 'customer'])->whereNumber(['id', 'n'])->name('evidence');
            Route::get('/{id}', [CustomerReturnController::class, 'show'])->name('show');
        });

        // Profil Pelanggan
        Route::prefix('profile')->as('profile.')->group(function () {
            Route::get('/', [CustomerProfileController::class, 'edit'])->name('edit');
            Route::patch('/', [CustomerProfileController::class, 'update'])->name('update');
        });
    });


// =========================================================================
// 4. RUTE ADMIN & STAFF (Admin Panel)
// Pintu masuk: role staff/admin/super_admin. Setiap fitur dijaga permission
// (can:<permission>) sehingga matriks Role & Hak Akses (menu Role) benar-benar berlaku.
// Super Admin lolos semua lewat Gate::before di AppServiceProvider.
// =========================================================================
Route::prefix('admin')
    ->as('admin.')
    ->middleware(['auth', 'role:staff|admin|super_admin'])
    ->group(function () {

        // Resource dengan izin terpisah per aksi. create harus terdaftar sebelum {param}.
        $crud = function (string $name, string $controller, string $perm, array $except = []) {
            $reg = fn (string $ability, array $only) => Route::middleware("can:{$ability}_{$perm}")
                ->group(fn () => Route::resource($name, $controller)->only(array_diff($only, $except)));

            $reg('create', ['create', 'store']);
            $reg('edit', ['edit', 'update']);
            $reg('delete', ['destroy']);
            $reg('view', ['index', 'show']);
        };

        // Dashboard
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard.alt');

        // Katalog
        $crud('products', ProductController::class, 'products');
        Route::patch('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active')->middleware('can:edit_products');
        Route::patch('products/{product}/toggle-featured', [ProductController::class, 'toggleFeatured'])->name('products.toggle-featured')->middleware('can:edit_products');

        $crud('categories', CategoryController::class, 'categories');
        Route::patch('categories/{category}/toggle-active', [CategoryController::class, 'toggleActive'])->name('categories.toggle-active')->middleware('can:edit_categories');

        $crud('brands', BrandController::class, 'brands');
        Route::patch('brands/{brand}/toggle-active', [BrandController::class, 'toggleActive'])->name('brands.toggle-active')->middleware('can:edit_brands');

        // Stok
        Route::prefix('stock')->as('stock.')->group(function () {
            Route::get('/', [StockController::class, 'index'])->name('index')->middleware('can:view_stock');
            Route::get('/history', [StockController::class, 'history'])->name('history')->middleware('can:view_stock');
            Route::post('/adjust', [StockController::class, 'adjust'])->name('adjust')->middleware('can:manage_stock');
        });

        // Penjualan - Orders
        Route::prefix('orders')->as('orders.')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index'])->name('index')->middleware('can:view_orders');
            Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show')->middleware('can:view_orders');
            Route::post('/{order}/confirm-payment', [AdminOrderController::class, 'confirmPayment'])->name('confirm-payment')->middleware('can:verify_payments');
            Route::post('/{order}/process', [AdminOrderController::class, 'process'])->name('process')->middleware('can:edit_orders');
            Route::post('/{order}/ship', [AdminOrderController::class, 'ship'])->name('ship')->middleware('can:manage_shipments');
            Route::post('/{order}/complete', [AdminOrderController::class, 'complete'])->name('complete')->middleware('can:edit_orders');
            Route::post('/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('cancel')->middleware('can:cancel_orders');
            Route::get('/{order}/invoice', [AdminOrderController::class, 'invoice'])->name('invoice')->middleware('can:download_invoices');
        });

        // Penjualan - Payments
        Route::prefix('payments')->as('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index')->middleware('can:verify_payments');
            Route::get('/{payment}/proof', [PaymentProofController::class, 'admin'])->name('proof')->middleware('can:verify_payments');
            Route::get('/{payment}', [PaymentController::class, 'show'])->name('show')->middleware('can:verify_payments');
            Route::post('/{payment}/verify', [PaymentController::class, 'verify'])->name('verify')->middleware('can:verify_payments');
            Route::post('/{payment}/reject', [PaymentController::class, 'reject'])->name('reject')->middleware('can:reject_payments');
            Route::post('/{payment}/sync', [PaymentController::class, 'sync'])->name('sync')->middleware('can:verify_payments');
            Route::post('/{payment}/approve', [PaymentController::class, 'approve'])->name('approve')->middleware('can:verify_payments');
            Route::post('/{payment}/resolve', [PaymentController::class, 'resolve'])->name('resolve')->middleware('can:verify_payments');
        });

        // Penjualan - Shipments
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index')->middleware('can:manage_shipments');
        Route::patch('/shipments/{shipment}', [ShipmentController::class, 'update'])->name('shipments.update')->middleware('can:manage_shipments');

        // Tarif pengiriman per wilayah
        Route::prefix('shipping-methods')->as('shipping.')->group(function () {
            Route::get('/', [ShippingMethodController::class, 'index'])->name('index')->middleware('can:view_shipping_rates');
            Route::get('/create', [ShippingMethodController::class, 'create'])->name('create')->middleware('can:manage_shipping_rates');
            Route::post('/', [ShippingMethodController::class, 'store'])->name('store')->middleware('can:manage_shipping_rates');
            Route::get('/{shippingMethod}/edit', [ShippingMethodController::class, 'edit'])->name('edit')->middleware('can:view_shipping_rates');
            Route::put('/{shippingMethod}', [ShippingMethodController::class, 'update'])->name('update')->middleware('can:manage_shipping_rates');
            Route::delete('/{shippingMethod}', [ShippingMethodController::class, 'destroy'])->name('destroy')->middleware('can:manage_shipping_rates');
            Route::post('/{shippingMethod}/rates', [ShippingMethodController::class, 'storeRate'])->name('rates.store')->middleware('can:manage_shipping_rates');
            Route::post('/{shippingMethod}/rates/bulk', [ShippingMethodController::class, 'bulkRates'])->name('rates.bulk')->middleware('can:manage_shipping_rates');
            Route::delete('/{shippingMethod}/rates/{rate}', [ShippingMethodController::class, 'destroyRate'])->name('rates.destroy')->middleware('can:manage_shipping_rates');
        });

        // Penjualan - Returns
        Route::prefix('returns')->as('returns.')->group(function () {
            Route::get('/', [AdminReturnController::class, 'index'])->name('index')->middleware('can:view_returns');
            Route::get('/{returnOrder}/evidence/{n}', [ReturnEvidenceController::class, 'admin'])->whereNumber('n')->name('evidence')->middleware('can:view_returns');
            Route::get('/{returnOrder}', [AdminReturnController::class, 'show'])->name('show')->middleware('can:view_returns');
            Route::post('/{returnOrder}/approve', [AdminReturnController::class, 'approve'])->name('approve')->middleware('can:approve_returns');
            Route::post('/{returnOrder}/reject', [AdminReturnController::class, 'reject'])->name('reject')->middleware('can:reject_returns');
            Route::post('/{returnOrder}/refunded', [AdminReturnController::class, 'refunded'])->name('refunded')->middleware('can:approve_returns');
        });

        // Pelanggan
        Route::prefix('customers')->as('customers.')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->name('index')->middleware('can:view_customers');
            Route::get('/export', [CustomerController::class, 'export'])->name('export')->middleware('can:export_customers');
            Route::get('/{customer}', [CustomerController::class, 'show'])->name('show')->middleware('can:view_customers');
        });

        // Pemasaran
        $crud('vouchers', VoucherController::class, 'vouchers', ['show']);
        $crud('banners', BannerController::class, 'banners', ['show']);
        Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder')->middleware('can:edit_banners');
        Route::prefix('testimonials')->as('testimonials.')->group(function () {
            Route::get('/', [TestimonialController::class, 'index'])->name('index')->middleware('can:view_testimonials');
            Route::get('/create', [TestimonialController::class, 'create'])->name('create')->middleware('can:approve_testimonials');
            Route::post('/', [TestimonialController::class, 'store'])->name('store')->middleware('can:approve_testimonials');
            Route::get('/{testimonial}/edit', [TestimonialController::class, 'edit'])->name('edit')->middleware('can:approve_testimonials');
            Route::put('/{testimonial}', [TestimonialController::class, 'update'])->name('update')->middleware('can:approve_testimonials');
            Route::patch('/{testimonial}/toggle-approval', [TestimonialController::class, 'toggleApproval'])->name('toggle-approval')->middleware('can:approve_testimonials');
            Route::delete('/{testimonial}', [TestimonialController::class, 'destroy'])->name('destroy')->middleware('can:delete_testimonials');
        });

        // Konten (CMS)
        $crud('faqs', FaqController::class, 'faqs', ['show']);
        $crud('partners', PartnerController::class, 'partners', ['show']);
        $crud('branches', BranchController::class, 'branches', ['show']);
        Route::prefix('settings')->as('settings.')->group(function () {
            Route::get('/', [SettingController::class, 'index'])->name('index')->middleware('can:view_settings');
            Route::put('/', [SettingController::class, 'update'])->name('update')->middleware('can:edit_settings');
        });
        Route::prefix('messages')->as('messages.')->group(function () {
            Route::get('/', [ContactMessageController::class, 'index'])->name('index')->middleware('can:view_contact_messages');
            Route::get('/{message}', [ContactMessageController::class, 'show'])->name('show')->middleware('can:view_contact_messages');
            Route::patch('/{message}/read', [ContactMessageController::class, 'markAsRead'])->name('mark-as-read')->middleware('can:reply_contact_messages');
            Route::patch('/{message}/replied', [ContactMessageController::class, 'markReplied'])->name('mark-replied')->middleware('can:reply_contact_messages');
            Route::delete('/{message}', [ContactMessageController::class, 'destroy'])->name('destroy')->middleware('can:delete_contact_messages');
        });

        // Laporan
        Route::prefix('reports')->as('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index')->middleware('can:view_reports');
            Route::get('/sales', [ReportController::class, 'sales'])->name('sales')->middleware('can:view_reports');
            Route::get('/export-excel', [ReportController::class, 'exportExcel'])->name('export-excel')->middleware('can:export_reports');
            Route::get('/export-pdf', [ReportController::class, 'exportPdf'])->name('export-pdf')->middleware('can:export_reports');
        });

        // Administrasi (PRD 6.7): user, role, log
        $crud('users', UserController::class, 'users');
        Route::prefix('roles')->as('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index')->middleware('can:view_roles');
            Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit')->middleware('can:manage_roles');
            Route::put('/{role}', [RoleController::class, 'update'])->name('update')->middleware('can:manage_roles');
        });
        Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index')->middleware('can:view_activity_logs');
    });


// =========================================================================
// 5. BREEZE AUTHENTICATION & PROFILE
// =========================================================================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Notifikasi payment gateway (publik; keaslian dijamin tanda tangan, bukan sesi login).
