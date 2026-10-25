<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\InvestorProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NinAndPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function investor(string $kyc = 'approved', array $profile = []): User
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create($profile + ['phone' => '08031234567', 'kyc_status' => $kyc, 'address' => 'Abuja', 'date_of_birth' => '1980-01-01']);

        return $user;
    }

    private function kycForm(array $override = []): array
    {
        return $override + ['phone' => '08031234567', 'date_of_birth' => '1990-01-01', 'address' => '1 Test Street', 'nin' => '12345678901', 'bank_name' => 'Test Bank', 'account_number' => '0123456789', 'account_name' => 'N', 'id_document' => UploadedFile::fake()->image('id.jpg')];
    }

    // ---- NIN ----

    public function test_the_bvn_is_gone_and_the_nin_is_required_and_eleven_digits(): void
    {
        $this->assertFalse(Schema::hasColumn('investor_profiles', 'bvn'));
        $user = $this->investor('incomplete');

        $this->actingAs($user)->get(route('investor.profile'))->assertOk()->assertSee('National Identification Number')->assertDontSee('BVN');
        foreach ([null, '1234567890', '123456789012', 'abcdefghijk'] as $bad) {
            $this->actingAs($user)->put(route('investor.profile.update'), $this->kycForm(['nin' => $bad]))->assertSessionHasErrors('nin');
        }
        $this->actingAs($user)->put(route('investor.profile.update'), $this->kycForm())->assertSessionHasNoErrors();
        $this->assertSame(KycStatus::Submitted, $user->investorProfile->fresh()->kyc_status);
    }

    public function test_the_nin_is_stored_encrypted_hidden_and_masked_for_most_staff(): void
    {
        $user = $this->investor('submitted');
        $this->actingAs($user)->put(route('investor.profile.update'), $this->kycForm());

        $profile = $user->investorProfile->fresh();
        $this->assertSame('12345678901', $profile->nin);
        $this->assertNotSame('12345678901', $profile->getRawOriginal('nin'));
        $this->assertStringNotContainsString('12345678901', $profile->toJson());
        $this->assertSame(InvestorProfile::hashNin('12345678901'), $profile->nin_hash);
        $this->assertSame('•••••••8901', $profile->maskedNin());

        $accounts = User::factory()->create()->assignRole('accounts');
        $compliance = User::factory()->create()->assignRole('compliance');
        $this->actingAs($accounts)->get(route('admin.investors.show', $user))->assertSee('•••••••8901')->assertDontSee('12345678901');
        $this->actingAs($compliance)->get(route('admin.investors.show', $user))->assertSee('12345678901');
        $this->actingAs($compliance)->get(route('admin.kyc'))->assertSee('NIN 12345678901');
    }

    public function test_one_nin_cannot_be_registered_on_two_accounts_without_revealing_whose(): void
    {
        $first = $this->investor('incomplete');
        $this->actingAs($first)->put(route('investor.profile.update'), $this->kycForm())->assertSessionHasNoErrors();

        $second = $this->investor('incomplete');
        $this->actingAs($second)->put(route('investor.profile.update'), $this->kycForm())->assertSessionHasErrors(['nin' => 'This NIN cannot be used. Please check it, or contact support.']);
        $this->assertNull($second->investorProfile->fresh()->nin);

        // the same person correcting their own submission is fine
        $this->actingAs($first)->put(route('investor.profile.update'), $this->kycForm())->assertSessionHasNoErrors();
    }

    public function test_an_investor_verified_earlier_must_add_a_nin_before_investing_but_keeps_their_policies(): void
    {
        $user = $this->investor('approved');
        $plan = InvestmentPlan::where('slug', '6-month')->first();
        $body = ['investment_plan_id' => $plan->id, 'amount' => '500,000', 'profit_frequency' => 'monthly', 'accept_terms' => '1'];

        $this->actingAs($user)->get(route('investor.dashboard'))->assertSee('add your NIN');
        $this->actingAs($user)->get(route('investor.plans'))->assertSee('add your NIN');
        $this->actingAs($user)->post(route('investor.investments.store'), $body)->assertSessionHasErrors('amount');
        $this->assertSame(0, Investment::count());

        $this->actingAs($user)->put(route('investor.profile.update'), ['section' => 'nin', 'nin' => '12345'])->assertSessionHasErrors('nin');
        $this->actingAs($user)->put(route('investor.profile.update'), ['section' => 'nin', 'nin' => '98765432101'])->assertSessionHasNoErrors();
        $this->assertSame('98765432101', $user->investorProfile->fresh()->nin);
        $this->assertDatabaseHas('activity_log', ['description' => 'NIN added', 'causer_id' => $user->id]);

        $this->actingAs($user)->get(route('investor.dashboard'))->assertDontSee('add your NIN');
        $this->actingAs($user)->post(route('investor.investments.store'), $body)->assertSessionHasNoErrors();
        $this->assertSame(1, Investment::count());
    }

    public function test_a_nin_on_record_can_only_be_changed_by_support(): void
    {
        $user = $this->investor('approved', ['nin' => '11111111111']);

        $this->actingAs($user)->put(route('investor.profile.update'), ['section' => 'nin', 'nin' => '22222222222'])->assertSessionHasErrors('nin');
        $this->assertSame('11111111111', $user->investorProfile->fresh()->nin);
    }

    // ---- profile picture ----

    private function photo(int $w = 1200, int $h = 800, string $name = 'me.jpg')
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    public function test_an_investor_uploads_a_picture_that_is_stored_privately_as_a_square_jpeg(): void
    {
        $user = $this->investor();

        $this->actingAs($user)->post(route('investor.profile.photo.update'), ['photo' => $this->photo()])->assertSessionHasNoErrors();

        $path = $user->investorProfile->fresh()->photo_path;
        $this->assertStringStartsWith('profile-photos/', $path);
        $size = getimagesizefromstring(Storage::get($path));
        $this->assertSame([400, 400, IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]]);
        $this->assertDatabaseHas('activity_log', ['description' => 'Profile picture updated', 'causer_id' => $user->id]);
        $this->actingAs($user)->get(route('investor.profile'))->assertSee('Update picture')->assertSee(route('investor.profile.photo'), false);
        $this->actingAs($user)->get(route('investor.dashboard'))->assertSee(route('investor.profile.photo'), false); // the navbar avatar
    }

    public function test_replacing_a_picture_deletes_the_old_file_and_removing_clears_it(): void
    {
        $user = $this->investor();
        $this->actingAs($user)->post(route('investor.profile.photo.update'), ['photo' => $this->photo()]);
        $first = $user->investorProfile->fresh()->photo_path;

        $this->actingAs($user)->post(route('investor.profile.photo.update'), ['photo' => $this->photo(600, 600, 'b.png')]);
        $second = $user->investorProfile->fresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);

        $this->actingAs($user)->delete(route('investor.profile.photo.destroy'))->assertSessionHasNoErrors();
        $this->assertNull($user->investorProfile->fresh()->photo_path);
        Storage::assertMissing($second);
        $this->assertSame([], Storage::allFiles('profile-photos'));
        $this->actingAs($user)->get(route('investor.profile.photo'))->assertNotFound();
    }

    public function test_bad_pictures_are_refused_and_nothing_is_stored(): void
    {
        $user = $this->investor();

        $bad = [
            UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('huge.jpg', 1000, 1000)->size(5000),
            UploadedFile::fake()->image('tiny.png', 40, 40),
        ];
        foreach ($bad as $file) {
            $this->actingAs($user)->post(route('investor.profile.photo.update'), ['photo' => $file])->assertSessionHasErrors('photo');
        }
        $this->actingAs($user)->post(route('investor.profile.photo.update'))->assertSessionHasErrors('photo');

        $this->assertNull($user->investorProfile->fresh()->photo_path);
        $this->assertSame([], Storage::allFiles('profile-photos'));
    }

    public function test_pictures_are_never_public_and_only_the_owner_and_authorised_staff_can_see_them(): void
    {
        $owner = $this->investor();
        $other = $this->investor();
        $this->actingAs($owner)->post(route('investor.profile.photo.update'), ['photo' => $this->photo()]);
        $profile = $owner->investorProfile->fresh();

        $this->actingAs($owner)->get(route('investor.profile.photo'))->assertOk()->assertHeader('content-type', 'image/jpeg')->assertHeader('cache-control', 'no-store, private');
        $this->actingAs($other)->get(route('investor.profile.photo'))->assertNotFound(); // their own: none

        $accounts = User::factory()->create()->assignRole('accounts');
        $hr = User::factory()->create()->assignRole('hr');
        $compliance = User::factory()->create()->assignRole('compliance');
        $this->actingAs($accounts)->get(route('admin.investors.photo', $owner))->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->actingAs($hr)->get(route('admin.investors.photo', $owner))->assertForbidden();
        $this->actingAs($accounts)->get(route('admin.kyc.photo', $profile))->assertForbidden();
        $this->actingAs($compliance)->get(route('admin.kyc.photo', $profile))->assertOk();
        $this->actingAs($other)->get(route('admin.investors.photo', $owner))->assertForbidden();

        auth()->guard()->forgetUser();
        $this->app['auth']->forgetGuards();
        $this->get(route('investor.profile.photo'))->assertRedirect(route('login'));
        $this->get(route('admin.investors.photo', $owner))->assertRedirect(route('login'));
    }

    public function test_the_picture_is_optional_for_kyc_and_staff_see_it_on_the_review_and_detail_pages(): void
    {
        $user = $this->investor('incomplete');
        $this->actingAs($user)->put(route('investor.profile.update'), $this->kycForm())->assertSessionHasNoErrors(); // no picture needed
        $this->assertSame(KycStatus::Submitted, $user->investorProfile->fresh()->kyc_status);

        $compliance = User::factory()->create()->assignRole('compliance');
        $this->actingAs($compliance)->get(route('admin.kyc'))->assertDontSee('Profile picture of');

        $this->actingAs($user)->post(route('investor.profile.photo.update'), ['photo' => $this->photo()]);
        $this->actingAs($compliance)->get(route('admin.kyc'))->assertSee('Profile picture of');
        $this->actingAs($compliance)->get(route('admin.investors.show', $user))->assertSee('Profile picture of');
    }
}
