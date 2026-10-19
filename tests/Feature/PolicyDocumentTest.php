<?php

namespace Tests\Feature;

use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\LedgerException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\PolicyDocument;
use App\Models\Signatory;
use App\Models\User;
use App\Notifications\PolicyDocumentDelivered;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\Performance\PerformanceService;
use App\Services\Performance\ProfitSchedule;
use App\Services\Performance\ProfitSplit;
use App\Services\Policy\PolicyDocumentService;
use App\Services\Policy\PolicyTerms;
use App\Services\SubscriptionService;
use App\Support\NumberToWords;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PolicyDocumentTest extends TestCase
{
    use RefreshDatabase;

    private PolicyDocumentService $documents;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        config(['afisab.policy.code' => 'OYO']); // the sample's code; the setting's own default is free to change
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        $this->documents = app(PolicyDocumentService::class);
    }

    private function investor(string $name = 'Ridwan Farayola', array $profile = []): User
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create($profile + [
            'phone' => '08031234567', 'kyc_status' => 'approved', 'address' => '40 Clinton Avenue, GAP Estate, Abuja', 'date_of_birth' => '1977-10-10',
            'occupation' => 'Tailoring', 'occupation_address' => 'Bodija, Ibadan', 'nominee_name' => 'Aisha Farayola', 'nominee_relationship' => 'Spouse', 'nominee_phone' => '08035556666',
        ]);

        return $user;
    }

    private function policy(ProfitFrequency $frequency = ProfitFrequency::Quarterly, ?User $user = null, bool $accept = true, string $slug = '12-month', int $amount = 12_500_000_00): Investment
    {
        $user ??= $this->investor();
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', $slug)->first(), $amount, $frequency, $accept ? ['ip' => '102.89.1.1'] : null);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    private function signatory(string $name = 'Alhaji Musa Bello'): Signatory
    {
        $path = 'signatures/'.uniqid().'.png';
        $im = imagecreatetruecolor(300, 100);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imageline($im, 5, 90, 150, 10, imagecolorallocate($im, 20, 30, 120));
        ob_start();
        imagepng($im);
        Storage::put($path, ob_get_clean());
        Signatory::where('is_active', true)->update(['is_active' => false]);

        return Signatory::create(['name' => $name, 'title' => 'Managing Director / CEO', 'signature_path' => $path, 'is_active' => true]);
    }

    private function text(array $context): string
    {
        return collect($this->documents->sections($context))->flatMap(fn ($s) => collect($s['blocks'])->map(fn ($b) => $s['no'].'. '.$s['title']."\n".json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))->implode("\n")
            .json_encode($this->documents->schedule($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ---- amounts in words ----

    public function test_amounts_are_written_in_words(): void
    {
        $this->assertSame('Twelve Million, Five Hundred Thousand Naira Only', NumberToWords::naira(12_500_000_00));
        $this->assertSame('Five Hundred Thousand Naira Only', NumberToWords::naira(500_000_00));
        $this->assertSame('One Thousand and Five Naira Only', NumberToWords::naira(1_005_00));
        $this->assertSame('One Thousand, Two Hundred and Thirty-Four Naira and Fifty Kobo Only', NumberToWords::naira(1_234_50));
        $this->assertSame('One Million Naira Only', NumberToWords::naira(1_000_000_00));
        $this->assertSame('Fifty Kobo Only', NumberToWords::naira(50));
        $this->assertSame('Zero Naira Only', NumberToWords::naira(0));
        $this->assertSame('One Hundred and Twenty-Five Naira Only', NumberToWords::naira(125_00));
    }

    // ---- terms frozen at subscription ----

    public function test_terms_are_frozen_when_the_investor_subscribes(): void
    {
        $i = $this->policy(ProfitFrequency::Quarterly);

        $this->assertSame(['investor' => 2400, 'company' => 5600, 'risk_fund' => 0, 'reserve' => 2000], $i->terms['shares_bps']);
        $this->assertSame([2000, 3000, 12, 'quarterly', PolicyTerms::TEMPLATE_VERSION], [$i->terms['retention_bps'], $i->terms['investor_share_bps'], $i->terms['duration_months'], $i->terms['profit_frequency'], $i->terms['template_version']]);
        $this->assertSame(10_000, array_sum($i->terms['shares_bps']), 'the four shares add up to the whole profit');
        $this->assertSame('102.89.1.1', $i->terms_accepted_ip);
        $this->assertNotNull($i->terms_accepted_at);

        $unaccepted = $this->policy(ProfitFrequency::Monthly, $this->investor('No Tick'), accept: false);
        $this->assertNull($unaccepted->terms_accepted_at);
        $this->assertNotNull($unaccepted->terms);
    }

    public function test_profit_is_calculated_with_the_terms_the_investor_accepted_not_todays_settings(): void
    {
        $i = $this->policy(ProfitFrequency::Monthly);
        config(['afisab.profit_retention_bps' => 3000]); // the company changes its rules afterwards

        $this->assertSame(200_000, (new ProfitSplit)->split(1_000_000, $i->terms)['retained'], 'the contract said 20%');
        $this->assertSame(300_000, (new ProfitSplit)->split(1_000_000)['retained'], 'a policy without frozen terms follows the new rule');

        app(PerformanceService::class)->recordCycleResult('C1', [$i->id => $i->amount], 1_000_000_00);
        $run = app(DistributionService::class)->calculate(now(), null, notify: false);
        $this->assertSame(200_000_00, $run->lines()->firstOrFail()->retained);
    }

    public function test_half_yearly_and_annual_payments_fall_due_on_their_months(): void
    {
        $half = $this->policy(ProfitFrequency::HalfYearly, $this->investor('Half'));
        $annual = $this->policy(ProfitFrequency::Annually, $this->investor('Annual'));
        $due = fn (Investment $i, string $month) => ProfitSchedule::isDue($i, Carbon::parse($month.'-01'));

        foreach (['2027-01', '2027-03', '2027-05'] as $m) {
            $this->assertFalse($due($half, $m), "half-yearly $m");
        }
        foreach (['2027-04', '2027-10'] as $m) { // 6 months after the start, and the final run
            $this->assertTrue($due($half, $m), "half-yearly $m");
        }
        $this->assertFalse($due($annual, '2027-04'));
        $this->assertTrue($due($annual, '2027-10'));
        $this->assertSame('2027-04-30', ProfitSchedule::nextRun($half)['pay_by']->toDateString());

        $this->expectException(BusinessRuleException::class);
        $this->policy(ProfitFrequency::Annually, $this->investor('Too short'), slug: '6-month'); // a 6-month plan cannot pay yearly
    }

    // ---- what the agreement says ----

    public function test_the_agreement_states_the_policys_own_terms(): void
    {
        $i = $this->policy(ProfitFrequency::Quarterly);
        $i->update(['policy_number' => 'MIC/10-2026/OYO/0000001']);
        $text = $this->text($this->documents->context($i->fresh()->load(['user.investorProfile', 'plan']), null));
        $missing = [];

        foreach ([
            '12,500,000.00', 'Twelve Million, Five Hundred Thousand Naira Only',
            'Investor: 24.00%', 'Mudarib): 56.00%', 'Risk Fund: 0.00%', 'Reserve Fund: 20.00%',
            'OCTOBER 20TH, 2026', 'OCTOBER 20TH, 2027', '1 YEAR', 'MIC/10-2026/OYO/0000001',
            'One (1) Month', 'Abuja, Federal Capital Territory, Nigeria', 'QUARTERLY',
            'Aisha Farayola (Spouse), 08035556666', 'Tailoring', 'OCTOBER 10TH, 1977', 'RIDWAN FARAYOLA',
        ] as $expected) {
            if (! str_contains($text, $expected)) {
                $missing[] = $expected;
            }
        }
        $this->assertSame([], $missing, 'phrases missing from the agreement');
        foreach (range(1, 18) as $n) {
            $this->assertStringContainsString("{$n}. ", $text);
        }
        $this->assertStringContainsString('3. INVESTMENT CAPITAL', $text);
        // Only the chosen frequency is ticked.
        $this->assertSame(1, preg_match_all('/true\]/', $text), 'exactly one frequency box is ticked');
        $this->assertStringContainsString('["Quarterly Basis",true]', $text);
        // Nothing from the takaful savings product the sample was copied from.
        foreach (['PIA', 'Interest free loan', 'INSTALMENT', 'AGE NEXT BIRTHDAY', 'SUM COVERED', 'SUPPLEMENTARY'] as $absent) {
            $this->assertStringNotContainsStringIgnoringCase($absent, $text);
        }
    }

    public function test_missing_profile_details_say_so_instead_of_leaving_blanks(): void
    {
        $user = $this->investor('Bare', ['occupation' => null, 'occupation_address' => null, 'nominee_name' => null, 'nominee_relationship' => null, 'nominee_phone' => null]);
        $i = $this->policy(ProfitFrequency::Monthly, $user);

        $text = $this->text($this->documents->context($i->load(['user.investorProfile', 'plan']), null));

        $this->assertSame(2, substr_count($text, 'Not provided'), 'occupation and nominee');
    }

    // ---- issuing, storing, versions ----

    public function test_issuing_stores_the_exact_pdf_with_its_fingerprint_and_is_idempotent(): void
    {
        $signatory = $this->signatory();
        $i = $this->policy();

        $doc = $this->documents->issue($i);

        $this->assertSame('MIC/10-2026/OYO/0000001', $doc->policy_number);
        $this->assertSame(1, $doc->version);
        $this->assertSame($signatory->id, $doc->signatory_id);
        Storage::assertExists($doc->file_path);
        $bytes = Storage::get($doc->file_path);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $doc->sha256);
        $this->assertSame(strlen($bytes), $doc->size);
        preg_match_all('/\/Type\s*\/Page[^s]/', $bytes, $pages);
        $this->assertGreaterThanOrEqual(7, count($pages[0]));
        $this->assertLessThanOrEqual(12, count($pages[0]));
        $this->assertSame('MIC/10-2026/OYO/0000001', $i->fresh()->policy_number);

        $this->assertTrue($this->documents->issue($i->fresh())->is($doc), 'asking again returns the same document');
        $this->assertSame(1, PolicyDocument::count());
        $this->assertDatabaseHas('activity_log', ['description' => 'Policy document issued']);
    }

    public function test_policy_numbers_run_in_sequence_without_gaps(): void
    {
        $a = $this->documents->issue($this->policy(ProfitFrequency::Monthly, $this->investor('A')));
        $b = $this->documents->issue($this->policy(ProfitFrequency::Monthly, $this->investor('B')));

        $this->assertSame(['MIC/10-2026/OYO/0000001', 'MIC/10-2026/OYO/0000002'], [$a->policy_number, $b->policy_number]);
        config(['afisab.policy.code' => 'ABJ']);
        $c = $this->documents->issue($this->policy(ProfitFrequency::Monthly, $this->investor('C')));
        $this->assertSame('MIC/10-2026/ABJ/0000003', $c->policy_number);
    }

    public function test_an_agreement_cannot_be_issued_before_payment_is_confirmed(): void
    {
        $i = app(SubscriptionService::class)->subscribe($this->investor(), InvestmentPlan::first(), 1_000_000_00);

        $this->expectException(BusinessRuleException::class);
        $this->documents->issue($i);
    }

    public function test_reissuing_supersedes_but_never_alters_the_original(): void
    {
        $first = $this->signatory('Old Signer');
        $i = $this->policy();
        $v1 = $this->documents->issue($i);
        $originalBytes = Storage::get($v1->file_path);

        $second = $this->signatory('New Signer');
        $v2 = $this->documents->issue($i->fresh(), reason: 'Address corrected', force: true);

        $this->assertSame([2, $v1->policy_number, 'Address corrected', $second->id], [$v2->version, $v2->policy_number, $v2->reason, $v2->signatory_id]);
        $this->assertSame(PolicyDocument::SUPERSEDED, $v1->fresh()->status);
        $this->assertSame($first->id, $v1->fresh()->signatory_id, 'the old document still says who signed it');
        $this->assertSame($originalBytes, Storage::get($v1->file_path), 'the old file is untouched');
        $this->assertTrue($i->fresh()->currentPolicyDocument()->is($v2));
        $this->assertStringEndsWith('-v2.pdf', $v2->filename());

        $this->expectException(LedgerException::class);
        $v1->update(['sha256' => str_repeat('0', 64)]);
    }

    public function test_issued_documents_cannot_be_deleted_or_repointed(): void
    {
        $doc = $this->documents->issue($this->policy());

        foreach ([fn () => $doc->delete(), fn () => $doc->update(['file_path' => 'x.pdf']), fn () => $doc->update(['policy_number' => 'X'])] as $attempt) {
            try {
                $attempt();
                $this->fail('a policy document was changed');
            } catch (LedgerException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_older_policy_without_frozen_terms_gets_them_when_first_issued(): void
    {
        $i = $this->policy();
        $i->update(['terms' => null, 'terms_accepted_at' => null]);

        $this->documents->issue($i->fresh());

        $this->assertNotNull($i->fresh()->terms);
        $this->assertSame(2400, $i->fresh()->terms['shares_bps']['investor']);
    }

    public function test_with_no_signatory_the_agreement_is_still_issued_unsigned(): void
    {
        $doc = $this->documents->issue($this->policy());

        $this->assertNull($doc->signatory_id);
        $this->assertStringStartsWith('%PDF', Storage::get($doc->file_path));
    }

    // ---- automatic issue after payment ----

    public function test_confirming_a_payment_issues_the_receipt_and_agreement_and_emails_them_together(): void
    {
        config(['afisab.policy.auto_issue' => true]);
        Notification::fake();
        $this->signatory();

        $i = $this->policy();

        $doc = $i->currentPolicyDocument();
        $this->assertNotNull($doc);
        Notification::assertSentTo($i->user, \App\Notifications\InvestmentDocumentsIssued::class, fn ($n) => $n->document->is($doc) && $n->receipt->is($i->currentReceipt()));
        Notification::assertSentToTimes($i->user, \App\Notifications\InvestmentDocumentsIssued::class, 1);
    }

    public function test_the_agreement_email_carries_the_pdf(): void
    {
        $i = $this->policy();
        $doc = $this->documents->issue($i);

        $i->user->notify(new PolicyDocumentDelivered($doc, 'copy'));

        $mail = collect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage())->first(fn ($m) => str_contains((string) $m->getSubject(), 'A copy of your AFISAB investment agreement'));
        $this->assertNotNull($mail);
        $this->assertSame($i->user->email, $mail->getTo()[0]->getAddress());
        $this->assertCount(1, $mail->getAttachments());
        $this->assertSame('AFISAB-Policy-MIC-10-2026-OYO-0000001.pdf', $mail->getAttachments()[0]->getFilename());
        $this->assertStringContainsString('MIC/10-2026/OYO/0000001', $mail->getHtmlBody());
    }

    // ---- the investor ----

    public function test_an_investor_must_agree_before_subscribing_and_the_agreement_is_readable_first(): void
    {
        $user = $this->investor();
        $plan = InvestmentPlan::where('slug', '12-month')->first();
        $base = ['investment_plan_id' => $plan->id, 'amount' => '500,000', 'profit_frequency' => 'annually'];

        $this->actingAs($user)->get(route('investor.plans'))->assertOk()->assertSee('name="accept_terms"', false)->assertSee(route('investor.agreement', ['plan' => $plan->id]), false)->assertSee('Yearly');
        $this->actingAs($user)->post(route('investor.investments.store'), $base)->assertSessionHasErrors('accept_terms');
        $this->assertSame(0, Investment::count());

        $this->actingAs($user)->post(route('investor.investments.store'), $base + ['accept_terms' => '1'])->assertSessionHasNoErrors();
        $i = Investment::firstOrFail();
        $this->assertNotNull($i->terms_accepted_at);
        $this->assertSame('127.0.0.1', $i->terms_accepted_ip);
        $this->assertSame(ProfitFrequency::Annually, $i->profit_frequency);

        $this->actingAs($user)->get(route('investor.investments.show', $i))->assertOk()->assertSee('You agreed to the Mudharabah Investment Agreement');
    }

    public function test_the_preview_shows_the_same_words_with_blanks_to_be_filled(): void
    {
        $user = $this->investor();
        $plan = InvestmentPlan::where('slug', '12-month')->first();

        $page = $this->actingAs($user)->get(route('investor.agreement', ['plan' => $plan->id, 'frequency' => 'quarterly', 'amount' => '2,000,000']))->assertOk();

        $page->assertSee('MUDHARABAH INVESTMENT AGREEMENT')->assertSee('Investor: 24.00%')->assertSee('Two Million Naira Only')->assertSee('Assigned when your payment is confirmed')
            ->assertSee('☒ Quarterly Basis', false)->assertSee('RIDWAN FARAYOLA');
        $this->actingAs(User::factory()->create()->assignRole('accounts'))->get(route('investor.agreement', ['plan' => $plan->id]))->assertForbidden();
    }

    public function test_an_investor_downloads_and_emails_only_their_own_agreement(): void
    {
        $i = $this->policy();
        $this->documents->issue($i);
        $stranger = $this->investor('Stranger');

        $page = $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertOk();
        $page->assertSee('Download agreement (PDF)')->assertSee('MIC/10-2026/OYO/0000001')->assertSee('Email me a copy');

        $this->actingAs($i->user)->get(route('investor.policy.download', $i))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('activity_log', ['description' => 'Policy document downloaded', 'causer_id' => $i->user->id]);
        $this->actingAs($stranger)->get(route('investor.policy.download', $i))->assertNotFound();
        $this->actingAs($stranger)->post(route('investor.policy.email', $i))->assertNotFound();

        $this->actingAs($i->user)->post(route('investor.policy.email', $i))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseHas('activity_log', ['description' => 'Policy document emailed', 'causer_id' => $i->user->id]);
    }

    public function test_an_investor_can_only_email_themselves_a_few_copies_a_day(): void
    {
        $i = $this->policy();
        $this->documents->issue($i);

        foreach (range(1, 3) as $n) {
            $this->actingAs($i->user)->post(route('investor.policy.email', $i))->assertSessionHasNoErrors();
        }
        $this->actingAs($i->user)->post(route('investor.policy.email', $i))->assertSessionHasErrors('policy');

        $this->travelTo(now()->addDay()->addMinute());
        $this->actingAs($i->user)->post(route('investor.policy.email', $i))->assertSessionHasNoErrors();
    }

    public function test_if_the_agreement_was_never_generated_the_investor_can_still_get_it(): void
    {
        $i = $this->policy(); // auto-issue is off in the test suite, so nothing exists yet
        $this->assertNull($i->currentPolicyDocument());

        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertSee('Your agreement is being prepared');
        $this->actingAs($i->user)->get(route('investor.policy.download', $i))->assertSessionHasErrors('policy');

        $this->actingAs($i->user)->post(route('investor.policy.email', $i))->assertSessionHasNoErrors();
        $this->assertNotNull($i->fresh()->currentPolicyDocument());
    }

    public function test_occupation_and_nominee_can_be_saved_even_after_kyc_is_approved(): void
    {
        $user = $this->investor('Late Updater', ['occupation' => null, 'nominee_name' => null]);

        $this->actingAs($user)->put(route('investor.profile.update'), ['section' => 'agreement', 'occupation' => 'Farmer', 'nominee_name' => 'Musa', 'nominee_relationship' => 'Brother', 'nominee_phone' => '08031112222'])->assertSessionHasNoErrors();
        $this->assertSame(['Farmer', 'Musa'], [$user->investorProfile->fresh()->occupation, $user->investorProfile->fresh()->nominee_name]);
        $this->actingAs($user)->put(route('investor.profile.update'), ['section' => 'agreement', 'nominee_phone' => 'not a phone'])->assertSessionHasErrors('nominee_phone');
        $this->actingAs($user)->get(route('investor.profile'))->assertSee('Details for your investment agreement');
    }

    // ---- staff ----

    public function test_staff_can_reprint_resend_and_reissue_according_to_their_role(): void
    {
        $i = $this->policy();
        $doc = $this->documents->issue($i);
        $roles = fn (string $r) => User::factory()->create()->assignRole($r);
        [$accounts, $compliance, $auditor, $hr, $sales] = [$roles('accounts'), $roles('compliance'), $roles('auditor'), $roles('hr'), $roles('sales')];

        foreach ([$accounts, $compliance, $auditor, $sales] as $staff) {
            $this->actingAs($staff)->get(route('admin.policies.download', $doc))->assertOk()->assertHeader('content-type', 'application/pdf');
        }
        $this->actingAs($hr)->get(route('admin.policies.download', $doc))->assertForbidden();
        $this->assertDatabaseHas('activity_log', ['description' => 'Policy document downloaded by staff', 'causer_id' => $accounts->id]);

        foreach ([$accounts, $compliance] as $staff) {
            $this->actingAs($staff)->post(route('admin.policies.send', $i))->assertSessionHasNoErrors()->assertSessionHas('status');
        }
        foreach ([$auditor, $sales, $hr] as $staff) {
            $this->actingAs($staff)->post(route('admin.policies.send', $i))->assertForbidden();
        }
        $this->assertSame(2, \Spatie\Activitylog\Models\Activity::where('description', 'Policy document emailed')->count());

        $this->actingAs($accounts)->post(route('admin.policies.reissue', $i), ['reason' => 'because'])->assertForbidden();
        $this->actingAs($compliance)->post(route('admin.policies.reissue', $i), [])->assertSessionHasErrors('reason');
        $this->actingAs($compliance)->post(route('admin.policies.reissue', $i), ['reason' => 'ok'])->assertSessionHasErrors('reason'); // too short
        $this->assertSame(1, PolicyDocument::count());

        $this->actingAs($compliance)->post(route('admin.policies.reissue', $i), ['reason' => 'Nominee updated', 'send_copy' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(2, $i->fresh()->currentPolicyDocument()->version);
        $this->assertDatabaseHas('activity_log', ['description' => 'Policy document re-issued', 'causer_id' => $compliance->id]);
    }

    public function test_the_investor_page_for_staff_lists_every_version_with_the_right_buttons(): void
    {
        $i = $this->policy();
        $this->documents->issue($i);
        $this->documents->issue($i->fresh(), reason: 'Signatory changed', force: true);
        $accounts = User::factory()->create()->assignRole('accounts');
        $compliance = User::factory()->create()->assignRole('compliance');

        $this->actingAs($accounts)->get(route('admin.investors.show', $i->user))->assertOk()
            ->assertSee('Investment receipts and agreements')->assertSee('v2 (current)')->assertSee('v1 (superseded)')->assertSee('Signatory changed')->assertSee('Resend to investor')->assertDontSee('Re-issue as a new version');
        $this->actingAs($compliance)->get(route('admin.investors.show', $i->user))->assertSee('Re-issue as a new version');
    }

    // ---- the signatory ----

    public function test_only_the_super_admin_manages_the_signatory_and_needs_their_password(): void
    {
        $director = User::factory()->create()->assignRole('super-admin');
        $file = fn (int $w = 300, int $h = 100, string $name = 'sig.png') => UploadedFile::fake()->image($name, $w, $h);
        $form = fn ($signature) => ['name' => 'Alhaji Musa Bello', 'title' => 'Managing Director / CEO', 'signature' => $signature, 'password' => 'password'];

        foreach (['accounts', 'compliance', 'hr', 'sales'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get(route('admin.signatory'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.signatory.store'), $form($file()))->assertForbidden();
        }
        $this->assertSame(0, Signatory::count());

        $this->actingAs($director)->post(route('admin.signatory.store'), ['password' => 'wrong'] + $form($file()))->assertSessionHasErrors('password');
        $this->actingAs($director)->post(route('admin.signatory.store'), $form(UploadedFile::fake()->create('evil.php', 10, 'application/x-php')))->assertSessionHasErrors('signature');
        $this->actingAs($director)->post(route('admin.signatory.store'), $form($file(50, 20)))->assertSessionHasErrors('signature'); // too small to be legible
        $this->actingAs($director)->post(route('admin.signatory.store'), $form(UploadedFile::fake()->image('big.png', 1000, 400)->size(2000)))->assertSessionHasErrors('signature');
        $this->assertSame(0, Signatory::count());

        $this->actingAs($director)->post(route('admin.signatory.store'), $form($file(2000, 800)))->assertSessionHasNoErrors();
        $active = Signatory::active();
        $this->assertSame('Alhaji Musa Bello', $active->name);
        $size = getimagesizefromstring(Storage::get($active->signature_path));
        $this->assertSame(IMAGETYPE_PNG, $size[2]);
        $this->assertLessThanOrEqual(600, $size[0]);
        $this->assertLessThanOrEqual(240, $size[1]);
        $this->assertDatabaseHas('activity_log', ['description' => 'Signatory set', 'causer_id' => $director->id]);
    }

    public function test_replacing_the_signatory_keeps_the_old_one_and_never_touches_issued_agreements(): void
    {
        $director = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($director)->post(route('admin.signatory.store'), ['name' => 'First', 'title' => 'MD', 'signature' => UploadedFile::fake()->image('a.png', 300, 100), 'password' => 'password']);
        $first = Signatory::active();
        $doc = $this->documents->issue($this->policy());

        $this->actingAs($director)->post(route('admin.signatory.store'), ['name' => 'Second', 'title' => 'MD', 'signature' => UploadedFile::fake()->image('b.png', 300, 100), 'password' => 'password']);

        $this->assertSame(2, Signatory::count());
        $this->assertSame('Second', Signatory::active()->name);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertSame($first->id, $doc->fresh()->signatory_id);
        Storage::assertExists($first->signature_path);

        $this->actingAs($director)->post(route('admin.signatory.activate', $first), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($director)->post(route('admin.signatory.activate', $first), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('First', Signatory::active()->name);
        $this->actingAs($director)->get(route('admin.signatory'))->assertOk()->assertSee('Policy signatory')->assertSee('Company stamp')->assertSee('Earlier signatories');
    }

    public function test_the_signature_image_is_never_public_and_is_private_to_the_super_admin(): void
    {
        $signatory = $this->signatory();
        $director = User::factory()->create()->assignRole('super-admin');

        $this->get(route('admin.signatory.image', $signatory))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create()->assignRole('compliance'))->get(route('admin.signatory.image', $signatory))->assertForbidden();
        $this->actingAs($director)->get(route('admin.signatory.image', $signatory))->assertOk()->assertHeader('content-type', 'image/png')->assertHeader('cache-control', 'no-store, private');
    }

    public function test_the_dashboard_warns_the_super_admin_until_a_signatory_is_set(): void
    {
        $director = User::factory()->create()->assignRole('super-admin');
        $accounts = User::factory()->create()->assignRole('accounts');

        $this->actingAs($director)->get(route('admin.dashboard'))->assertSee('No signatory is set');
        $this->actingAs($accounts)->get(route('admin.dashboard'))->assertDontSee('No signatory is set');

        $this->signatory();
        $this->actingAs($director)->get(route('admin.dashboard'))->assertDontSee('No signatory is set');
    }
}
