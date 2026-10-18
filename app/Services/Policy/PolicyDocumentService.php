<?php

namespace App\Services\Policy;

use App\Enums\InvestmentStatus;
use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\PolicyDocument;
use App\Models\Signatory;
use App\Models\User;
use App\Notifications\PolicyDocumentDelivered;
use App\Support\Money;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Mudharabah Investment Agreement issued for every policy: its text, its PDF, and its life afterwards.
 *
 *  - The text lives here as data (sections() / schedule()), so the PDF and the on-screen preview the investor reads
 *    before agreeing are always the same words. Change wording by editing this class and raising TEMPLATE_VERSION.
 *  - Figures come from the terms frozen on the policy at subscription (PolicyTerms), never from today's settings.
 *  - An issued PDF is stored and hashed. Reprints and resends return that exact file. A correction issues a NEW
 *    version that supersedes the old one; nothing is overwritten.
 *  - The company signs with the active signatory's signature image; the document records which one it used.
 */
class PolicyDocumentService
{
    public function issue(Investment $investment, ?User $by = null, ?string $reason = null, bool $force = false): PolicyDocument
    {
        return DB::transaction(function () use ($investment, $by, $reason, $force) {
            $investment = Investment::whereKey($investment->id)->lockForUpdate()->with(['user.investorProfile', 'plan'])->firstOrFail();

            if (! in_array($investment->status, [InvestmentStatus::Active, InvestmentStatus::Matured, InvestmentStatus::PaidOut], true) || $investment->start_date === null) {
                throw new BusinessRuleException('A policy document can only be issued once the payment is confirmed.');
            }

            $existing = $investment->currentPolicyDocument();
            if ($existing && ! $force) {
                return $existing;
            }

            if ($investment->terms === null) { // older policies: freeze today's terms now
                $investment->terms = PolicyTerms::snapshot($investment->plan, $investment->profit_frequency);
            }
            if ($investment->policy_number === null) {
                $investment->policy_number = $this->nextPolicyNumber($investment->start_date);
            }
            $investment->save();

            $signatory = Signatory::active();
            $bytes = $this->render($this->context($investment, $signatory));
            $version = (int) $investment->policyDocuments()->max('version') + 1;
            $path = "policy-documents/{$investment->id}/v{$version}.pdf";
            Storage::put($path, $bytes);

            $existing?->update(['status' => PolicyDocument::SUPERSEDED, 'superseded_at' => now()]);
            $document = $investment->policyDocuments()->create([
                'version' => $version, 'status' => PolicyDocument::ISSUED, 'template_version' => PolicyTerms::TEMPLATE_VERSION,
                'policy_number' => $investment->policy_number, 'file_path' => $path, 'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes),
                'signatory_id' => $signatory?->id, 'issued_by' => $by?->id, 'issued_at' => now(), 'reason' => $reason,
            ]);
            activity()->performedOn($investment)->causedBy($by)->withProperties(['version' => $version, 'sha256' => $document->sha256, 'signed_by' => $signatory?->name, 'reason' => $reason])
                ->log($version === 1 ? 'Policy document issued' : 'Policy document re-issued');

            return $document;
        });
    }

    /** Email the current document to the investor's registered address. */
    public function send(Investment $investment, ?User $by, string $reason = 'copy'): PolicyDocument
    {
        $document = $investment->currentPolicyDocument() ?? $this->issue($investment, $by, 'Generated when a copy was requested');
        $investment->loadMissing('user');
        $investment->user->notify(new PolicyDocumentDelivered($document, $reason));
        activity()->performedOn($investment)->causedBy($by)->withProperties(['version' => $document->version, 'to' => $investment->user->email])->log('Policy document emailed');

        return $document;
    }

    public function download(PolicyDocument $document): StreamedResponse
    {
        abort_unless(Storage::exists($document->file_path), 404, 'The stored file is missing.');

        return Storage::download($document->file_path, $document->filename(), ['Content-Type' => 'application/pdf']);
    }

    // ---- building the document ----

    /** @return array<string, mixed> everything the PDF and the preview need, already formatted */
    public function context(Investment $investment, ?Signatory $signatory): array
    {
        $profile = $investment->user->investorProfile;
        $terms = $investment->terms ?? PolicyTerms::snapshot($investment->plan, $investment->profit_frequency);
        $made = $investment->start_date ?? now();

        return $this->base($terms, [
            'preview' => false,
            'policy_number' => (string) $investment->policy_number,
            'verify_url' => route('verify.show', $investment->certificate_token ?? 'pending'),
            'investor_name' => $investment->user->name,
            'investor_address' => $profile?->address ?: 'Not provided',
            'occupation' => $profile?->occupation ?: 'Not provided',
            'occupation_address' => $profile?->occupation_address ?: '',
            'dob' => $profile?->date_of_birth ? strtoupper($profile->date_of_birth->format('F jS, Y')) : 'Not provided',
            'nominee' => $this->nominee($profile),
            'plan_name' => $investment->plan->name,
            'duration_text' => $this->durationText($investment->duration_months),
            'commencement' => strtoupper($made->format('F jS, Y')),
            'maturity' => $investment->maturity_date ? strtoupper($investment->maturity_date->format('F jS, Y')) : '',
            'amount' => Money::format($investment->amount),
            'amount_words' => NumberToWords::naira($investment->amount),
            'frequency' => $investment->profit_frequency,
            'made_day' => $made->format('jS'), 'made_month' => $made->format('F'), 'made_year' => $made->format('Y'),
            'accepted' => $investment->terms_accepted_at ? ['at' => $investment->terms_accepted_at->format('d F Y, H:i'), 'ip' => $investment->terms_accepted_ip] : null,
            'signatory' => $this->signatoryData($signatory),
            'qr' => $this->qr(route('verify.show', $investment->certificate_token ?? 'pending')),
        ]);
    }

    /** The same words, for an investor who has not subscribed yet. */
    public function previewContext(User $user, InvestmentPlan $plan, ?ProfitFrequency $frequency = null, ?int $amount = null): array
    {
        $profile = $user->investorProfile;
        $terms = PolicyTerms::snapshot($plan, $frequency ?? ProfitFrequency::Monthly);

        return $this->base($terms, [
            'preview' => true,
            'policy_number' => 'Assigned when your payment is confirmed',
            'verify_url' => '',
            'investor_name' => $user->name,
            'investor_address' => $profile?->address ?: 'As given in your profile',
            'occupation' => $profile?->occupation ?: 'Not provided', 'occupation_address' => $profile?->occupation_address ?: '',
            'dob' => $profile?->date_of_birth ? strtoupper($profile->date_of_birth->format('F jS, Y')) : 'As given in your profile',
            'nominee' => $this->nominee($profile),
            'plan_name' => $plan->name,
            'duration_text' => $this->durationText($plan->duration_months),
            'commencement' => 'The date your payment is confirmed',
            'maturity' => 'The commencement date plus '.$this->durationText($plan->duration_months, false),
            'amount' => $amount ? Money::format($amount) : '₦ ____________',
            'amount_words' => $amount ? NumberToWords::naira($amount) : '____________________',
            'frequency' => $frequency,
            'made_day' => '……', 'made_month' => '……', 'made_year' => '20…',
            'accepted' => null, 'signatory' => null, 'qr' => null,
        ]);
    }

    /** @param  array<string, mixed>  $terms */
    private function base(array $terms, array $ctx): array
    {
        $shares = $terms['shares_bps'];

        return $ctx + [
            'company' => config('afisab.company_name'),
            'slogan' => config('afisab.slogan'),
            'duration_months' => (int) $terms['duration_months'],
            'shares' => array_map(fn ($bps) => PolicyTerms::percent($bps), $shares),
            'notice_months' => (int) $terms['early_withdrawal_notice_months'],
            'seat' => $terms['arbitration_seat'],
            'template_version' => $terms['template_version'] ?? PolicyTerms::TEMPLATE_VERSION,
            'logo' => $this->logo(),
        ];
    }

    /**
     * The agreement, as data. Each block is one of: ['p' => text], ['ul' => [text...]], ['checks' => [[label, ticked]...]],
     * or a marker the renderers expand: ['schedule' => true], ['execution' => true].
     *
     * @return list<array{no: string, title: string, break?: bool, blocks: list<array<string, mixed>>}>
     */
    public function sections(array $c): array
    {
        $company = $c['company'];
        $frequency = $c['frequency'];
        $ticked = fn (ProfitFrequency $f) => $frequency === $f;

        return [
            ['no' => '1', 'title' => 'PREAMBLE', 'break' => true, 'blocks' => [
                ['p' => 'WHEREAS:'],
                ['p' => "A. {$company} is engaged in the business of purchasing agricultural produce from farmers and suppliers and selling such produce to markets, processors, manufacturers, exporters, and other buyers."],
                ['p' => 'B. The Investor desires to provide capital for investment under the principles of Mudharabah in accordance with Islamic commercial jurisprudence.'],
                ['p' => "C. {$company} agrees to manage and invest the capital provided by the Investor for the purpose of generating halal profits through the trading of agricultural produce."],
                ['p' => 'NOW THEREFORE, the Parties hereby agree as follows:'],
            ]],
            ['no' => '2', 'title' => 'DEFINITIONS', 'blocks' => [
                ['p' => '2.1 For the purposes of this Agreement:'],
                ['ul' => [
                    '“Capital” means the amount invested by the Investor.',
                    '“Profit” means the net profit generated from the Mudharabah venture after deduction of legitimate business expenses.',
                    '“Loss” means any financial loss incurred in the ordinary course of business, excluding losses caused by negligence, misconduct, fraud, or breach of contract by the Mudarib.',
                    '“Business Activities” means the purchase, storage, transportation, processing (where applicable), and sale of agricultural produce.',
                ]],
                ['p' => '2.2 The Schedule on the following page forms part of this Agreement.'],
                ['schedule' => true],
            ]],
            ['no' => '3', 'title' => 'INVESTMENT CAPITAL', 'break' => true, 'blocks' => [
                ['p' => 'The Investor hereby contributes the sum of:'],
                ['p' => "**{$c['amount']}** (Amount in Words: {$c['amount_words']})"],
                ['p' => "to {$company} as Mudharabah Capital."],
                ['p' => 'The Mudarib acknowledges receipt of the capital and undertakes to utilize it solely for lawful business activities relating to agricultural produce trading.'],
            ]],
            ['no' => '4', 'title' => 'NATURE OF THE AGREEMENT', 'blocks' => [
                ['p' => '4.1 This Agreement is a Mudharabah arrangement whereby:'],
                ['ul' => ['The Investor provides the capital.', "{$company} provides management, expertise, and operational services."]],
                ['p' => '4.2 The Investor shall not participate in the day-to-day management of the business unless mutually agreed in writing.'],
            ]],
            ['no' => '5', 'title' => 'PURPOSE OF INVESTMENT', 'blocks' => [
                ['p' => 'The investment shall be used for:'],
                ['ul' => ['Purchase of farm produce;', 'Transportation and logistics;', 'Storage and warehousing;', 'Processing and packaging where necessary;', 'Marketing and sale of agricultural commodities;', 'Other lawful business activities related to agricultural produce trading.']],
            ]],
            ['no' => '6', 'title' => 'PROFIT SHARING', 'blocks' => [
                ['p' => '6.1 Net profits generated from the Mudharabah business shall be shared as follows:'],
                ['ul' => ["Investor: {$c['shares']['investor']}", "{$company} (Mudarib): {$c['shares']['company']}", "Risk Fund: {$c['shares']['risk_fund']}", "Reserve Fund: {$c['shares']['reserve']}"]],
                ['p' => '6.2 The profit-sharing ratio shall be determined as a percentage and not as a fixed monetary amount.'],
                ['p' => '6.3 AFISAB may, with the Investor’s written consent or as stated in the Schedule, retain a reasonable reserve from profits for:'],
                ['ul' => ['business contingencies;', 'logistics volatility;', 'spoilage, market fluctuation, or storage risk;', 'tax, statutory, or regulatory obligations;', 'GIT Takaful Insurance.']],
                ['p' => '6.4 Profit shall be distributed on the frequency chosen by the Investor:'],
                ['checks' => [
                    ['Monthly Basis', $ticked(ProfitFrequency::Monthly)],
                    ['Quarterly Basis', $ticked(ProfitFrequency::Quarterly)],
                    ['Half Yearly Basis', $ticked(ProfitFrequency::HalfYearly)],
                    ['Annually', $ticked(ProfitFrequency::Annually)],
                    ['One-off at maturity', $ticked(ProfitFrequency::AtMaturity)],
                ]],
                ['p' => '6.5 No profit shall be distributed until actual profits have been realized and verified.'],
            ]],
            ['no' => '7', 'title' => 'LOSSES', 'blocks' => [
                ['p' => '7.1 Any genuine business loss shall be borne solely by the Investor in proportion to the capital invested.'],
                ['p' => "7.2 {$company} shall lose only its effort, time, and management services unless the loss arises from:"],
                ['ul' => ['Fraud;', 'Gross negligence;', 'Misconduct;', 'Breach of this Agreement.']],
                ['p' => "7.3 Where loss results from any of the above actions by the Mudarib, {$company} shall be liable for such loss."],
            ]],
            ['no' => '8', 'title' => 'TERM OF INVESTMENT PERIOD', 'blocks' => [
                ['p' => "The duration of this Agreement shall be: {$this->durationText(null, false, $c)}, from the Commencement Date to the Maturity Date stated in the Schedule."],
                ['p' => 'The frequency of this Investment Return Payout shall be: '.($frequency ? $frequency->label() : 'as chosen by the Investor').', being one of Monthly Basis, Quarterly Basis, Half Yearly Basis, Annually or One-off at maturity.'],
                ['p' => 'The Parties may mutually agree to renew the Agreement.'],
            ]],
            ['no' => '9', 'title' => 'CAPITAL WITHDRAWAL', 'blocks' => [
                ['p' => '9.1 The Investor may request withdrawal of capital upon expiration of the agreed term.'],
                ['p' => "9.2 Early withdrawal shall require a minimum notice period of {$this->words($c['notice_months'])} ({$c['notice_months']}) Month".($c['notice_months'] === 1 ? '' : 's').' and shall be subject to the availability of funds and settlement of ongoing transactions.'],
                ['p' => '9.3 Capital shall not be guaranteed except in cases of negligence, fraud, or misconduct by the Mudarib.'],
            ]],
            ['no' => '10', 'title' => 'RECORDS AND REPORTING', 'blocks' => [
                ['p' => "10.1 {$company} shall maintain proper accounting records."],
                ['p' => '10.2 The Investor may request reasonable access to relevant financial records upon giving prior notice.'],
            ]],
            ['no' => '11', 'title' => 'SHARIAH COMPLIANCE', 'blocks' => [
                ['p' => '11.1 The Parties agree that all transactions under this Agreement shall comply with the principles of Islamic finance.'],
                ['p' => '11.2 The Mudarib shall not knowingly engage in any haram business activities.'],
            ]],
            ['no' => '12', 'title' => 'REPRESENTATIONS AND WARRANTIES', 'blocks' => [
                ['p' => 'The Mudarib represents that:'],
                ['ul' => ['It is duly incorporated under the laws of the Federal Republic of Nigeria.', 'It possesses the necessary expertise to conduct the business.', 'It shall act honestly and in good faith.']],
                ['p' => 'The Investor represents that:'],
                ['ul' => ['The invested funds are lawful.', 'The Investor understands the risk-sharing nature of Mudharabah.']],
            ]],
            ['no' => '13', 'title' => 'CONFIDENTIALITY', 'blocks' => [
                ['p' => 'Both Parties shall maintain confidentiality regarding business information, financial records, and trade secrets unless disclosure is required by law.'],
            ]],
            ['no' => '14', 'title' => 'DISPUTE RESOLUTION', 'blocks' => [
                ['p' => '14.1 Any dispute arising from this Agreement shall first be resolved through amicable negotiation.'],
                ['p' => '14.2 Where negotiation fails, the dispute shall be referred to mediation.'],
                ['p' => "14.3 If mediation fails, the dispute shall be submitted to arbitration in {$c['seat']}, in accordance with applicable laws."],
            ]],
            ['no' => '15', 'title' => 'GOVERNING LAW', 'blocks' => [
                ['p' => 'This Agreement shall be governed by the laws of the Federal Republic of Nigeria, provided that its interpretation shall also take into account the principles of Islamic commercial jurisprudence applicable to Mudharabah.'],
            ]],
            ['no' => '16', 'title' => 'TERMINATION', 'blocks' => [
                ['p' => 'This Agreement may be terminated:'],
                ['ul' => ['By mutual consent;', 'Upon expiration of the investment term;', 'Upon material breach by either Party;', 'Upon insolvency or winding up of either Party.']],
            ]],
            ['no' => '17', 'title' => 'ENTIRE AGREEMENT', 'blocks' => [
                ['p' => 'This Agreement constitutes the entire understanding between the Parties and supersedes all prior discussions or arrangements relating to the investment.'],
            ]],
            ['no' => '18', 'title' => 'EXECUTION', 'break' => true, 'blocks' => [
                ['p' => 'IN WITNESS WHEREOF, the Parties have executed this Agreement on the date first written above.'],
                ['execution' => true],
            ]],
        ];
    }

    /** @return list<array{string, list<string>}> label and value lines for the Schedule table */
    public function schedule(array $c): array
    {
        return [
            ['CERTIFICATE NUMBER', [$c['policy_number']]],
            ['PLAN', ['MUDHARABAH INVESTMENT CERTIFICATE', $c['plan_name']]],
            ['INVESTMENT CAPITAL', [$c['amount'], $c['amount_words']]],
            ['DURATION', [$c['duration_text']]],
            ['COMMENCEMENT DATE', [$c['commencement']]],
            ['MATURITY DATE', [$c['maturity']]],
            ['NAME AND ADDRESS OF THE INVESTOR', array_filter([strtoupper($c['investor_name']), $c['investor_address']])],
            ['OCCUPATION AND ADDRESS OF THE INVESTOR', array_filter([$c['occupation'], $c['occupation_address']])],
            ['DATE OF BIRTH', [$c['dob']]],
            ['NOMINEE', [$c['nominee']]],
            ['PROFIT PAYMENT', [$c['frequency'] ? strtoupper($c['frequency']->label()) : 'AS CHOSEN BY THE INVESTOR']],
            ['PROFIT SHARING (OF NET PROFIT)', ["Investor {$c['shares']['investor']} · {$c['company']} {$c['shares']['company']}", "Risk Fund {$c['shares']['risk_fund']} · Reserve Fund {$c['shares']['reserve']}"]],
        ];
    }

    /** The PDF, with "Page x of y" and the policy number on every page after the cover. */
    public function render(array $context): string
    {
        $pdf = Pdf::loadView('pdf.policy', $context + ['sections' => $this->sections($context), 'schedule' => $this->schedule($context)])->setPaper('a4');
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $left = $context['company'].'  ·  '.$context['policy_number'];
        $dompdf->getCanvas()->page_script(function ($page, $count, $canvas, $fontMetrics) use ($font, $left) {
            if ($page === 1) {
                return;
            }
            $y = $canvas->get_height() - 34;
            $right = "Page {$page} of {$count}";
            $canvas->text(56, $y, $left, $font, 7.5, [0.42, 0.45, 0.5]);
            $canvas->text($canvas->get_width() - 56 - $fontMetrics->getTextWidth($right, $font, 7.5), $y, $right, $font, 7.5, [0.42, 0.45, 0.5]);
        });

        return $dompdf->output();
    }

    // ---- helpers ----

    /** Escape a clause for HTML, turning **bold** markers into <strong>. */
    public static function inline(string $text): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($text)));
    }

    private function nextPolicyNumber(Carbon $start): string
    {
        DB::table('number_sequences')->insertOrIgnore(['name' => 'policy', 'value' => 0]);
        $row = DB::table('number_sequences')->where('name', 'policy')->lockForUpdate()->first();
        $next = (int) $row->value + 1;
        DB::table('number_sequences')->where('name', 'policy')->update(['value' => $next]);

        return sprintf('MIC/%s/%s/%07d', $start->format('m-Y'), strtoupper((string) config('afisab.policy.code')), $next);
    }

    private function durationText(?int $months, bool $upper = true, ?array $c = null): string
    {
        $months ??= (int) ($c['duration_months'] ?? 0);
        $text = $months % 12 === 0 && $months > 0
            ? ($months / 12).($months === 12 ? ' year' : ' years')
            : $months.' months';

        return $upper ? strtoupper($text) : $text;
    }

    private function words(int $n): string
    {
        return NumberToWords::integer($n);
    }

    private function nominee(?object $profile): string
    {
        if (! $profile?->nominee_name) {
            return 'Not provided';
        }

        return $profile->nominee_name.($profile->nominee_relationship ? " ({$profile->nominee_relationship})" : '').($profile->nominee_phone ? ", {$profile->nominee_phone}" : '');
    }

    /** @return array{name: string, title: string, image: ?string}|null */
    private function signatoryData(?Signatory $signatory): ?array
    {
        if (! $signatory) {
            return null;
        }
        $image = Storage::exists($signatory->signature_path) ? 'data:image/png;base64,'.base64_encode(Storage::get($signatory->signature_path)) : null;

        return ['name' => $signatory->name, 'title' => $signatory->title, 'image' => $image];
    }

    private function logo(): string
    {
        $path = public_path('image/logo-full.jpg');

        return is_file($path) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path)) : '';
    }

    private function qr(string $data): string
    {
        return (new Builder(writer: new PngWriter, data: $data, size: 220, margin: 2))->build()->getDataUri();
    }
}
