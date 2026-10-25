<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProfitFrequency;
use App\Enums\PaymentStatus;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A bank-transfer payment record exists only once the investor submits a receipt. */
class TransferSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(DatabaseSeeder::class);
    }

    private function pending(): Investment
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '08031234567', 'kyc_status' => 'approved', 'address' => 'Abuja', 'date_of_birth' => '1980-01-01']);

        return app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '6-month')->first(), 1_000_000_00, ProfitFrequency::Monthly, ['ip' => '1.1.1.1']);
    }

    private function upload(Investment $i, $file = null)
    {
        return $this->actingAs($i->user)->post(route('investor.payments.transfer', $i), ['proof' => $file ?? UploadedFile::fake()->image('receipt.png')]);
    }

    public function test_viewing_the_bank_details_creates_no_payment_record(): void
    {
        $i = $this->pending();

        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertOk()->assertSee('upload your transfer receipt');
        $this->assertSame(0, Payment::count());
    }

    public function test_submitting_without_a_receipt_creates_nothing(): void
    {
        $i = $this->pending();

        $this->actingAs($i->user)->post(route('investor.payments.transfer', $i))->assertSessionHasErrors('proof');
        $this->upload($i, UploadedFile::fake()->create('evil.php', 10, 'application/x-php'))->assertSessionHasErrors('proof');
        $this->upload($i, UploadedFile::fake()->create('big.pdf', 5000, 'application/pdf'))->assertSessionHasErrors('proof');

        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::allFiles('payment-proofs'));
    }

    public function test_submitting_a_receipt_creates_one_awaiting_payment_with_the_proof(): void
    {
        $i = $this->pending();

        $this->upload($i)->assertSessionHasNoErrors()->assertRedirect(route('investor.investments.show', $i));

        $payment = Payment::sole();
        $this->assertSame([PaymentMethod::Transfer, PaymentStatus::AwaitingConfirmation, 1_000_000_00], [$payment->method, $payment->status, $payment->amount]);
        Storage::assertExists($payment->proof_path);
        $this->assertStringStartsWith('PAY-', $payment->reference);
        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertSee('awaiting confirmation by Accounts')->assertSee('Replace receipt');
    }

    public function test_uploading_again_replaces_the_receipt_without_a_second_payment_or_a_stray_file(): void
    {
        $i = $this->pending();
        $this->upload($i);
        $first = Payment::sole()->proof_path;

        $this->upload($i)->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertNotSame($first, $payment->proof_path);
        Storage::assertMissing($first);
        Storage::assertExists($payment->proof_path);
        $this->assertCount(1, Storage::allFiles('payment-proofs'));
    }

    public function test_a_payment_left_pending_by_the_old_two_step_flow_is_reused(): void
    {
        $i = $this->pending();
        $legacy = app(PaymentService::class)->startTransfer($i);

        $this->upload($i);

        $this->assertSame(1, Payment::count());
        $this->assertSame(PaymentStatus::AwaitingConfirmation, $legacy->fresh()->status);
    }

    public function test_staff_only_see_transfers_that_have_a_receipt_and_the_investor_does_not_see_empty_ones(): void
    {
        $i = $this->pending();
        $legacy = app(PaymentService::class)->startTransfer($i); // an old empty "pending" record
        $accounts = User::factory()->create()->assignRole('accounts');

        $this->actingAs($accounts)->get(route('admin.payments'))->assertOk()->assertDontSee($legacy->reference)->assertSee('Nothing awaiting confirmation');
        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertOk()->assertDontSee($legacy->reference);

        $this->upload($i);
        $this->actingAs($accounts)->get(route('admin.payments'))->assertSee($legacy->reference)->assertSee('Confirm received');
    }

    public function test_it_cannot_be_used_on_someone_elses_or_an_already_paid_investment(): void
    {
        $i = $this->pending();
        $stranger = User::factory()->create()->assignRole('investor');

        $this->actingAs($stranger)->post(route('investor.payments.transfer', $i), ['proof' => UploadedFile::fake()->image('x.png')])->assertNotFound();
        $this->assertSame(0, Payment::count());

        $i->update(['status' => InvestmentStatus::Active]);
        $this->upload($i)->assertSessionHasErrors('proof');
        $this->assertSame(0, Payment::count());
    }
}
