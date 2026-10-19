<?php

namespace App\Jobs;

use App\Models\Investment;
use App\Notifications\InvestmentDocumentsIssued;
use App\Services\Policy\PolicyDocumentService;
use App\Services\Policy\ReceiptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issues the investment receipt and agreement once a payment is confirmed, and emails them together. Runs after the payment's database
 * transaction has committed, off the web request, so a PDF problem can never undo or delay a confirmed payment.
 * If it keeps failing it appears under failed jobs, and staff can still press "Generate" on the investor's page.
 */
class IssuePolicyDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public int $investmentId) {}

    public function handle(PolicyDocumentService $documents, ReceiptService $receipts): void
    {
        $investment = Investment::with('user')->findOrFail($this->investmentId);
        $already = $investment->currentPolicyDocument() !== null && $investment->currentReceipt() !== null;

        $receipt = $receipts->issueForInvestment($investment, null, 'Issued on payment confirmation');
        $document = $documents->issue($investment);

        if (! $already) { // a retry that finds both already there must not email them twice
            $investment->user->notify(new InvestmentDocumentsIssued($receipt, $document));
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error("Policy document for investment {$this->investmentId} could not be issued: ".$e->getMessage());
    }
}
