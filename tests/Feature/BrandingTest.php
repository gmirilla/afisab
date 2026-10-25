<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Services\Documents\DocumentService;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function investment(): Investment
    {
        $user = User::factory()->create(['name' => 'Ada Obi'])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::first(), 1_000_000_00);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh()->load(['user', 'plan', 'payments']);
    }

    public function test_the_logo_files_are_in_place_and_sized_for_their_use(): void
    {
        foreach (['logo.png' => 'image/png', 'logo-mark.png' => 'image/png', 'favicon.png' => 'image/png', 'logo-full.jpg' => 'image/jpeg'] as $file => $mime) {
            $info = getimagesize(public_path("image/$file"));
            $this->assertNotFalse($info, "$file must be a valid image");
            $this->assertSame($mime, $info['mime'], $file);
        }
        $this->assertLessThan(60_000, filesize(public_path('image/logo-mark.png')), 'the navbar image should stay small');
        $this->assertLessThan(80_000, filesize(public_path('image/logo-full.jpg')), 'the PDF image should stay small');
        $mark = getimagesize(public_path('image/logo-mark.png'));
        $this->assertSame($mark[0], $mark[1], 'the mark is square so it fits its tile');
    }

    public function test_both_layouts_show_the_logo_in_the_navbar_and_footer_and_as_the_tab_icon(): void
    {
        $this->seed(DatabaseSeeder::class);
        $mark = asset('image/logo-mark.png');

        foreach ([route('home'), route('login')] as $url) { // public site layout, then the application layout
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertGreaterThanOrEqual(2, substr_count($html, $mark), "$url: logo in the navbar and the footer");
            $this->assertStringContainsString('href="'.asset('image/favicon.png').'"', $html, "$url: tab icon");
            $this->assertStringContainsString('alt="AFISAB NIG. LTD logo"', $html, "$url: navbar logo has alt text");
            $this->assertStringNotContainsString('>A</span>', $html, "$url: the old letter placeholder is gone");
        }

        $director = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($director)->get(route('admin.dashboard'))->assertOk()->assertSee($mark, false);
    }

    public function test_the_certificate_and_statement_carry_the_logo_and_fall_back_to_text_without_it(): void
    {
        $this->seed(DatabaseSeeder::class);
        $investment = $this->investment();
        $service = app(DocumentService::class);
        $logo = (new \ReflectionMethod($service, 'logoDataUri'))->invoke($service);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $logo);

        $data = ['investment' => $investment, 'verifyUrl' => 'x', 'qr' => 'data:image/png;base64,AAAA', 'position' => ['principal' => 1, 'earned' => 0, 'paid' => 0, 'payable' => 0, 'current_value' => 1, 'pending_entries' => 0, 'recorded_net_profit' => 0, 'cycles' => 0], 'entries' => collect(), 'generatedAt' => now()];

        $context = $service->certificateContext($investment);
        $withLogo = view('pdf.certificate', $context)->render();
        $this->assertStringContainsString('<img class="lockup" src="data:image/png;base64,', $withLogo);
        $this->assertStringNotContainsString('class="brand"', $withLogo);
        $this->assertStringContainsString('<img class="logo" src="data:image/jpeg;base64,', view('pdf.statement', $data + ['logo' => $logo])->render());

        $withoutLogo = view('pdf.certificate', ['assets' => []] + $context)->render();
        $this->assertStringContainsString('<div class="brand">AFISAB NIG. LTD</div>', $withoutLogo);
        $this->assertStringNotContainsString('<img class="lockup"', $withoutLogo);

        // And the real PDFs still build.
        $this->assertStringStartsWith('%PDF', $service->certificate($investment));
        $this->assertStringStartsWith('%PDF', $service->statement($investment));
    }
}
