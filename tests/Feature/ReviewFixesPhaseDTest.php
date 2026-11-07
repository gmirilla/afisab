<?php

namespace Tests\Feature;

use App\Models\Commodity;
use App\Models\Enquiry;
use App\Notifications\EnquiryAcknowledged;
use App\Notifications\EnquiryReceived;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Fixes from the site review, phase D: the public forms and the sign-up page. */
class ReviewFixesPhaseDTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    private function enquiry(string $type, array $over = []): array
    {
        $base = ['type' => $type, 'name' => 'Ngozi Okafor', 'company' => 'Okafor Feeds Ltd', 'email' => 'ngozi@okaforfeeds.example', 'phone' => '0803 123 4567',
            'consent' => '1', 'started' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp), 'website' => ''];

        return $over + $base + match ($type) {
            'quote' => ['commodity' => 'Maize', 'quantity' => '50 tonnes', 'location' => 'Kano', 'message' => 'Need weekly deliveries.'],
            'supplier' => ['commodity' => 'Sesame Seeds', 'quantity' => '20 tonnes', 'location' => 'Makurdi, Benue', 'message' => ''],
            default => ['message' => 'Do you supply feed mills in Ogun State?'],
        };
    }

    public function test_both_the_buyer_and_supplier_forms_require_a_commodity(): void
    {
        foreach (['public.buyers', 'public.suppliers'] as $page) {
            $html = $this->get(route($page))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<select name="items\[0\]\[commodity\]" required#', $html, $page);
            $this->assertStringContainsString('Other (type its name)', $html);
        }

        foreach (['quote', 'supplier'] as $type) {
            $this->post(route('public.enquiries.store'), $this->enquiry($type, ['commodity' => '']))->assertSessionHasErrors('items.0.commodity');
        }
        $this->assertSame(0, Enquiry::count());

        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['commodity' => 'Teff', 'message' => 'Maize and sorghum']))->assertSessionHasNoErrors();
        $this->assertSame(1, Enquiry::count());
    }

    public function test_the_general_contact_form_still_has_no_commodity(): void
    {
        $this->post(route('public.enquiries.store'), $this->enquiry('general'))->assertSessionHasNoErrors();
        $this->assertSame(1, Enquiry::count());
    }

    public function test_the_person_who_wrote_in_gets_a_short_acknowledgement_with_nothing_they_typed_in_it(): void
    {
        Notification::fake();

        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['name' => 'Click Here Win Prize', 'message' => 'http://evil.example send money']))->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(EnquiryReceived::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'info@afisab.com');
        Notification::assertSentOnDemand(EnquiryAcknowledged::class, function ($n, $c, $notifiable) {
            $mail = $n->toMail($notifiable);
            $text = $mail->subject.' '.implode(' ', array_map(fn ($l) => is_string($l) ? $l : '', array_merge($mail->introLines, [$mail->greeting])));

            return $notifiable->routes['mail'] === 'ngozi@okaforfeeds.example'
                && str_contains($text, 'We received your request') && ! str_contains($text, 'Click Here') && ! str_contains($text, 'evil.example');
        });
    }

    public function test_the_acknowledgement_goes_out_once_an_hour_per_address_and_only_when_an_email_was_given(): void
    {
        Notification::fake();

        $this->post(route('public.enquiries.store'), $this->enquiry('quote'));
        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['name' => 'Again']));
        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['email' => 'NGOZI@okaforfeeds.example']));
        Notification::assertSentOnDemandTimes(EnquiryAcknowledged::class, 1);
        Notification::assertSentOnDemandTimes(EnquiryReceived::class, 3);

        $this->travel(61)->minutes();
        $this->post(route('public.enquiries.store'), $this->enquiry('quote'));
        Notification::assertSentOnDemandTimes(EnquiryAcknowledged::class, 2);

        $this->post(route('public.enquiries.store'), $this->enquiry('supplier', ['email' => '']));
        Notification::assertSentOnDemandTimes(EnquiryAcknowledged::class, 2);
    }

    public function test_a_turned_away_bot_gets_no_acknowledgement(): void
    {
        Notification::fake();

        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['website' => 'http://spam.example']))->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_the_sign_up_and_reset_pages_list_the_password_rules_and_the_script_ticks_them_off(): void
    {
        foreach (['register', 'password.reset'] as $route) {
            $html = $this->get($route === 'register' ? route('register') : route('password.reset', ['token' => 'abc']))->assertOk()->getContent();
            $this->assertStringContainsString('data-password-rules', $html, $route);
            foreach (['At least 10 characters', 'An uppercase letter', 'A lowercase letter', 'A number', 'Both passwords match'] as $rule) {
                $this->assertStringContainsString($rule, $html, $route);
            }
        }

        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('data-password-rules', $js);
        $this->assertStringContainsString('/[A-Z]/', $js);
        $this->assertDoesNotMatchRegularExpression('#<script(?![^>]*src=)[^>]*>#i', $this->get(route('register'))->getContent(), 'no inline script');
    }

    public function test_the_server_still_enforces_the_password_rules(): void
    {
        $body = ['name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '08031234567'];

        $this->post('/register', $body + ['password' => 'alllowercase1', 'password_confirmation' => 'alllowercase1'])->assertSessionHasErrors('password');
        $this->post('/register', $body + ['password' => 'Short1a', 'password_confirmation' => 'Short1a'])->assertSessionHasErrors('password');
        $this->post('/register', $body + ['password' => 'Str0ngPassword1', 'password_confirmation' => 'Different0ne'])->assertSessionHasErrors('password');
    }
}
