<?php

namespace Tests\Feature;

use App\Notifications\MailTestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailAndTrustTest extends TestCase
{
    use RefreshDatabase;

    public function test_perintah_mail_test_mengirim_email_uji(): void
    {
        Notification::fake();

        $this->artisan('mail:test', ['to' => 'pemilik@example.com'])->assertExitCode(0);

        Notification::assertSentOnDemand(MailTestNotification::class);
    }

    public function test_perintah_mail_test_menolak_alamat_tidak_valid(): void
    {
        Notification::fake();

        $this->artisan('mail:test', ['to' => 'bukan-email'])->assertExitCode(1);

        Notification::assertNothingSent();
    }

    public function test_email_uji_memakai_tema_toko(): void
    {
        $html = (string) (new MailTestNotification)->toMail(new \Illuminate\Notifications\AnonymousNotifiable)->render();

        $this->assertStringContainsString(route('legal.terms'), $html);
        $this->assertStringContainsString('Uji pengiriman email', (new MailTestNotification)->toMail(new \Illuminate\Notifications\AnonymousNotifiable)->subject);
    }

    public function test_footer_ringkas_tanpa_strip_tetap_menautkan_kebijakan(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Garansi tercatat per unit')
            ->assertSee(route('legal.terms'), false);
    }
}
