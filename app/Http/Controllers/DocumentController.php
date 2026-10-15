<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Services\Documents\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** PDF downloads for a policy: the owner, or staff holding certificates.issue. */
class DocumentController extends Controller
{
    public function certificate(Request $request, Investment $investment, DocumentService $documents): Response
    {
        $this->authorizeAccess($request, $investment);
        abort_unless($investment->hasCertificate(), 404);

        return $this->pdf($documents->certificate($investment), "certificate-{$investment->certificate_number}.pdf");
    }

    public function statement(Request $request, Investment $investment, DocumentService $documents): Response
    {
        $this->authorizeAccess($request, $investment);

        return $this->pdf($documents->statement($investment), "statement-{$investment->reference}.pdf");
    }

    private function authorizeAccess(Request $request, Investment $investment): void
    {
        $user = $request->user();
        abort_unless($investment->user_id === $user->id || $user->can('certificates.issue'), 404);
    }

    private function pdf(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
