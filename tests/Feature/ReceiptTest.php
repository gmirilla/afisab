<?php

namespace Tests\Feature;

use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\LedgerException;
use App\Models\CompanyDetail;
use App\Models\CompanyStamp;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\Receipt;
use App\Models\Signatory;
use App\Models\User;
use App\Notifications\InvestmentDocumentsIssued;
use App\Notifications\ReceiptDelivered;
use App\Services\Payments\PaymentService;
use App\Services\Policy\ReceiptService;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private ReceiptService $receipts;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        config(['afisab.policy.code' => 'OYO']);
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        $this->receipts = app(ReceiptService::class);
    }

    private function investor(string $name = 'Ridwan Farayola', array $profile = []): User
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create($profile + ['phone' => '08031234567', 'kyc_status' => 'approved', 'address' => '40 Clinton Avenue, Abuja', 'date_of_birth' => '1977-10-10']);

        return $user;
    }

    private function policy(?User $user = null, string $slug = '12-month', int $amount = 12_500_000_00, ?Carbon $receivedOn = null): Investment
    {
        $user ??= $this->investor();
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', $slug)->first(), $amount, ProfitFrequency::Quarterly, ['ip' => '102.89.1.1']);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment), null, null, null, $receivedOn);

        return $investment->fresh();
    }

    private function png(string $dir): string
    {
        $path = $dir.'/'.uniqid().'.png';
        $im = imagecreatetruecolor(200, 200);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        ob_start();
        imagepng($im);
        Storage::put($path, ob_get_clean());

        return $path;
    }

    private function receiptFor(Investment $i): Receipt
    {
        return $this->receipts->issueForInvestment($i);
    }

    public function test_receipt_numbers_start_at_0001_and_run_in_four_digits_per_year(): void
    {
        $first = $this->receiptFor($this->policy());
        $second = $this->receiptFor($this->policy());

        $this->assertSame('AFISAB/INV/2026/0001', $first->receipt_number);
        $this->assertSame('AFISAB/INV/2026/0002', $second->receipt_number);

        $this->travelTo(Carbon::parse('2027-01-05 09:00'));
        $this->assertSame('AFISAB/INV/2027/0001', $this->receiptFor($this->policy())->receipt_number, 'each year restarts at 0001');
    }

    public function test_a_receipt_is_a_stored_pdf_with_a_fingerprint_and_issuing_twice_returns_the_same_one(): void
    {
        $i = $this->policy();
        $receipt = $this->receiptFor($i);

        Storage::assertExists($receipt->file_path);
        $bytes = Storage::get($receipt->file_path);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $receipt->sha256);
        $this->assertSame(12_500_000_00, $receipt->amount);
        $this->assertSame(1, $receipt->version);
        $this->assertSame($receipt->id, $this->receiptFor($i)->id);
        $this->assertSame(1, Receipt::count());
        $this->assertSame('AFISAB-Receipt-AFISAB-INV-2026-0001.pdf', $receipt->filename());
        $this->assertDatabaseHas('activity_log', ['description' => 'Receipt issued']);
    }

    public function test_the_receipt_says_what_the_sample_says(): void
    {
        $user = $this->investor('Ridwan Farayola', ['title' => 'Alh.']);
        $i = $this->policy($user, receivedOn: Carbon::parse('2026-10-18'));
        $payment = $i->payments()->first();
        $context = $this->receipts->context($payment, 'AFISAB/INV/2026/0001', Carbon::parse('2026-10-18'), null, null);

        $this->assertSame('Alh. Ridwan Farayola', $context['investor_name']);
        $this->assertSame('One (1) Year', $context['period']);
        $this->assertSame('20th of October, 2026', $context['start'], 'the investment starts on the day it was confirmed');
        $this->assertSame('20th of October, 2027', $context['maturity']);
        $this->assertSame('18th October, 2026', $context['received']);
        $this->assertSame('Quarterly', $context['frequency']);
        $this->assertSame('Twelve Million, Five Hundred Thousand Naira Only', $context['amount_words']);
        $this->assertStringContainsString($payment->reference, $context['payment_reference']);
        $this->assertSame('One (1) Year', $this->receipts->context($payment, 'x', now(), null, null)['period']);

        $short = $this->policy(slug: '3-month');
        $this->assertSame('Three (3) Months', $this->receipts->context($short->payments()->first(), 'x', now(), null, null)['period']);
    }

    public function test_the_start_date_is_the_day_the_payment_is_confirmed_even_when_the_money_arrived_earlier(): void
    {
        $i = $this->policy(receivedOn: Carbon::parse('2026-10-15'));

        $this->assertSame('2026-10-20', $i->start_date->toDateString());
        $this->assertSame('2026-10-15', $i->payments()->first()->received_on->toDateString());
        $this->assertSame('2026-10-15', $this->receiptFor($i)->received_on->toDateString());
    }

    public function test_no_address_is_printed_until_the_super_admin_enters_one(): void
    {
        $i = $this->policy();
        $context = $this->receipts->context($i->payments()->first(), 'x', now(), null, null);
        $this->assertSame([], $context['details']['address']);
        $this->assertSame(['+234 805 752 0669'], $context['details']['phones']);

        CompanyDetail::create(['address' => "12 Example Road\nIbadan, Oyo State", 'phone_secondary' => '08030000000', 'investment_email' => 'invest@afisab.com']);
        $context = $this->receipts->context($i->payments()->first(), 'x', now(), null, null);
        $this->assertSame(['12 Example Road', 'Ibadan, Oyo State'], $context['details']['address']);
        $this->assertCount(2, $context['details']['phones']);
        $this->assertContains('invest@afisab.com', $context['details']['emails']);
    }

    public function test_the_receipt_records_the_signatory_and_stamp_it_was_issued_with(): void
    {
        $signatory = Signatory::create(['name' => 'Alhaji Musa Bello', 'title' => 'MD', 'signature_path' => $this->png('signatures'), 'is_active' => true]);
        $stamp = CompanyStamp::create(['image_path' => $this->png('stamps'), 'is_active' => true]);

        $receipt = $this->receiptFor($this->policy());

        $this->assertSame($signatory->id, $receipt->signatory_id);
        $this->assertSame($stamp->id, $receipt->stamp_id);
    }

    public function test_an_issued_receipt_cannot_be_changed_or_deleted_but_can_be_superseded_and_voided(): void
    {
        $i = $this->policy();
        $v1 = $this->receiptFor($i);

        foreach (['file_path' => 'other.pdf', 'sha256' => str_repeat('a', 64), 'receipt_number' => 'X', 'amount' => 1, 'version' => 9] as $field => $value) {
            try {
                $v1->update([$field => $value]);
                $this->fail("{$field} should be locked");
            } catch (LedgerException) {
                $this->assertTrue(true);
            }
            $v1->refresh();
        }
        try {
            $v1->delete();
            $this->fail('receipts are never deleted');
        } catch (LedgerException) {
        }

        $v2 = $this->receipts->issueForInvestment($i, null, 'address corrected', force: true);
        $this->assertSame([2, $v1->receipt_number], [$v2->version, $v2->receipt_number], 'a correction keeps the number');
        $this->assertSame('superseded', $v1->fresh()->status);
        $this->assertSame($v2->id, $i->currentReceipt()->id);

        $director = User::factory()->create()->assignRole('super-admin');
        $this->receipts->void($v2, $director, 'payment reversed');
        $this->assertNull($i->fresh()->currentReceipt());
        $this->assertSame('void', $v1->fresh()->status);
        $this->expectException(BusinessRuleException::class);
        $this->receipts->issueForInvestment($i, null, 'again', force: true);
    }

    public function test_confirming_a_payment_emails_one_message_with_both_documents(): void
    {
        config(['afisab.policy.auto_issue' => true]);
        Notification::fake();

        $i = $this->policy();

        $this->assertNotNull($i->currentReceipt());
        $this->assertNotNull($i->currentPolicyDocument());
        Notification::assertSentToTimes($i->user, InvestmentDocumentsIssued::class, 1);
    }

    public function test_the_combined_email_carries_both_pdfs(): void
    {
        $i = $this->policy();
        $receipt = $this->receiptFor($i);
        $doc = app(\App\Services\Policy\PolicyDocumentService::class)->issue($i);

        $i->user->notify(new InvestmentDocumentsIssued($receipt, $doc));

        $mail = collect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage())->first(fn ($m) => str_contains((string) $m->getSubject(), 'receipt and agreement'));
        $this->assertNotNull($mail);
        $this->assertCount(2, $mail->getAttachments());
        $this->assertStringContainsString('AFISAB/INV/2026/0001', $mail->getHtmlBody());
    }

    public function test_an_investor_downloads_and_emails_their_own_receipt_only(): void
    {
        $i = $this->policy();
        $this->receiptFor($i);
        $other = $this->investor('Someone Else');
        Notification::fake();

        $this->actingAs($i->user)->get(route('investor.receipt.download', $i))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($other)->get(route('investor.receipt.download', $i))->assertNotFound();
        $this->actingAs($other)->post(route('investor.receipt.email', $i))->assertNotFound();

        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertOk()->assertSee('AFISAB/INV/2026/0001')->assertSee('Download receipt');

        foreach (range(1, 3) as $n) {
            $this->actingAs($i->user)->post(route('investor.receipt.email', $i))->assertSessionHasNoErrors();
        }
        $this->actingAs($i->user)->post(route('investor.receipt.email', $i))->assertSessionHasErrors('receipt');
        Notification::assertSentToTimes($i->user, ReceiptDelivered::class, 3);
    }

    public function test_staff_permissions_for_receipts(): void
    {
        $i = $this->policy();
        $receipt = $this->receiptFor($i);
        $accounts = User::factory()->create()->assignRole('accounts');
        $compliance = User::factory()->create()->assignRole('compliance');
        $hr = User::factory()->create()->assignRole('hr');
        Notification::fake();

        $this->actingAs($hr)->get(route('admin.receipts.download', $receipt))->assertForbidden();
        $this->actingAs($accounts)->get(route('admin.receipts.download', $receipt))->assertOk();
        $this->actingAs($accounts)->post(route('admin.receipts.send', $i))->assertSessionHasNoErrors();
        Notification::assertSentTo($i->user, ReceiptDelivered::class);

        $this->actingAs($accounts)->post(route('admin.receipts.reissue', $i), ['reason' => 'address corrected'])->assertForbidden();
        $this->actingAs($accounts)->post(route('admin.receipts.void', $receipt), ['reason' => 'payment reversed'])->assertForbidden();

        $this->actingAs($compliance)->post(route('admin.receipts.reissue', $i), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($compliance)->post(route('admin.receipts.reissue', $i), ['reason' => 'address corrected'])->assertSessionHasNoErrors();
        $this->assertSame(2, $i->currentReceipt()->version);
        $this->actingAs($compliance)->post(route('admin.receipts.void', $i->currentReceipt()), ['reason' => 'payment reversed'])->assertSessionHasNoErrors();
        $this->assertNull($i->fresh()->currentReceipt());

        $this->actingAs($accounts)->get(route('admin.investors.show', $i->user))->assertOk()->assertSee('Investment receipts and agreements');
    }

    public function test_the_confirm_form_takes_a_received_date_that_cannot_be_in_the_future(): void
    {
        $accounts = User::factory()->create()->assignRole('accounts');
        $user = $this->investor();
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), 5_000_000_00, ProfitFrequency::Monthly, ['ip' => '1.1.1.1']);
        $payment = app(PaymentService::class)->startTransfer($investment);
        $payment->update(['status' => 'awaiting_confirmation']);
        $this->travelTo(Carbon::parse('2026-10-22 10:00'));

        $this->actingAs($accounts)->post(route('admin.payments.confirm', $payment), ['received_on' => '2026-12-31'])->assertSessionHasErrors('received_on');
        $this->actingAs($accounts)->post(route('admin.payments.confirm', $payment), ['received_on' => '2026-10-01'])->assertSessionHasErrors('received_on'); // before the investor subscribed
        $this->actingAs($accounts)->post(route('admin.payments.confirm', $payment), ['received_on' => '2026-10-21'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-21', $payment->fresh()->received_on->toDateString());
        $this->assertSame('2026-10-22', $investment->fresh()->start_date->toDateString());
    }

    public function test_an_investor_can_set_their_title_and_it_is_used_on_the_agreement_and_receipt(): void
    {
        $user = $this->investor();
        $this->actingAs($user)->put(route('investor.profile.update'), ['section' => 'agreement', 'title' => 'Dr.'])->assertSessionHasNoErrors();
        $this->assertSame('Dr.', $user->investorProfile->fresh()->title);
        $this->actingAs($user)->put(route('investor.profile.update'), ['section' => 'agreement', 'title' => 'Emperor'])->assertSessionHasErrors('title');
    }

    // ---- the super-admin view for company details and the stamp ----

    public function test_only_the_super_admin_can_open_or_change_company_details_and_the_stamp(): void
    {
        $form = ['address' => "1 Main Road\nIbadan", 'password' => 'password'];

        foreach (['accounts', 'compliance', 'hr', 'sales'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->put(route('admin.signatory.details'), $form)->assertForbidden();
            $this->actingAs($user)->post(route('admin.signatory.stamp.store'), ['stamp' => UploadedFile::fake()->image('s.png', 300, 300), 'password' => 'password'])->assertForbidden();
        }
        $this->assertSame(0, CompanyDetail::count());
        $this->assertSame(0, CompanyStamp::count());
        auth()->guard()->forgetUser();
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.signatory'))->assertRedirect(route('login'));
    }

    public function test_the_super_admin_saves_company_details_with_their_password_and_it_is_audited(): void
    {
        $director = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($director)->put(route('admin.signatory.details'), ['address' => '1 Main Road', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($director)->put(route('admin.signatory.details'), ['investment_email' => 'not-an-email', 'password' => 'password'])->assertSessionHasErrors('investment_email');
        $this->actingAs($director)->put(route('admin.signatory.details'), ['phone_secondary' => 'call me', 'password' => 'password'])->assertSessionHasErrors('phone_secondary');
        $this->assertSame(0, CompanyDetail::count());

        $this->actingAs($director)->put(route('admin.signatory.details'), ['address' => "12 Example Road\nIbadan", 'phone_secondary' => '0803 000 0000', 'investment_email' => 'invest@afisab.com', 'website' => 'www.afisab.com', 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(1, CompanyDetail::count());
        $this->assertSame("12 Example Road\nIbadan", CompanyDetail::current()->address);

        $this->actingAs($director)->put(route('admin.signatory.details'), ['address' => '', 'password' => 'password']);
        $this->assertSame(1, CompanyDetail::count(), 'one row, edited in place');
        $this->assertNull(CompanyDetail::current()->address);
        $this->assertDatabaseHas('activity_log', ['description' => 'Company details updated', 'causer_id' => $director->id]);
        $this->actingAs($director)->get(route('admin.signatory'))->assertOk()->assertSee('Company address and contact lines');
    }

    public function test_the_stamp_is_validated_resized_kept_private_and_old_ones_are_retained(): void
    {
        $director = User::factory()->create()->assignRole('super-admin');
        $upload = fn ($file, $pw = 'password') => $this->actingAs($director)->post(route('admin.signatory.stamp.store'), ['stamp' => $file, 'password' => $pw]);

        $upload(UploadedFile::fake()->image('s.png', 300, 300), 'wrong')->assertSessionHasErrors('password');
        $upload(UploadedFile::fake()->create('evil.php', 10, 'application/x-php'))->assertSessionHasErrors('stamp');
        $upload(UploadedFile::fake()->image('tiny.png', 40, 40))->assertSessionHasErrors('stamp');
        $this->assertSame(0, CompanyStamp::count());

        $upload(UploadedFile::fake()->image('s.png', 2000, 2000))->assertSessionHasNoErrors();
        $first = CompanyStamp::active();
        $size = getimagesizefromstring(Storage::get($first->image_path));
        $this->assertSame(IMAGETYPE_PNG, $size[2]);
        $this->assertLessThanOrEqual(500, $size[0]);

        $upload(UploadedFile::fake()->image('t.png', 300, 300))->assertSessionHasNoErrors();
        $this->assertSame(2, CompanyStamp::count());
        $this->assertFalse($first->fresh()->is_active);
        Storage::assertExists($first->image_path);

        $this->actingAs($director)->post(route('admin.signatory.stamp.activate', $first), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($director)->post(route('admin.signatory.stamp.activate', $first), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame($first->id, CompanyStamp::active()->id);

        $this->app['auth']->forgetGuards();
        $this->get(route('admin.signatory.stamp.image', $first))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create()->assignRole('compliance'))->get(route('admin.signatory.stamp.image', $first))->assertForbidden();
        $this->actingAs($director)->get(route('admin.signatory.stamp.image', $first))->assertOk()->assertHeader('content-type', 'image/png')->assertHeader('cache-control', 'no-store, private');
    }

    public function test_replacing_the_stamp_never_alters_an_issued_receipt(): void
    {
        $old = CompanyStamp::create(['image_path' => $this->png('stamps'), 'is_active' => true]);
        $receipt = $this->receiptFor($this->policy());
        $director = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($director)->post(route('admin.signatory.stamp.store'), ['stamp' => UploadedFile::fake()->image('n.png', 300, 300), 'password' => 'password']);

        $this->assertSame($old->id, $receipt->fresh()->stamp_id);
        $this->assertNotSame($old->id, CompanyStamp::active()->id);
    }
}
