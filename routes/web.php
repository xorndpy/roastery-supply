<?php

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
Route::post('/kontak', [ContactController::class, 'store'])->name('contact.send');
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

        // Keranjang Belanja (Cart)
        Route::prefix('cart')->as('cart.')->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('/', [CartController::class, 'store'])->name('store');
            Route::patch('/{id}', [CartController::class, 'update'])->name('update');
            Route::delete('/{id}', [CartController::class, 'destroy'])->name('destroy');
        });

        // Checkout
        Route::prefix('checkout')->as('checkout.')->group(function () {
            Route::get('/', [CheckoutController::class, 'index'])->name('index');
            Route::post('/', [CheckoutController::class, 'store'])->name('store');
        });

        // Riwayat Order & Detail
        Route::prefix('orders')->as('orders.')->group(function () {
            Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
            Route::get('/{orderNumber}', [CustomerOrderController::class, 'show'])->name('show');
            Route::post('/{orderNumber}/pay', [CustomerOrderController::class, 'pay'])->name('pay');
        });

        // Pengajuan Retur
        Route::prefix('returns')->as('returns.')->group(function () {
            Route::get('/', [CustomerReturnController::class, 'index'])->name('index');
            Route::get('/create/{orderNumber?}', [CustomerReturnController::class, 'create'])->name('create');
            Route::post('/', [CustomerReturnController::class, 'store'])->name('store');
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
// Middleware: auth + role:admin|super_admin
// =========================================================================
Route::prefix('admin')
    ->as('admin.')
    ->middleware(['auth', 'role:admin|super_admin'])
    ->group(function () {

        // Dashboard Admin
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard.alt');

        // ---------------------------------------------------------------
        // Katalog
        // ---------------------------------------------------------------
        Route::resource('products', ProductController::class);
        Route::patch('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');
        Route::patch('products/{product}/toggle-featured', [ProductController::class, 'toggleFeatured'])->name('products.toggle-featured');

        Route::resource('categories', CategoryController::class);
        Route::patch('categories/{category}/toggle-active', [CategoryController::class, 'toggleActive'])->name('categories.toggle-active');

        Route::resource('brands', BrandController::class);
        Route::patch('brands/{brand}/toggle-active', [BrandController::class, 'toggleActive'])->name('brands.toggle-active');

        // ---------------------------------------------------------------
        // Stok
        // ---------------------------------------------------------------
        Route::prefix('stock')->as('stock.')->group(function () {
            Route::get('/', [StockController::class, 'index'])->name('index');
            Route::post('/adjust', [StockController::class, 'adjust'])->name('adjust');
            Route::get('/history', [StockController::class, 'history'])->name('history');
        });

        // ---------------------------------------------------------------
        // Penjualan - Orders
        // ---------------------------------------------------------------
        Route::prefix('orders')->as('orders.')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index'])->name('index');
            Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
            Route::post('/{order}/confirm-payment', [AdminOrderController::class, 'confirmPayment'])->name('confirm-payment');
            Route::post('/{order}/process', [AdminOrderController::class, 'process'])->name('process');
            Route::post('/{order}/ship', [AdminOrderController::class, 'ship'])->name('ship');
            Route::post('/{order}/complete', [AdminOrderController::class, 'complete'])->name('complete');
            Route::post('/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('cancel');
            Route::get('/{order}/invoice', [AdminOrderController::class, 'invoice'])->name('invoice');
        });

        // ---------------------------------------------------------------
        // Penjualan - Payments
        // ---------------------------------------------------------------
        Route::prefix('payments')->as('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::post('/{id}/verify', [PaymentController::class, 'verify'])->name('verify');
            Route::post('/{id}/reject', [PaymentController::class, 'reject'])->name('reject');
        });

        // Penjualan - Shipments
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');

        // Penjualan - Returns
        Route::prefix('returns')->as('returns.')->group(function () {
            Route::get('/', [AdminReturnController::class, 'index'])->name('index');
            Route::get('/{returnOrder}', [AdminReturnController::class, 'show'])->name('show');
            Route::post('/{returnOrder}/approve', [AdminReturnController::class, 'approve'])->name('approve');
            Route::post('/{returnOrder}/reject', [AdminReturnController::class, 'reject'])->name('reject');
        });

        // Pelanggan
        Route::prefix('customers')->as('customers.')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->name('index');
            Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        });

        // ---------------------------------------------------------------
        // Pemasaran
        // ---------------------------------------------------------------
        Route::resource('vouchers', VoucherController::class);
        Route::resource('banners', BannerController::class);

        Route::prefix('testimonials')->as('testimonials.')->group(function () {
            Route::get('/', [TestimonialController::class, 'index'])->name('index');
            Route::patch('/{testimonial}/toggle-approval', [TestimonialController::class, 'toggleApproval'])->name('toggle-approval');
            Route::delete('/{testimonial}', [TestimonialController::class, 'destroy'])->name('destroy');
        });

        // ---------------------------------------------------------------
        // CMS
        // ---------------------------------------------------------------
        Route::resource('faqs', FaqController::class);
        Route::resource('partners', PartnerController::class);
        Route::resource('branches', BranchController::class);

        Route::prefix('messages')->as('messages.')->group(function () {
            Route::get('/', [ContactMessageController::class, 'index'])->name('index');
            Route::get('/{message}', [ContactMessageController::class, 'show'])->name('show');
            Route::patch('/{message}/read', [ContactMessageController::class, 'markAsRead'])->name('mark-as-read');
            Route::delete('/{message}', [ContactMessageController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('settings')->as('settings.')->group(function () {
            Route::get('/', [SettingController::class, 'index'])->name('index');
            Route::put('/', [SettingController::class, 'update'])->name('update');
        });

        // ---------------------------------------------------------------
        // Laporan
        // ---------------------------------------------------------------
        Route::prefix('reports')->as('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('/export-excel', [ReportController::class, 'exportExcel'])->name('export-excel');
            Route::get('/export-pdf', [ReportController::class, 'exportPdf'])->name('export-pdf');
        });

        // ---------------------------------------------------------------
        // Administrasi
        // ---------------------------------------------------------------
        Route::resource('users', UserController::class);

        Route::prefix('roles')->as('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        });

        Route::get('/logs', fn() => view('admin.logs.index'))->name('logs.index');
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