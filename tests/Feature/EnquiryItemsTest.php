<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\EnquiryItem;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\EnquiryReceived;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** An enquiry can be about several commodities, each with its own quantity. */
class EnquiryItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function body(string $type, array $items, array $over = []): array
    {
        return $over + ['type' => $type, 'name' => 'Ngozi Okafor', 'company' => 'Okafor Feeds Ltd', 'email' => 'ngozi@okaforfeeds.example', 'phone' => '0803 123 4567',
            'location' => 'Kano', 'message' => '', 'consent' => '1', 'started' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp), 'website' => '', 'items' => $items];
    }

    private function send(string $type, array $items, array $over = [])
    {
        return $this->post(route('public.enquiries.store'), $this->body($type, $items, $over));
    }

    public function test_several_commodities_with_their_own_quantities_are_saved_in_order(): void
    {
        $this->send('quote', [
            ['commodity' => 'Maize', 'quantity' => '50 tonnes'],
            ['commodity' => 'Sorghum', 'quantity' => '20 tonnes'],
            ['commodity' => '', 'quantity' => ''], // an untouched spare row
            ['commodity' => 'Soya Beans', 'quantity' => ''],
        ])->assertSessionHasNoErrors();

        $e = Enquiry::with('items')->sole();
        $this->assertSame([['Maize', '50 tonnes', 1], ['Sorghum', '20 tonnes', 2], ['Soya Beans', null, 3]], $e->items->map(fn ($i) => [$i->commodity, $i->quantity, $i->position])->all());
        $this->assertSame(['Maize', '50 tonnes'], [$e->commodity, $e->quantity], 'the first item is also kept as the summary');
        $this->assertSame('Maize, Sorghum +1', $e->commoditySummary());
    }

    public function test_the_supplier_form_takes_several_too(): void
    {
        $this->send('supplier', [['commodity' => 'Sesame Seeds', 'quantity' => '20 tonnes'], ['commodity' => 'Cashew Nuts', 'quantity' => '5 tonnes']])->assertSessionHasNoErrors();

        $this->assertSame(2, EnquiryItem::count());
    }

    public function test_the_first_commodity_is_required_and_blank_rows_do_not_count(): void
    {
        $this->send('quote', [['commodity' => '', 'quantity' => ''], ['commodity' => '', 'quantity' => '']])->assertSessionHasErrors('items');
        $this->send('quote', [['commodity' => '', 'quantity' => '50 tonnes']])->assertSessionHasErrors('items.0.commodity');
        $this->send('supplier', [])->assertSessionHasErrors('items');
        $this->assertSame(0, Enquiry::count());
    }

    public function test_other_needs_a_name_and_the_name_is_what_is_stored(): void
    {
        $this->send('quote', [['commodity' => 'Other', 'other' => '', 'quantity' => '5 tonnes']])->assertSessionHasErrors('items.0.commodity');
        $this->assertSame(0, Enquiry::count());

        $this->send('quote', [['commodity' => 'Maize', 'quantity' => ''], ['commodity' => 'Other', 'other' => 'Teff', 'quantity' => '3 tonnes']])->assertSessionHasNoErrors();
        $this->assertSame(['Maize', 'Teff'], Enquiry::sole()->items->pluck('commodity')->all());
    }

    public function test_at_most_five_and_repeats_are_collapsed(): void
    {
        $six = collect(['Maize', 'Sorghum', 'Millet', 'Soya Beans', 'Sesame Seeds', 'Cashew Nuts'])->map(fn ($c) => ['commodity' => $c, 'quantity' => ''])->all();
        $this->send('quote', $six)->assertSessionHasErrors('items');
        $this->assertSame(0, Enquiry::count());

        $this->send('quote', [['commodity' => 'Maize', 'quantity' => '10 tonnes'], ['commodity' => 'maize', 'quantity' => '99 tonnes'], ['commodity' => 'Sorghum', 'quantity' => '']])->assertSessionHasNoErrors();
        $this->assertSame([['Maize', '10 tonnes'], ['Sorghum', null]], Enquiry::sole()->items->map(fn ($i) => [$i->commodity, $i->quantity])->all());
    }

    public function test_a_single_commodity_sent_the_old_way_still_works_and_the_general_form_has_none(): void
    {
        $this->post(route('public.enquiries.store'), ['type' => 'quote', 'name' => 'A', 'email' => 'a@example.com', 'phone' => '0803 123 4567', 'commodity' => 'Maize', 'quantity' => '5 tonnes',
            'consent' => '1', 'started' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp), 'website' => ''])->assertSessionHasNoErrors();
        $this->assertSame([['Maize', '5 tonnes']], Enquiry::sole()->items->map(fn ($i) => [$i->commodity, $i->quantity])->all());

        $this->post(route('public.enquiries.store'), $this->body('general', [], ['message' => 'Hello there']))->assertSessionHasNoErrors();
        $this->assertSame(0, Enquiry::where('type', 'general')->first()->items()->count());
    }

    public function test_the_email_names_every_commodity_in_the_subject_and_body(): void
    {
        Notification::fake();

        $this->send('quote', [['commodity' => 'Maize', 'quantity' => '50 tonnes'], ['commodity' => 'Sorghum', 'quantity' => '20 tonnes'], ['commodity' => 'Millet', 'quantity' => ''], ['commodity' => 'Soya Beans', 'quantity' => '']]);

        Notification::assertSentOnDemand(EnquiryReceived::class, function ($n, $c, $notifiable) {
            $mail = $n->toMail($notifiable);
            $body = implode(' | ', array_filter($mail->introLines, 'is_string'));

            return str_contains($mail->subject, 'Maize, Sorghum, Millet +1')
                && str_contains($body, 'Commodity: Maize (50 tonnes)') && str_contains($body, 'Commodity: Sorghum (20 tonnes)') && str_contains($body, 'Commodity: Soya Beans');
        });
    }

    public function test_staff_see_all_commodities_search_finds_any_and_the_supplier_note_lists_them(): void
    {
        $this->send('supplier', [['commodity' => 'Sesame Seeds', 'quantity' => '20 tonnes'], ['commodity' => 'Cashew Nuts', 'quantity' => '5 tonnes']]);
        $this->send('quote', [['commodity' => 'Maize', 'quantity' => '']], ['name' => 'Someone Else', 'email' => 'else@example.com']);
        $director = User::factory()->create()->assignRole('super-admin');
        $supplier = Enquiry::where('type', 'supplier')->first();

        $this->actingAs($director)->get(route('admin.enquiries'))->assertOk()->assertSee('Sesame Seeds, Cashew Nuts');
        $this->actingAs($director)->get(route('admin.enquiries', ['q' => 'Cashew']))->assertOk()->assertSee('Ngozi Okafor')->assertDontSee('Someone Else');
        $this->actingAs($director)->get(route('admin.enquiries.show', $supplier))->assertOk()->assertSee('Commodities')->assertSee('Sesame Seeds')->assertSee('20 tonnes')->assertSee('Cashew Nuts')->assertSee('5 tonnes');

        $this->actingAs($director)->post(route('admin.enquiries.supplier', $supplier))->assertSessionHasNoErrors();
        $this->assertStringContainsString('Offers: Sesame Seeds (20 tonnes); Cashew Nuts (5 tonnes).', Supplier::sole()->notes);
    }

    public function test_enquiries_from_before_this_change_are_carried_over_and_still_display(): void
    {
        $old = Enquiry::create(['type' => 'quote', 'name' => 'Old Buyer', 'email' => 'old@example.com', 'phone' => '0803 000 0000', 'commodity' => 'Rice', 'quantity' => '10 tonnes', 'consented_at' => now(), 'status' => 'new']);
        $this->assertSame(0, $old->items()->count());
        $director = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($director)->get(route('admin.enquiries.show', $old))->assertOk()->assertSee('Rice')->assertSee('10 tonnes');
        $this->actingAs($director)->get(route('admin.enquiries'))->assertSee('Rice');

        // the migration step: every enquiry with a commodity becomes a one-item enquiry
        \Illuminate\Support\Facades\Schema::drop('enquiry_items');
        (require database_path('migrations/2026_11_07_100000_create_enquiry_items.php'))->up();
        $this->assertSame([['Rice', '10 tonnes', 1]], $old->fresh()->items->map(fn ($i) => [$i->commodity, $i->quantity, $i->position])->all());
    }

    public function test_the_form_shows_five_rows_with_the_later_ones_tucked_away_and_a_prefill_from_the_link(): void
    {
        $html = $this->get(route('public.buyers', ['commodity' => 'Maize']))->assertOk()->getContent();

        $this->assertSame(5, substr_count($html, 'data-commodity-select'));
        $this->assertSame(2, substr_count($html, 'data-extra-row'), 'rows 4 and 5 are revealed by the add button');
        $this->assertMatchesRegularExpression('#<select name="items\[0\]\[commodity\]" required#', $html);
        $this->assertStringNotContainsString('name="items[1][commodity]" required', $html);
        $this->assertStringContainsString('<option value="Maize" selected>', $html);
        $this->assertStringContainsString('data-add-row', $html);
    }
}
