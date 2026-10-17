<?php

namespace Tests\Feature;

use App\Models\Commodity;
use App\Models\Enquiry;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\EnquiryReceived;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        'home' => 'Building Trust, Delivering Excellence',
        'public.trade' => 'What we trade',
        'public.buyers' => 'Request a quote',
        'public.suppliers' => 'Register your interest',
        'public.how' => 'A disciplined path',
        'public.about' => 'Who we are',
        'public.contact' => 'Get in touch',
        'public.privacy' => 'Privacy notice',
        'public.terms' => 'Terms of use',
        'public.risk' => 'Risk notice',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
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

    // ---- the pages ----

    public function test_every_public_page_loads_for_a_visitor_with_the_company_details(): void
    {
        $titles = [];
        foreach (self::PAGES as $route => $text) {
            $page = $this->get(route($route))->assertOk()->assertSee($text);
            $html = $page->getContent();

            $page->assertSee('AFISAB NIG. LTD')->assertSee('info@afisab.com')->assertSee('+234 805 752 0669')->assertSee('wa.me/2348057520669', false);
            $this->assertStringNotContainsString('Laravel', $html, "$route mentions the framework");
            $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]{40,}"/', $html, "$route needs a description");
            $this->assertStringContainsString('<link rel="canonical" href="'.route($route).'">', $html);
            preg_match('/<title>(.*?)<\/title>/', $html, $m);
            $titles[] = $m[1];
        }
        $this->assertSame(count($titles), count(array_unique($titles)), 'every page has its own title');
    }

    public function test_the_company_is_described_as_an_organization_for_search_engines(): void
    {
        $html = $this->get(route('home'))->getContent();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $data = json_decode($m[1], true);

        $this->assertSame('Organization', $data['@type']);
        $this->assertSame('AFISAB NIG. LTD', $data['name']);
        $this->assertSame('info@afisab.com', $data['email']);
        $this->assertSame('+2348057520669', $data['telephone']);
        $this->assertSame('Building Trust, Delivering Excellence', $data['slogan']);
    }

    public function test_the_site_makes_no_claim_the_company_has_not_confirmed(): void
    {
        foreach (self::PAGES as $route => $_) {
            $text = strip_tags($this->get(route($route))->getContent());
            foreach (['years of experience', 'ISO ', 'nationwide', 'award', 'guaranteed returns', 'tonnes per year', 'export-ready', 'certified'] as $claim) {
                $this->assertStringNotContainsString($claim, $text, "$route makes an unconfirmed claim: $claim");
            }
        }
    }

    public function test_the_investment_programme_is_not_promoted_until_it_is_switched_on(): void
    {
        foreach (['home', 'public.about', 'public.risk'] as $route) {
            $page = $this->get(route($route))->assertDontSee('Become an investor')->assertDontSee('₦500,000')->assertDontSee('investor programme');
            $page->assertSee('Investor portal'); // a plain sign-in link only
        }
        $this->get(route('public.investors'))->assertNotFound();
        $this->get(route('sitemap'))->assertDontSee('/investors');

        config(['afisab.public_investor_page' => true]);
        $this->get(route('public.investors'))->assertOk()->assertSee('Become an investor')->assertSee('not guaranteed');
        $this->get(route('home'))->assertSee('href="'.route('public.investors').'"', false);
        $this->get(route('sitemap'))->assertSee(route('public.investors'));
        $this->get(route('public.risk'))->assertSee('capital is at risk');
    }

    public function test_commodities_come_from_the_database(): void
    {
        $page = $this->get(route('public.trade'))->assertOk()->assertSee('Grains')->assertSee('Oil seeds')->assertSee('Tree crops')->assertSee('Cash crops');
        foreach (Commodity::where('is_active', true)->pluck('name') as $name) {
            $page->assertSee($name);
        }

        Commodity::where('name', 'Castor Seeds')->update(['is_active' => false]);
        $this->get(route('public.trade'))->assertDontSee('Castor Seeds');
        $this->get(route('home'))->assertDontSee('Castor Seeds');

        $this->get(route('public.buyers', ['commodity' => 'Maize']))->assertSee('<option value="Maize" selected>', false);
    }

    public function test_sitemap_and_robots(): void
    {
        $sitemap = $this->get(route('sitemap'))->assertOk()->assertHeader('content-type', 'application/xml');
        foreach (array_keys(self::PAGES) as $route) {
            $sitemap->assertSee('<loc>'.route($route).'</loc>', false);
        }

        $robots = $this->get(route('robots'))->assertOk()->getContent();
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /investor', $robots);
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $robots);
    }

    public function test_signed_in_staff_and_investors_still_get_the_app_layout_on_their_own_pages(): void
    {
        $director = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($director)->get(route('admin.dashboard'))->assertOk()->assertSee('Back office')->assertDontSee('Request a quote');
    }

    // ---- enquiries ----

    public function test_a_buyer_can_request_a_quote_and_the_shared_mailbox_is_told(): void
    {
        Notification::fake();

        $this->post(route('public.enquiries.store'), $this->enquiry('quote'))->assertRedirect(route('public.buyers').'#enquiry')->assertSessionHas('enquiry_sent');

        $e = Enquiry::firstOrFail();
        $this->assertSame(['quote', 'Ngozi Okafor', 'Okafor Feeds Ltd', 'Maize', '50 tonnes', 'Kano', Enquiry::NEW], [$e->type, $e->name, $e->company, $e->commodity, $e->quantity, $e->location, $e->status]);
        $this->assertNotNull($e->consented_at);
        Notification::assertSentOnDemand(EnquiryReceived::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'info@afisab.com' && $n->enquiry->is($e));
        $this->assertDatabaseHas('activity_log', ['description' => 'Website enquiry received']);

        $this->get(route('public.buyers'))->assertSee('Thank you');
    }

    public function test_the_alert_email_names_the_sender_and_what_they_want(): void
    {
        Notification::route('mail', 'info@afisab.com')->notify(new EnquiryReceived(Enquiry::create([
            'type' => 'quote', 'name' => 'Ngozi Okafor', 'company' => 'Okafor Feeds Ltd', 'email' => 'ngozi@okaforfeeds.example', 'phone' => '08031234567',
            'commodity' => 'Maize', 'quantity' => '50 tonnes', 'message' => '<b>Urgent</b> please', 'consented_at' => now(),
        ])));

        $mail = collect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage())->first(fn ($m) => str_contains($m->getSubject(), 'Quote request'));
        $this->assertNotNull($mail);
        $this->assertSame('Quote request: Maize from Okafor Feeds Ltd', $mail->getSubject());
        $this->assertSame('info@afisab.com', $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('ngozi@okaforfeeds.example', $mail->getHtmlBody());
        $this->assertStringContainsString('50 tonnes', $mail->getHtmlBody());
        $this->assertStringNotContainsString('<b>Urgent</b>', $mail->getHtmlBody(), 'markup in the message is not passed through');
    }

    public function test_suppliers_and_general_enquiries_work_and_go_back_to_their_own_page(): void
    {
        $this->post(route('public.enquiries.store'), $this->enquiry('supplier', ['email' => '']))->assertRedirect(route('public.suppliers').'#enquiry');
        $this->post(route('public.enquiries.store'), $this->enquiry('general', ['phone' => '']))->assertRedirect(route('public.contact').'#enquiry');

        $this->assertSame(['supplier', 'general'], Enquiry::orderBy('id')->pluck('type')->all());
        $this->assertNull(Enquiry::where('type', 'supplier')->first()->email);
    }

    public function test_forms_validate_what_each_type_needs(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // many posts here; the limit has its own test
        $post = fn (string $type, array $over) => $this->post(route('public.enquiries.store'), $this->enquiry($type, $over));

        $post('quote', ['consent' => ''])->assertSessionHasErrors('consent');
        $post('quote', ['phone' => ''])->assertSessionHasErrors('phone');
        $post('quote', ['phone' => 'abc'])->assertSessionHasErrors('phone');
        $post('quote', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $post('quote', ['name' => ''])->assertSessionHasErrors('name');
        $post('supplier', ['commodity' => ''])->assertSessionHasErrors('commodity');
        $post('supplier', ['location' => ''])->assertSessionHasErrors('location');
        $post('general', ['message' => ''])->assertSessionHasErrors('message');
        $post('general', ['message' => str_repeat('x', 2001)])->assertSessionHasErrors('message');

        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['type' => 'hacking']))->assertStatus(422);
        $this->assertSame(0, Enquiry::count());
    }

    public function test_bots_are_turned_away_without_anything_being_stored_or_emailed(): void
    {
        Notification::fake();

        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['website' => 'http://spam.example']))->assertStatus(422);                 // the hidden field was filled
        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['started' => Crypt::encryptString((string) now()->timestamp)]))->assertStatus(422); // sent instantly
        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['started' => Crypt::encryptString((string) now()->subHours(3)->timestamp)]))->assertStatus(422); // expired
        $this->post(route('public.enquiries.store'), $this->enquiry('quote', ['started' => 'forged']))->assertStatus(422);                              // tampered
        $this->post(route('public.enquiries.store'), array_diff_key($this->enquiry('quote'), ['started' => 1]))->assertStatus(422);                       // missing

        $this->assertSame(0, Enquiry::count());
        Notification::assertNothingSent();
    }

    public function test_the_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('public.enquiries.store'), $this->enquiry('general'))->assertRedirect();
        }
        $this->post(route('public.enquiries.store'), $this->enquiry('general'))->assertStatus(429);
        $this->assertSame(6, Enquiry::count());
    }

    // ---- the back-office inbox ----

    public function test_the_inbox_is_for_sales_and_directors_only(): void
    {
        $e = Enquiry::create(['type' => 'quote', 'name' => 'Ngozi', 'email' => 'n@example.com', 'phone' => '08031234567', 'consented_at' => now()]);

        foreach (['sales', 'super-admin'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get(route('admin.enquiries'))->assertOk()->assertSee('Ngozi');
            $this->actingAs($user)->get(route('admin.enquiries.show', $e))->assertOk();
        }
        foreach (['accounts', 'hr', 'procurement', 'auditor', 'compliance', 'investor'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get(route('admin.enquiries'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.enquiries.show', $e))->assertForbidden();
            $this->actingAs($user)->put(route('admin.enquiries.update', $e), ['status' => 'closed'])->assertForbidden();
        }
    }

    public function test_staff_work_an_enquiry_and_the_inbox_filters_it(): void
    {
        $sales = User::factory()->create(['name' => 'Sam Sales'])->assignRole('sales');
        $new = Enquiry::create(['type' => 'quote', 'name' => 'Alpha Buyer', 'commodity' => 'Maize', 'phone' => '08030000001', 'consented_at' => now()]);
        $done = Enquiry::create(['type' => 'general', 'name' => 'Beta Asker', 'phone' => '08030000002', 'status' => 'closed', 'consented_at' => now()]);

        $this->actingAs($sales)->get(route('admin.dashboard'))->assertSee('New website enquiries');
        $this->actingAs($sales)->get(route('admin.enquiries', ['status' => 'new']))->assertSee('Alpha Buyer')->assertDontSee('Beta Asker');
        $this->actingAs($sales)->get(route('admin.enquiries', ['type' => 'general']))->assertSee('Beta Asker')->assertDontSee('Alpha Buyer');
        $this->actingAs($sales)->get(route('admin.enquiries', ['q' => 'Maize']))->assertSee('Alpha Buyer')->assertDontSee('Beta Asker');
        $this->actingAs($sales)->get(route('admin.enquiries', ['q' => '%']))->assertSee('No enquiries match');
        $this->actingAs($sales)->get(route('admin.enquiries', ['status' => 'bogus']))->assertSessionHasErrors('status');

        $this->actingAs($sales)->put(route('admin.enquiries.update', $new), ['status' => 'contacted', 'internal_note' => 'Called, sending prices'])->assertSessionHasNoErrors();
        $new->refresh();
        $this->assertSame(['contacted', 'Called, sending prices', $sales->id], [$new->status, $new->internal_note, $new->handled_by]);
        $this->actingAs($sales)->put(route('admin.enquiries.update', $new), ['status' => 'unknown'])->assertSessionHasErrors('status');
        $this->actingAs($sales)->get(route('admin.dashboard'))->assertDontSee('New website enquiries'); // nothing new is left
    }

    public function test_a_supplier_enquiry_can_become_a_supplier_record_once(): void
    {
        $director = User::factory()->create()->assignRole('super-admin');
        $sales = User::factory()->create()->assignRole('sales');
        $supplierEnquiry = Enquiry::create(['type' => 'supplier', 'name' => 'Musa Ibrahim', 'company' => 'Benue Growers Coop', 'phone' => '08031234567', 'commodity' => 'Sesame Seeds', 'quantity' => '20 tonnes', 'location' => 'Makurdi', 'consented_at' => now()]);
        $quote = Enquiry::create(['type' => 'quote', 'name' => 'Buyer', 'phone' => '08031234567', 'email' => 'b@example.com', 'consented_at' => now()]);

        $this->actingAs($sales)->post(route('admin.enquiries.supplier', $supplierEnquiry))->assertForbidden(); // sales cannot manage suppliers
        $this->actingAs($director)->post(route('admin.enquiries.supplier', $supplierEnquiry))->assertSessionHasNoErrors();

        $supplier = Supplier::firstOrFail();
        $this->assertSame(['Benue Growers Coop', 'Makurdi', '08031234567'], [$supplier->name, $supplier->location, $supplier->phone]);
        $this->assertStringContainsString('Sesame Seeds (20 tonnes)', $supplier->notes);
        $this->assertSame($supplier->id, $supplierEnquiry->fresh()->supplier_id);
        $this->assertSame('contacted', $supplierEnquiry->fresh()->status);

        $this->actingAs($director)->post(route('admin.enquiries.supplier', $supplierEnquiry))->assertSessionHasErrors('enquiry'); // not twice
        $this->actingAs($director)->post(route('admin.enquiries.supplier', $quote))->assertSessionHasErrors('enquiry');            // not a quote
        $this->assertSame(1, Supplier::count());
    }
}
