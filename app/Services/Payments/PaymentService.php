<?php

namespace App\Services\Payments;

use App\Enums\InvestmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmed;
use App\Services\Ledger\InvestorFundsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private InvestorFundsService $funds,
        private PaystackClient $paystack,
    ) {}

    /** Start (or reuse) a bank-transfer payment; the investor then uploads proof. */
    public function startTransfer(Investment $investment): Payment
    {
        $this->assertPayable($investment);

        return $investment->payments()->firstOrCreate(
            ['method' => PaymentMethod::Transfer, 'status' => PaymentStatus::Pending],
            ['amount' => $investment->amount, 'reference' => $this->newReference()],
        );
    }

    public function submitProof(Payment $payment, UploadedFile $proof): Payment
    {
        if ($payment->method !== PaymentMethod::Transfer || ! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::AwaitingConfirmation], true)) {
            throw new BusinessRuleException('Proof cannot be submitted for this payment.');
        }

        $path = $proof->store('payment-proofs'); // private disk
        $payment->update(['proof_path' => $path, 'status' => PaymentStatus::AwaitingConfirmation]);

        return $payment;
    }

    /** @return string Paystack checkout URL */
    public function startPaystack(Investment $investment): string
    {
        $this->assertPayable($investment);

        $payment = $investment->payments()->create([
            'method' => PaymentMethod::Paystack,
            'amount' => $investment->amount,
            'status' => PaymentStatus::Pending,
            'reference' => $this->newReference(),
        ]);

        return $this->paystack->initialize(
            $investment->user->email,
            $payment->amount,
            $payment->reference,
            route('investor.payments.callback'),
        );
    }

    /** Verify a Paystack payment with the gateway and confirm it if genuine. */
    public function settlePaystack(string $reference): ?Payment
    {
        $payment = Payment::where('reference', $reference)->where('method', PaymentMethod::Paystack)->first();
        if (! $payment) {
            return null;
        }
        if ($payment->status === PaymentStatus::Confirmed) {
            return $payment;
        }

        $result = $this->paystack->verify($reference);
        if (! $result['paid'] || $result['currency'] !== 'NGN') {
            if ($payment->status === PaymentStatus::Pending && ! $result['paid']) {
                return $payment; // still pending / abandoned; leave it open for the webhook
            }
            throw new BusinessRuleException('Payment could not be verified.');
        }

        return $this->confirm($payment, null, $result['amount'], $result['gateway_reference']);
    }

    /**
     * Confirm a payment: activate the policy and book the funds into the ledger.
     * Idempotent and safe under concurrent webhook + callback + staff action.
     */
    public function confirm(Payment $payment, ?User $by = null, ?int $paidAmount = null, ?string $gatewayReference = null): Payment
    {
        return DB::transaction(function () use ($payment, $by, $paidAmount, $gatewayReference) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Confirmed) {
                return $payment;
            }
            if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Rejected], true)) {
                throw new BusinessRuleException('This payment was already '.strtolower($payment->status->label()).'.');
            }
            if ($paidAmount !== null && $paidAmount !== $payment->amount) {
                throw new BusinessRuleException('Paid amount does not match the amount due.');
            }

            $investment = Investment::whereKey($payment->investment_id)->lockForUpdate()->firstOrFail();
            if ($investment->status !== InvestmentStatus::PendingPayment) {
                throw new BusinessRuleException('This investment is not awaiting payment.');
            }

            $payment->update([
                'status' => PaymentStatus::Confirmed,
                'confirmed_by' => $by?->id,
                'confirmed_at' => now(),
                'gateway_reference' => $gatewayReference ?? $payment->gateway_reference,
            ]);

            $start = now()->startOfDay();
            $investment->update([
                'status' => InvestmentStatus::Active,
                'start_date' => $start,
                'maturity_date' => $start->copy()->addMonthsNoOverflow($investment->duration_months),
                'certificate_number' => sprintf('AFC-%s-%06d', $start->format('Y'), $investment->id),
                'certificate_token' => Str::lower(Str::random(20)), // unguessable key for public verification
                'certificate_issued_at' => now(),
            ]);

            // Other open attempts for this policy are now void.
            $investment->payments()->where('id', '!=', $payment->id)
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::AwaitingConfirmation])
                ->update(['status' => PaymentStatus::Rejected, 'note' => 'Superseded by confirmed payment '.$payment->reference]);

            $this->funds->receive($investment->amount, $investment, $investment->reference);

            activity()->performedOn($investment)->causedBy($by)
                ->withProperties(['payment' => $payment->reference, 'method' => $payment->method->value])
                ->log('Payment confirmed; investment activated');

            $investment->user->notify(new PaymentConfirmed($investment));

            return $payment;
        });
    }

    public function reject(Payment $payment, User $by, string $note): void
    {
        if ($payment->status !== PaymentStatus::AwaitingConfirmation && $payment->status !== PaymentStatus::Pending) {
            throw new BusinessRuleException('Only open payments can be rejected.');
        }

        $payment->update(['status' => PaymentStatus::Rejected, 'note' => $note, 'confirmed_by' => $by->id, 'confirmed_at' => now()]);
        activity()->performedOn($payment->investment)->causedBy($by)->withProperties(['note' => $note])->log('Payment rejected');
    }

    private function assertPayable(Investment $investment): void
    {
        if ($investment->status !== InvestmentStatus::PendingPayment) {
            throw new BusinessRuleException('This investment is not awaiting payment.');
        }
    }

    private function newReference(): string
    {
        return 'PAY-'.now()->format('ymd').'-'.strtoupper(Str::random(8));
    }
}
