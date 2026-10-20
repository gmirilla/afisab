<?php

namespace App\Services\Policy;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CompanyDetail;
use App\Models\CompanyStamp;
use App\Models\Investment;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Signatory;
use App\Models\User;
use App\Notifications\ReceiptDelivered;
use App\Support\Money;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Investment Receipt: proof that the company received the investor's money.
 *
 *  - One receipt per confirmed payment, issued when the payment is confirmed.
 *  - Numbered AFISAB/INV/{year}/{0001}, gap-free, restarting at 0001 each year.
 *  - Stored as the exact file issued, with its SHA-256 fingerprint; a correction is a new version (same number) that
 *    supersedes the old one; a receipt can be voided (never deleted) if the payment is later undone.
 *  - Signed with the active signatory's signature and the active company stamp, and records which it used.
 */
class ReceiptService
{
    /** Width x height in points: 148 x 222 mm, the 2:3 shape of the receipt design. */
    public const PAPER = [0, 0, 420, 630];

    private const ASSETS = ['lockup', 'banner', 'footer', 'user', 'coins', 'naira', 'calendar', 'target', 'document', 'wallet', 'pin', 'phone', 'mail', 'globe'];

    public function issue(Payment $payment, ?User $by = null, ?string $reason = null, bool $force = false): Receipt
    {
        return DB::transaction(function () use ($payment, $by, $reason, $force) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->with(['investment.user.investorProfile', 'investment.plan'])->firstOrFail();

            if ($payment->status !== PaymentStatus::Confirmed) {
                throw new BusinessRuleException('A receipt can only be issued for a confirmed payment.');
            }

            $latest = Receipt::where('payment_id', $payment->id)->orderByDesc('version')->first();
            if ($latest?->status === Receipt::VOID) {
                throw new BusinessRuleException('This receipt was voided and cannot be re-issued. Contact a Director.');
            }
            if ($latest && ! $force) {
                return $latest;
            }

            $signatory = Signatory::active();
            $stamp = CompanyStamp::active();
            $number = $latest?->receipt_number ?? $this->nextNumber();
            $receivedOn = ($payment->received_on ?? $payment->confirmed_at ?? now())->copy();
            $context = $this->context($payment, $number, $receivedOn, $signatory, $stamp);

            $bytes = Pdf::loadView('pdf.receipt', $context)->setPaper(self::PAPER)->output();
            $version = ($latest?->version ?? 0) + 1;
            $path = "receipts/{$payment->investment_id}/{$payment->id}-v{$version}.pdf";
            Storage::put($path, $bytes);

            $latest?->update(['status' => Receipt::SUPERSEDED, 'superseded_at' => now()]);
            $receipt = Receipt::create([
                'investment_id' => $payment->investment_id, 'payment_id' => $payment->id, 'receipt_number' => $number, 'version' => $version,
                'status' => Receipt::ISSUED, 'amount' => $payment->amount, 'received_on' => $receivedOn, 'file_path' => $path,
                'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes), 'signatory_id' => $signatory?->id, 'stamp_id' => $stamp?->id,
                'issued_by' => $by?->id, 'issued_at' => now(), 'reason' => $reason,
            ]);
            activity()->performedOn($payment->investment)->causedBy($by)->withProperties(['receipt' => $number, 'version' => $version, 'sha256' => $receipt->sha256, 'reason' => $reason])
                ->log($version === 1 ? 'Receipt issued' : 'Receipt re-issued');

            return $receipt;
        });
    }

    public function send(Investment $investment, ?User $by): Receipt
    {
        $receipt = $investment->currentReceipt() ?? $this->issueForInvestment($investment, $by);
        $investment->loadMissing('user');
        $investment->user->notify(new ReceiptDelivered($receipt));
        activity()->performedOn($investment)->causedBy($by)->withProperties(['receipt' => $receipt->receipt_number, 'to' => $investment->user->email])->log('Receipt emailed');

        return $receipt;
    }

    /** The receipt for an investment's confirmed payment, issuing it first if it was never generated. */
    public function issueForInvestment(Investment $investment, ?User $by = null, ?string $reason = null, bool $force = false): Receipt
    {
        $payment = $investment->payments()->where('status', PaymentStatus::Confirmed)->latest('id')->first();
        if (! $payment) {
            throw new BusinessRuleException('There is no confirmed payment to receipt yet.');
        }

        return $this->issue($payment, $by, $reason ?? 'Generated on request', $force);
    }

    public function void(Receipt $receipt, User $by, string $reason): Receipt
    {
        return DB::transaction(function () use ($receipt, $by, $reason) {
            $receipt = Receipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->status === Receipt::VOID) {
                throw new BusinessRuleException('This receipt is already void.');
            }
            // The whole receipt (every version) stops being valid: a receipt is for a payment, not for one file.
            Receipt::where('payment_id', $receipt->payment_id)->where('status', '!=', Receipt::VOID)->update(['status' => Receipt::VOID, 'voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);
            activity()->performedOn($receipt->investment)->causedBy($by)->withProperties(['receipt' => $receipt->receipt_number, 'reason' => $reason])->log('Receipt voided');

            return $receipt->refresh();
        });
    }

    public function download(Receipt $receipt): StreamedResponse
    {
        abort_unless(Storage::exists($receipt->file_path), 404, 'The stored file is missing.');

        return Storage::download($receipt->file_path, $receipt->filename(), ['Content-Type' => 'application/pdf']);
    }

    // ---- building the receipt ----

    /** @return array<string, mixed> */
    public function context(Payment $payment, string $number, $receivedOn, ?Signatory $signatory, ?CompanyStamp $stamp): array
    {
        $investment = $payment->investment;
        $profile = $investment->user->investorProfile;
        $company = CompanyDetail::current();
        $months = (int) $investment->duration_months;

        return [
            'company' => config('afisab.company_name'),
            'slogan' => config('afisab.slogan'),
            'assets' => $this->assets(),
            'details' => ['address' => $company->addressLines(), 'phones' => $company->phones(), 'emails' => $company->emails(), 'website' => $company->website],
            'receipt_number' => $number,
            'issued_on' => now()->format('d F Y'),
            'investor_name' => trim(($profile?->title ? $profile->title.' ' : '').$investment->user->name),
            'amount' => Money::format($payment->amount),
            'amount_words' => NumberToWords::naira($payment->amount),
            'period' => $this->period($months),
            'period_sentence' => strtolower($this->period($months)),
            'start' => $investment->start_date ? $investment->start_date->format('jS \o\f F, Y') : '',
            'maturity' => $investment->maturity_date ? $investment->maturity_date->format('jS \o\f F, Y') : '',
            'received' => $receivedOn->format('jS F, Y'),
            'frequency' => $investment->profit_frequency->label(),
            'payment_reference' => $this->paymentReference($payment),
            'signatory' => $this->image($signatory?->signature_path),
            'stamp' => $this->image($stamp?->image_path),
        ];
    }

    /** "One (1) Year", "Two (2) Years", "Three (3) Months" */
    private function period(int $months): string
    {
        if ($months > 0 && $months % 12 === 0) {
            $years = intdiv($months, 12);

            return NumberToWords::integer($years)." ({$years}) ".($years === 1 ? 'Year' : 'Years');
        }

        return NumberToWords::integer($months)." ({$months}) ".($months === 1 ? 'Month' : 'Months');
    }

    private function paymentReference(Payment $payment): string
    {
        $method = $payment->method === PaymentMethod::Paystack ? 'Paystack' : 'Bank transfer';
        $reference = $payment->reference.($payment->gateway_reference ? " (Paystack #{$payment->gateway_reference})" : '');

        return "{$method}: {$reference}";
    }

    private function nextNumber(): string
    {
        $year = now()->format('Y');
        $key = "receipt-{$year}";
        DB::table('number_sequences')->insertOrIgnore(['name' => $key, 'value' => 0]);
        $row = DB::table('number_sequences')->where('name', $key)->lockForUpdate()->first();
        $next = (int) $row->value + 1;
        DB::table('number_sequences')->where('name', $key)->update(['value' => $next]);

        return sprintf('%s/%s/%04d', config('afisab.receipt.prefix'), $year, $next);
    }

    private function image(?string $path): ?string
    {
        return $path && Storage::exists($path) ? 'data:image/png;base64,'.base64_encode(Storage::get($path)) : null;
    }

    /** The receipt's artwork (public/image/receipt, rebuilt by resources/receipt-assets/build.sh) as data URIs. */
    private function assets(): array
    {
        return collect(self::ASSETS)->mapWithKeys(function (string $name) {
            $file = public_path('image/receipt/'.($name === 'lockup' || $name === 'banner' || $name === 'footer' ? $name : "icon-{$name}").'.png');

            return [$name => is_file($file) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($file)) : ''];
        })->all();
    }
}
