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
        ])->setPaper('a4')->output();
    }

    public function verifyUrl(Investment $investment): string
    {
        return route('verify.show', $investment->certificate_token);
    }

    private function qrDataUri(string $data): string
    {
        return (new Builder(writer: new PngWriter, data: $data, size: 220, margin: 2))->build()->getDataUri();
    }
}
