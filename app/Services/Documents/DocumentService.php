<?php

namespace App\Services\Documents;

use App\Models\Investment;
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
        $investment->loadMissing(['user', 'plan']);

        return Pdf::loadView('pdf.certificate', [
            'investment' => $investment,
            'verifyUrl' => $this->verifyUrl($investment),
            'qr' => $this->qrDataUri($this->verifyUrl($investment)),
            'logo' => $this->logoDataUri(),
        ])->setPaper('a4', 'landscape')->output();
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
