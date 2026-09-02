<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Accounting\Queries\VatRateQuery;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Queries\ContactQuery;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\DeleteInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Actions\UpdateInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Queries\InvoiceQuery;
use App\Domains\Invoicing\Requests\StoreInvoiceRequest;
use App\Domains\Invoicing\Requests\UpdateInvoiceRequest;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use App\Support\Contracts\OrganizationQuotaResolver;
use App\Support\Services\FileUploadService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Invoice CRUD: creating, listing, viewing, editing and deleting invoices.
 */
class InvoiceController extends Controller
{
    use HandlesFlashErrorResponses;

    public function __construct(
        private FileUploadService $uploadService,
        private OrganizationDocumentStorageService $documentStorage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        return Inertia::render('Invoices/Index', [
            'invoices' => InvoiceQuery::list($request),
            'query' => [
                'sort' => $request->input('sort', 'issue_date'),
                'direction' => $request->input('direction', 'desc'),
                'search' => $request->input('search', ''),
                'filter' => $request->input('filter', ['type' => 'invoice']),
            ],
        ]);
    }

    public function create(Request $request, InvoiceNumberGenerator $numberGenerator, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('create', Invoice::class);

        // Allow the front-end to request a number scoped to a specific issue-date year
        // (e.g. when the user back-dates an invoice into a previous fiscal year).
        $forYear = null;
        if ($request->filled('for_year')) {
            $forYear = $request->integer('for_year');
        } elseif ($request->filled('issue_date')) {
            try {
                $forYear = Carbon::parse((string) $request->input('issue_date'))->year;
            } catch (Throwable) {
                $forYear = null;
            }
        }

        return Inertia::render('Invoices/Create', [
            'customers' => ContactQuery::forSelect(),
            'vatRates' => VatRateQuery::active(),
            'suggestedNumber' => $numberGenerator->next($currentOrg->id(), null, $forYear),
            'defaultNotes' => $currentOrg->get()->default_invoice_notes ?? '',
            'defaultPaymentTermsDays' => $currentOrg->get()->default_payment_terms_days,
            'defaultVatRateId' => optional(VatRateQuery::active()->firstWhere('is_default', true))->id,
            'taxTreatments' => InvoiceTaxTreatment::options(),
        ]);
    }

    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action, CurrentOrganization $currentOrg, FinalizeInvoiceAction $finalizeAction, InvoiceNumberGenerator $numberGenerator, OrganizationQuotaResolver $quotaResolver): RedirectResponse
    {
        $orgId = $currentOrg->id();
        $monthlyKey = 'invoices_monthly:'.$orgId.':'.now()->format('Y-m');
        $limit = $quotaResolver->maxInvoicesPerMonth($currentOrg->get());
        $invoiceQuotaReserved = false;

        if ($limit !== -1) {
            Cache::add($monthlyKey, 0, now()->startOfMonth()->addMonth());
            $newCount = Cache::increment($monthlyKey);
            if ($newCount > $limit) {
                Cache::decrement($monthlyKey);

                return $this->backWithError(__('app.invoice_monthly_limit_reached'));
            }

            $invoiceQuotaReserved = true;
        }

        $validated = $request->validated();
        $validated['organization_id'] = $orgId;
        $newJustificatifPath = null;

        if ($request->hasFile('justificatif')) {
            $organization = $currentOrg->get();
            $document = $request->file('justificatif');
            $documentBytes = (int) $document->getSize();
            $this->documentStorage->reserve($organization, $documentBytes);

            try {
                $newJustificatifPath = $this->uploadService->store($document, "justificatifs/{$currentOrg->id()}");
                $validated['justificatif_path'] = $newJustificatifPath;
            } catch (Throwable $exception) {
                $this->documentStorage->release($organization, $documentBytes);

                throw $exception;
            }
        }

        // Auto-generate the invoice number, retrying up to 3 times on a unique
        // constraint race condition (two concurrent requests picking the same sequence).
        $invoice = null;
        try {
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $forYear = null;
                if (! empty($validated['issue_date'])) {
                    try {
                        $forYear = Carbon::parse($validated['issue_date'])->year;
                    } catch (Throwable) {
                    }
                }
                $validated['number'] = $numberGenerator->next($orgId, null, $forYear);

                try {
                    $dto = CreateInvoiceData::fromArray($validated);
                    $invoice = $action->execute($dto);
                    break;
                } catch (QueryException $e) {
                    $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();
                    $isNumberConflict = $sqlState === '23505'
                        && str_contains($e->getMessage(), 'invoices_organization_id_number_unique');

                    if (! $isNumberConflict || $attempt === 3) {
                        throw $e;
                    }
                }
            }

            if ($request->boolean('finalize')) {
                $finalizeAction->execute($invoice);
            }
        } catch (Throwable $exception) {
            if ($invoice === null) {
                if ($newJustificatifPath) {
                    $this->documentStorage->delete($currentOrg->get(), $newJustificatifPath);
                }

                if ($invoiceQuotaReserved) {
                    Cache::decrement($monthlyKey);
                }
            }

            throw $exception;
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.invoice_created'));
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $defaultBankAccount = $invoice->organization->defaultInvoicingBankAccount();

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice->load(['customer', 'lines.vatRate', 'journalEntry.lines.account', 'payments.journalEntry']),
            'canForceDelete' => $request->user()->can('forceDelete', $invoice),
            'canRecordPayment' => $request->user()->can('recordPayment', $invoice),
            'canSend' => $request->user()->can('send', $invoice),
            'justificatifUrl' => $invoice->justificatif_path
                ? route('invoices.justificatif.download', $invoice)
                : null,
            'hasQrIban' => ! empty($invoice->qr_iban ?: $defaultBankAccount?->qr_iban ?: $defaultBankAccount?->iban),
            'bankAccounts' => BankAccount::query()
                ->where('is_active', true)
                ->select('id', 'account_id', 'name', 'iban', 'currency')
                ->with('ledgerAccount:id,code')
                ->orderBy('name')
                ->get(),
            'creditNotes' => $invoice->creditNotes()
                ->select('id', 'number', 'total')
                ->get(),
            'relatedInvoice' => $invoice->relatedInvoice
                ? $invoice->relatedInvoice->only('id', 'number')
                : null,
            'reminderCount' => $invoice->reminder_count ?? 0,
            'lastRemindedAt' => $invoice->last_reminded_at?->toISOString(),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice->load('lines.vatRate'),
            'customers' => ContactQuery::forSelect(),
            'vatRates' => VatRateQuery::active(),
            'justificatifUrl' => $invoice->justificatif_path
                ? route('invoices.justificatif.download', $invoice)
                : null,
            'defaultVatRateId' => optional(VatRateQuery::active()->firstWhere('is_default', true))->id,
            'taxTreatments' => InvoiceTaxTreatment::options(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateInvoiceAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $validated['organization_id'] = $invoice->organization_id;
        $oldJustificatifPath = null;
        $newJustificatifPath = null;

        if ($request->hasFile('justificatif')) {
            $organization = $invoice->organization;
            $document = $request->file('justificatif');
            $documentBytes = (int) $document->getSize();
            $this->documentStorage->reserve($organization, $documentBytes);

            try {
                $newJustificatifPath = $this->uploadService->store($document, "justificatifs/{$invoice->organization_id}");
                $validated['justificatif_path'] = $newJustificatifPath;
                $oldJustificatifPath = $invoice->justificatif_path;
            } catch (Throwable $exception) {
                $this->documentStorage->release($organization, $documentBytes);

                throw $exception;
            }
        }

        $dto = UpdateInvoiceData::fromArray($validated);

        try {
            $action->execute($invoice, $dto);
        } catch (InvalidInvoiceStateException $e) {
            if ($newJustificatifPath) {
                $this->documentStorage->delete($invoice->organization, $newJustificatifPath);
            }

            return $this->backWithError($e);
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();
            $isNumberConflict = $sqlState === '23505'
                && str_contains($e->getMessage(), 'invoices_organization_id_number_unique');

            if (! $isNumberConflict) {
                if ($newJustificatifPath) {
                    $this->documentStorage->delete($invoice->organization, $newJustificatifPath);
                }

                throw $e;
            }

            if ($newJustificatifPath) {
                $this->documentStorage->delete($invoice->organization, $newJustificatifPath);
            }

            return back()
                ->withErrors(['number' => __('validation.unique', ['attribute' => 'number'])])
                ->withInput();
        } catch (Throwable $exception) {
            if ($newJustificatifPath) {
                $this->documentStorage->delete($invoice->organization, $newJustificatifPath);
            }

            throw $exception;
        }

        if ($oldJustificatifPath) {
            $this->documentStorage->delete($invoice->organization, $oldJustificatifPath);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.invoice_updated'));
    }

    public function destroy(Invoice $invoice, DeleteInvoiceAction $action): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvalidInvoiceStateException $e) {
            return $this->backWithError($e);
        }

        return redirect()->route('invoices.index')
            ->with('success', __('app.invoice_deleted'));
    }
}
