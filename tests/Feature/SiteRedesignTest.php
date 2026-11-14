<?php

namespace Tests\Feature;

use App\Models\Commodity;
use App\Models\CompanyDetail;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\EnquiryReceived;
use App\Support\SiteCommodities;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The public site follows the designer's design: one home page, a catalogue with a page per commodity, and server-side enquiry forms. */
class SiteRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        SiteCommodities::forget();
        Cache::flush();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->travelTo(Carbon::parse('2026-11-14 10:00'));
    }

    private function quote(array $over = []): array
    {
        return $over + ['type' => 'quote', 'full_name' => 'Ngozi Okafor', 'company' => 'Okafor Feeds Ltd', 'email' => 'ngozi@okaforfeeds.example', 'phone' => '0803 123 4567', 'country' => 'Nigeria',
            'buyer_type' => 'feed_manufacturer', 'items' => [['commodity' => 'Maize', 'quantity' => '50', 'unit' => 'MT']], 'destination' => 'Kano', 'consent' => '1',
            'started' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp), 'website' => ''];
    }

    // ---- the design and its files ----

    public function test_the_designers_styles_scripts_fonts_and_every_photograph_are_in_place(): void
    {
        foreach (['css/system.css', 'css/pages.css', 'css/trade.css', 'css/additions.css', 'js/navigation.js', 'js/hero-carousel.js', 'js/catalogue.js', 'js/enquiry-form.js', 'assets/fonts/manrope-latin.woff2', 'assets/fonts/instrument-serif-italic-latin.woff2', 'assets/field-lines.svg'] as $file) {
            $this->assertFileExists(public_path("site/$file"));
        }
        foreach (config('site_commodities.items') as $slug => $c) {
            foreach (['400', '720'] as $w) {
                $this->assertFileExists(public_path("site/assets/images/$slug-$w.webp"), "$slug photo");
            }
            if ($c['closeup']) {
                $this->assertFileExists(public_path('site/assets/images/'.$c['closeup']['image'].'-720.webp'));
            }
        }
        foreach (['hero-1536', 'hero-handling-1280', 'hero-commodities-1280', 'inspection-960', 'warehouse-900'] as $img) {
            $this->assertFileExists(public_path("site/assets/images/$img.webp"));
        }
        $this->assertFileExists(public_path('image/logo-mark.png'), 'our own logo, not the designer\'s');
    }

    public function test_the_commodity_data_is_complete_and_consistent(): void
    {
        $items = config('site_commodities.items');
        $this->assertCount(20, $items);
        foreach ($items as $slug => $c) {
            foreach (['name', 'db_name', 'category', 'category_label', 'summary', 'card', 'image_alt', 'quality', 'packaging', 'sourcing'] as $key) {
                $this->assertNotEmpty($c[$key], "$slug has no $key");
            }
            $this->assertNotEmpty($c['applications'], "$slug has no applications");
            $this->assertSame([], array_diff($c['related'], array_keys($items)), "$slug relates to a commodity that does not exist");
            $this->assertTrue(Commodity::where('name', $c['db_name'])->exists(), "$slug has no matching commodity in the staff list ({$c['db_name']})");
        }
        $this->assertSame([], array_diff(config('site_commodities.featured'), array_keys($items)));
    }

    // ---- the home page ----

    public function test_the_home_page_has_the_designers_sections_and_our_logo(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach (['id="about"', 'id="commodities"', 'id="capabilities"', 'id="quality"', 'id="process"', 'id="buyers"', 'id="suppliers"', 'id="vision"', 'id="contact"', 'data-hero'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringContainsString('Delivering Excellence.', $html);
        $this->assertSame(6, substr_count($html, 'class="commodity-card'), 'six featured commodities');
        $this->assertStringContainsString(asset('image/logo-mark.png'), $html);
        $this->assertStringContainsString('<script defer src="'.asset('site/js/hero-carousel.js').'">', $html);
        $this->assertDoesNotMatchRegularExpression('#<script(?![^>]*(src=|type="application/ld\+json"))[^>]*>#i', $html, 'no inline script');
    }

    public function test_claims_the_company_has_not_confirmed_are_not_made(): void
    {
        $text = strip_tags($this->get(route('home'))->getContent());

        foreach (['Nigerian-owned', 'Africa’s most trusted', 'Export facilitation', 'at home and abroad', 'investment company', 'export-ready'] as $claim) {
            $this->assertStringNotContainsString($claim, $text, $claim);
        }
        $this->assertStringContainsString('international markets as a future opportunity', $text);
        $this->assertStringContainsString('Buyer support', $text);
    }

    public function test_contact_details_come_from_settings_and_the_address_only_once_entered(): void
    {
        $page = $this->get(route('home'))->assertOk();
        $page->assertSee('info@afisab.com')->assertSee('+234 805 752 0669')->assertDontSee('+234 816 455 2029')->assertDontSee('Commerce Street');
        $this->assertStringContainsString('<span>Nigeria</span>', $page->getContent());

        CompanyDetail::create(['address' => "12 Example Road\nIbadan", 'phone_secondary' => '+2348164552029']);

        $page = $this->get(route('home'));
        $page->assertSee('+234 816 455 2029')->assertSee('12 Example Road');
        $this->assertStringContainsString('href="tel:+2348164552029"', $page->getContent());
    }

    public function test_the_old_addresses_lead_to_the_new_pages(): void
    {
        $this->get('/about')->assertRedirect(route('home').'#about')->assertStatus(301);
        $this->get('/how-we-work')->assertRedirect(route('home').'#process')->assertStatus(301);
        $this->get('/what-we-trade')->assertRedirect(route('public.trade'))->assertStatus(301);
    }

    // ---- the catalogue and commodity pages ----

    public function test_the_catalogue_lists_every_active_commodity_with_its_filter_attributes(): void
    {
        $html = $this->get(route('public.trade'))->assertOk()->getContent();

        $this->assertSame(20, substr_count($html, 'data-commodity data-name='));
        $this->assertStringContainsString('20 commodities', $html);
        $this->assertStringContainsString('data-category="oilseeds"', $html);
        $this->assertStringContainsString('<script defer src="'.asset('site/js/catalogue.js').'">', $html);

        Commodity::where('name', 'Castor Seeds')->update(['is_active' => false]);
        SiteCommodities::forget();
        $this->get(route('public.trade'))->assertDontSee('Castor Seeds')->assertSee('19 commodities');
    }

    public function test_every_commodity_has_its_own_page_that_leads_to_the_quote_form(): void
    {
        foreach (config('site_commodities.items') as $slug => $c) {
            $page = $this->get(route('public.commodity', $slug))->assertOk()->assertSee($c['name'])->assertSee('Illustrative commodity image')->assertSee('Quality specifications');
            $page->assertSee(route('public.buyers', ['commodity' => $c['db_name']]).'#enquiry', false);
            $this->assertStringContainsString('wa.me/2348057520669', $page->getContent());
        }
        $this->get(route('public.commodity', 'maize'))->assertSee('Flour and grits milling')->assertSee('View maize kernel close-up')->assertSee('Soybeans');
    }

    public function test_an_unknown_or_switched_off_commodity_is_a_404_and_related_links_skip_it(): void
    {
        $this->get('/commodities/not-a-crop')->assertNotFound();

        Commodity::where('name', 'Soybeans')->update(['is_active' => false]);
        SiteCommodities::forget();
        $this->get(route('public.commodity', 'soybeans'))->assertNotFound();
        $page = $this->get(route('public.commodity', 'maize'))->assertOk();
        $this->assertStringNotContainsString(route('public.commodity', 'soybeans'), $page->getContent());
        $this->get(route('sitemap'))->assertDontSee(route('public.commodity', 'soybeans'));
    }

    public function test_the_sitemap_lists_the_commodity_pages(): void
    {
        $this->get(route('sitemap'))->assertOk()->assertSee(route('public.commodity', 'maize'))->assertSee(route('public.trade'))->assertDontSee('/about<')->assertDontSee('/how-we-work');
    }

    // ---- the enquiry forms ----

    public function test_the_buyer_form_collects_the_designers_fields_and_stores_them(): void
    {
        Notification::fake();

        $this->post(route('public.enquiries.store'), $this->quote([
            'delivery_date' => '2026-12-15', 'preferred_grade' => 'Grade 1', 'packaging' => '50 kg bags', 'quality_requirements' => 'Moisture below 13%', 'notes' => 'Weekly deliveries.',
            'items' => [['commodity' => 'Maize', 'quantity' => '50', 'unit' => 'MT'], ['commodity' => 'Soybeans', 'quantity' => '12.5', 'unit' => 'bags']],
        ]))->assertSessionHasNoErrors();

        $e = Enquiry::with('items')->sole();
        $this->assertSame(['Ngozi Okafor', 'Okafor Feeds Ltd', 'Nigeria', 'feed_manufacturer', 'Kano', 'Grade 1', '50 kg bags', 'Moisture below 13%', 'Weekly deliveries.'], [$e->name, $e->company, $e->country, $e->buyer_type, $e->destination, $e->preferred_grade, $e->packaging, $e->quality_requirements, $e->message]);
        $this->assertSame('2026-12-15', $e->delivery_date->toDateString());
        $this->assertSame([['Maize', '50 MT'], ['Soybeans', '12.5 bags']], $e->items->map(fn ($i) => [$i->commodity, $i->quantity])->all());

        Notification::assertSentOnDemand(EnquiryReceived::class, function ($n, $c, $notifiable) {
            $body = implode(' | ', array_filter($n->toMail($notifiable)->introLines, 'is_string'));

            return str_contains($body, 'Country: Nigeria') && str_contains($body, 'Buyer type: Feed manufacturer') && str_contains($body, 'Delivery destination: Kano')
                && str_contains($body, 'Expected delivery: 15 Dec 2026') && str_contains($body, 'Commodity: Maize (50 MT)');
        });
    }

    public function test_the_buyer_form_refuses_what_is_missing_and_says_so_next_to_the_field(): void
    {
        foreach ([['country' => ''], ['buyer_type' => ''], ['buyer_type' => 'wizard'], ['destination' => ''], ['company' => ''], ['delivery_date' => '2026-11-01']] as $bad) {
            $this->post(route('public.enquiries.store'), $this->quote($bad))->assertSessionHasErrors(array_key_first($bad));
        }
        $this->post(route('public.enquiries.store'), $this->quote(['items' => [['commodity' => 'Maize', 'quantity' => '', 'unit' => '']]]))->assertSessionHasErrors(['items.0.quantity' => 'Please enter the quantity for your first commodity.']);
        $this->assertSame(0, Enquiry::count());

        $page = $this->from(route('public.buyers'))->followingRedirects()->post(route('public.enquiries.store'), $this->quote(['country' => '', 'full_name' => 'Keep Me']));
        $page->assertSee('Please tell us your country.')->assertSee('value="Keep Me"', false)->assertSee('Please check the following');
    }

    public function test_the_supplier_form_collects_readiness_and_availability(): void
    {
        $body = ['type' => 'supplier', 'full_name' => 'Musa Bello', 'phone' => '0803 123 4567', 'location' => 'Makurdi, Benue', 'readiness' => 'upcoming', 'available_from' => '2027-01-10',
            'items' => [['commodity' => 'Sesame Seeds', 'quantity' => '20', 'unit' => 'MT']], 'consent' => '1', 'started' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp), 'website' => ''];

        $this->post(route('public.enquiries.store'), $body)->assertSessionHasNoErrors();
        $e = Enquiry::sole();
        $this->assertSame(['upcoming', '2027-01-10', 'Makurdi, Benue'], [$e->readiness, $e->available_from->toDateString(), $e->location]);

        $this->post(route('public.enquiries.store'), ['readiness' => ''] + $body)->assertSessionHasErrors('readiness');
        $this->post(route('public.enquiries.store'), ['readiness' => 'someday'] + $body)->assertSessionHasErrors('readiness');
    }

    public function test_the_form_pages_render_the_designers_markup_with_our_protections(): void
    {
        foreach (['public.buyers' => 'Buyer information', 'public.suppliers' => 'Supplier information', 'public.contact' => 'Your details'] as $route => $legend) {
            $html = $this->get(route($route))->assertOk()->assertSee($legend)->assertSee('How it works')->getContent();
            $this->assertStringContainsString('name="started"', $html);
            $this->assertStringContainsString('class="enquiry-trap"', $html);
            $this->assertStringContainsString('name="_token"', $html);
            $this->assertStringContainsString('name="consent"', $html);
        }
        $buyer = $this->get(route('public.buyers', ['commodity' => 'Maize']))->getContent();
        foreach (['Country', 'Buyer type', 'Delivery destination', 'Expected delivery date', 'Preferred grade', 'Packaging preference', 'Quality requirements', 'Add another commodity'] as $label) {
            $this->assertStringContainsString($label, $buyer);
        }
        $this->assertStringContainsString('<option value="Maize" selected>', $buyer);
        $supplier = $this->get(route('public.suppliers'))->getContent();
        foreach (['Harvest or readiness status', 'Available from', 'State or location'] as $label) {
            $this->assertStringContainsString($label, $supplier);
        }
    }

    public function test_staff_see_the_new_details_on_the_enquiry_page(): void
    {
        $this->post(route('public.enquiries.store'), $this->quote(['preferred_grade' => 'Grade 1', 'delivery_date' => '2026-12-15']));
        $director = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($director)->get(route('admin.enquiries.show', Enquiry::sole()))->assertOk()
            ->assertSee('Buyer type')->assertSee('Feed manufacturer')->assertSee('Delivery destination')->assertSee('Kano')->assertSee('Grade 1')->assertSee('15 Dec 2026')->assertSee('50 MT');
    }

    public function test_the_legal_pages_use_the_new_design_and_the_investor_page_stays_off(): void
    {
        foreach (['public.privacy' => 'Our reply', 'public.terms' => 'Terms of use', 'public.risk' => 'Risk notice'] as $route => $text) {
            $this->get(route($route))->assertOk()->assertSee($text)->assertSee('class="site-notice wrap"', false);
        }
        $this->get(route('public.investors'))->assertNotFound();
        config(['afisab.public_investor_page' => true]);
        $this->get(route('home'))->assertSee('href="'.route('public.investors').'"', false);
        $this->get(route('public.investors'))->assertOk()->assertSee('Become an investor')->assertSee('not guaranteed');
    }
}
