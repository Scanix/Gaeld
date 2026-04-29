<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Invoicing\Actions\GenerateQrInvoicePdfAction;
use App\Domains\Invoicing\Exceptions\QrBillValidationException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Support\QrBillValidationMessageFormatter;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use App\Support\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invoice document operations: PDF generation and justificatif management.
 */
class InvoiceDocumentController extends Controller
{
    use HandlesFlashErrorResponses;

    public function __construct(
        private FileUploadService $uploadService,
    ) {}

    public function removeJustificatif(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->justificatif_path) {
            $this->uploadService->delete($invoice->justificatif_path);
            $invoice->update(['justificatif_path' => null]);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.justificatif_removed'));
    }

    public function downloadJustificatif(Invoice $invoice, Request $request): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $invoice->justificatif_path || ! Storage::disk('local')->exists($invoice->justificatif_path)) {
            abort(404);
        }

        $filename = basename($invoice->justificatif_path);

        if ($request->boolean('inline')) {
            return Storage::disk('local')->response(
                $invoice->justificatif_path,
                $filename,
            );
        }

        return Storage::disk('local')->download(
            $invoice->justificatif_path,
            $filename,
        );
    }

    public function downloadQrPdf(
        Invoice $invoice,
        GenerateQrInvoicePdfAction $action,
        CurrentOrganization $currentOrg,
        QrBillValidationMessageFormatter $messageFormatter,
    ): HttpResponse|RedirectResponse {
        $this->authorize('view', $invoice);

        $organization = $currentOrg->get();
        $locale = $organization->locale ?? app()->getLocale();

        try {
            $pdf = $action->execute($invoice, $organization, $locale);
        } catch (QrBillValidationException $e) {
            return $this->backWithError($messageFormatter->format($e->violations));
        }

        $filename = 'invoice-'.($invoice->number ?? $invoice->id).'.pdf';

        return new HttpResponse($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
