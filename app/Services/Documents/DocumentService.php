<?php

namespace App\Services\Documents;

use App\Models\CompanyStamp;
use App\Models\Investment;
use App\Models\Signatory;
use App\Support\Money;
use App\Support\NumberToWords;
use App\Support\PdfArt;
use App\Services\Performance\PerformanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/** Renders the investment certificate and policy statement PDFs. */
class DocumentService
{
    public function __construct(private PerformanceService $performance) {}

    public function certificate(Investment $investment): string
    {
        return Pdf::loadView('pdf.certificate', $this->certificateContext($investment))->setPaper('a4', 'landscape')->output();
    }

    /**
     * What the certificate shows. A certificate is drawn fresh on every download, so it signs and stamps with whatever
     * the investor's own agreement and receipt were issued with (not whoever is active today), and only falls back to
     * the active ones when those documents do not exist yet.
     *
     * @return array<string, mixed>
     */
    public function certificateContext(Investment $investment): array
    {
        $investment->loadMissing(['user.investorProfile', 'plan']);
        $policy = $investment->currentPolicyDocument();
        $receipt = $investment->currentReceipt();
        $title = $investment->user->investorProfile?->title;

        return [
            'investment' => $investment,
            'investor_name' => trim(($title ? $title.' ' : '').$investment->user->name),
            'amount' => Money::format($investment->amount),
            'amount_words' => NumberToWords::naira($investment->amount),
            'policy_number' => $investment->policy_number,
            'receipt_number' => $receipt?->receipt_number,
            'received_on' => $receipt?->received_on?->format('jS F, Y'),
            'frequency' => $investment->profit_frequency?->label(),
            'verifyUrl' => $this->verifyUrl($investment),
            'qr' => $this->qrDataUri($this->verifyUrl($investment)),
            'assets' => PdfArt::assets(),
            'signatory' => PdfArt::stored(($policy?->signatory ?? Signatory::active())?->signature_path),
            'stamp' => PdfArt::stored(($receipt?->stamp ?? CompanyStamp::active())?->image_path),
            'company' => config('afisab.company_name'),
            'slogan' => config('afisab.slogan'),
        ];
    }

    public function statement(Investment $investment): string
    {
        $investment->loadMissing(['user', 'plan', 'payments']);

        return Pdf::loadView('pdf.statement', [
            'investment' => $investment,
            'position' => $this->performance->position($investment),
            'entries' => $investment->performanceEntries()->with('run')->get(),
            'generatedAt' => now(),
            'logo' => $this->logoDataUri(),
        ])->setPaper('a4')->output();
    }

    public function verifyUrl(Investment $investment): string
    {
        return route('verify.show', $investment->certificate_token);
    }

    /**
     * The company logo, embedded in the PDF itself. Embedding (rather than linking the file) keeps PDFs working
     * wherever the web root sits, including the cPanel layout where it is outside the application folder.
     * Returns '' if the file is missing, and the templates then fall back to the company name in text.
     */
    private function logoDataUri(): string
    {
        $path = public_path('image/logo-full.jpg');

        return is_file($path) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path)) : '';
    }

    private function qrDataUri(string $data): string
    {
        return (new Builder(writer: new PngWriter, data: $data, size: 220, margin: 2))->build()->getDataUri();
    }
}
