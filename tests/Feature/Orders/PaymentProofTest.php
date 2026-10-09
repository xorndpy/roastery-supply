<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function orderWithProof(string $disk = 'local'): array
    {
        $user = User::factory()->create();
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => 'B', 'email' => $user->email]));
        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id,
            'status' => 'menunggu_bayar', 'subtotal' => 1000, 'total' => 1000,
        ]));
        $path = 'payments/bukti-' . $order->id . '.jpg';
        Storage::disk($disk)->put($path, 'ISI-BUKTI-TRANSFER');
        $payment = Payment::unguarded(fn () => Payment::create(['order_id' => $order->id, 'method' => 'transfer_bca', 'amount' => 1000, 'status' => 'pending', 'proof_image' => $path]));

        return [$user, $order, $payment, $path];
    }

    private function userAs(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    public function test_uploaded_proof_is_stored_privately_not_on_the_public_disk(): void
    {
        [$user, $order] = $this->orderWithProof();

        $this->actingAs($user)->post(route('customer.orders.pay', $order->order_number), [
            'method' => 'transfer_bca', 'proof_image' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHas('success');

        $path = $order->payments()->latest('id')->first()->proof_image;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_owner_can_view_own_proof(): void
    {
        [$user, $order] = $this->orderWithProof();

        $res = $this->actingAs($user)->get(route('customer.orders.proof', $order->order_number));
        $res->assertOk();
        $this->assertStringContainsString('private', $res->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
    }

    public function test_other_customer_and_guest_cannot_view_proof(): void
    {
        [, $order, $payment] = $this->orderWithProof();
        $stranger = $this->userAs('customer');

        $this->actingAs($stranger)->get(route('customer.orders.proof', $order->order_number))->assertNotFound();
        $this->actingAs($stranger)->get(route('admin.payments.proof', $payment->id))->assertForbidden();

        auth()->logout();
        $this->get(route('customer.orders.proof', $order->order_number))->assertRedirect(route('login'));
        $this->get(route('admin.payments.proof', $payment->id))->assertRedirect(route('login'));
    }

    public function test_staff_and_admin_can_view_any_proof(): void
    {
        [, , $payment] = $this->orderWithProof();

        foreach (['staff', 'admin', 'super_admin'] as $role) {
            $this->actingAs($this->userAs($role))->get(route('admin.payments.proof', $payment->id))->assertOk();
        }
    }

    public function test_legacy_public_file_is_still_served(): void
    {
        [$user, $order, $payment, $path] = $this->orderWithProof('public');

        $this->actingAs($user)->get(route('customer.orders.proof', $order->order_number))->assertOk();
        $this->actingAs($this->userAs('staff'))->get(route('admin.payments.proof', $payment->id))->assertOk();
    }

    public function test_missing_file_gives_404(): void
    {
        [$user, $order, , $path] = $this->orderWithProof();
        Storage::disk('local')->delete($path);

        $this->actingAs($user)->get(route('customer.orders.proof', $order->order_number))->assertNotFound();
    }

    public function test_privatize_command_moves_legacy_files(): void
    {
        [, , $payment, $path] = $this->orderWithProof('public');

        $this->artisan('payments:privatize')->assertSuccessful();

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('ISI-BUKTI-TRANSFER', Storage::disk('local')->get($path));
    }

    public function test_admin_pages_link_to_the_protected_route_not_storage_url(): void
    {
        [, $order, $payment] = $this->orderWithProof();
        $staff = $this->userAs('staff');

        $this->actingAs($staff)->get(route('admin.orders.show', $order->id))
            ->assertOk()->assertSee(route('admin.payments.proof', $payment->id))->assertDontSee('/storage/payments');
        $this->actingAs($staff)->get(route('admin.payments.show', $payment->id))
            ->assertOk()->assertSee(route('admin.payments.proof', $payment->id))->assertDontSee('/storage/payments');
    }

    public function test_mail_test_command_sends_a_message(): void
    {
        $this->artisan('mail:test', ['email' => 'uji@example.com'])->assertSuccessful();

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
    }

    public function test_staff_can_verify_or_reject_from_the_payment_pages(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        [, $order, $payment] = $this->orderWithProof();
        $staff = $this->userAs('staff');

        $this->actingAs($staff)->get(route('admin.payments.index'))->assertOk()->assertSee($order->order_number);

        $this->actingAs($staff)->post(route('admin.payments.reject', $payment->id), ['note' => 'Buram'])->assertSessionHas('success');
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame('menunggu_bayar', $order->fresh()->status);

        [, $order2, $payment2] = $this->orderWithProof();
        $this->actingAs($staff)->post(route('admin.payments.verify', $payment2->id))->assertSessionHas('success');
        $this->assertSame('dikonfirmasi', $order2->fresh()->status);
        $this->assertSame($staff->id, $payment2->fresh()->verified_by);
    }
}
