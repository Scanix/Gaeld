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
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use App\Support\Contracts\OrganizationQuotaResolver;
use App\Support\Services\FileUploadService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        $organization = $currentOrg->get();
        $connection = $organization->getConnection();
        $validated = $request->validated();
        $validated['organization_id'] = $organization->id;

        $invoice = null;
        try {
            $connection->transaction(function () use ($request, $action, $numberGenerator, $quotaResolver, $organization, $connection, $validated, &$invoice): void {
                $monthlyKey = 'invoices_monthly:'.$organization->id.':'.now()->format('Y-m');
                $limit = $quotaResolver->maxInvoicesPerMonth($organization);
                if ($limit !== -1) {
                    Cache::add($monthlyKey, 0, now()->startOfMonth()->addMonth());
                    if (Cache::increment($monthlyKey) > $limit) {
                        Cache::decrement($monthlyKey);

                        return;
                    }
                    $connection->afterRollBack(static fn () => Cache::decrement($monthlyKey));
                }

                $path = null;
                if ($request->hasFile('justificatif')) {
                    $path = $this->storeSupportingDocument($organization, $request->file('justificatif'));
                }

                $forYear = Carbon::parse($validated['issue_date'])->year;
                for ($attempt = 1; ; $attempt++) {
                    $validated['number'] = $numberGenerator->next($organization->id, null, $forYear);
                    try {
                        $invoice = $action->execute(CreateInvoiceData::fromArray($validated), $path);

                        return;
                    } catch (QueryException $exception) {
                        $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();
                        if ($attempt === 3 || $sqlState !== '23505' || ! str_contains($exception->getMessage(), 'invoices_organization_id_number_unique')) {
                            throw $exception;
                        }
                    }
                }
            });
        } catch (Throwable $exception) {
            // A post-commit listener can fail after the customer already owns this invoice.
            if ($invoice === null || ! Invoice::withoutGlobalScopes()->useWritePdo()->where('organization_id', $organization->id)->whereKey($invoice->id)->exists()) {
                throw $exception;
            }
            report($exception);
        }

        if ($invoice === null) {
            return $this->backWithError(__('app.invoice_monthly_limit_reached'));
        }
        if ($request->boolean('finalize')) {
            $finalizeAction->execute($invoice);
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
            'canUpdatePayment' => $request->user()->can('updatePayment', $invoice),
            'canSend' => $request->user()->can('send', $invoice),
            'canSendReminder' => $request->user()->can('sendReminder', $invoice),
            'canRefreshCustomerSnapshot' => $request->user()->can('refreshCustomerSnapshot', $invoice),
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
        $dto = UpdateInvoiceData::fromArray($validated);
        try {
            if ($request->hasFile('justificatif')) {
                $this->replaceSupportingDocument($invoice, $dto, $request->file('justificatif'), $action);
            } else {
                $action->execute($invoice, $dto);
            }
        } catch (InvalidInvoiceStateException $exception) {
            return $this->backWithError($exception);
        } catch (QueryException $exception) {
            $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();
            if ($sqlState !== '23505' || ! str_contains($exception->getMessage(), 'invoices_organization_id_number_unique')) {
                throw $exception;
            }

            return back()
                ->withErrors(['number' => __('validation.unique', ['attribute' => 'number'])])
                ->withInput();
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.invoice_updated'));
    }

    private function replaceSupportingDocument(Invoice $invoice, UpdateInvoiceData $data, UploadedFile $document, UpdateInvoiceAction $action): void
    {
        $organization = $invoice->organization;
        $connection = $invoice->getConnection();
        $previous = null;
        $path = null;
        try {
            $connection->transaction(function () use ($invoice, $data, $document, $action, $organization, &$previous, &$path): void {
                $path = $this->storeSupportingDocument($organization, $document);
                $previous = $action->replaceSupportingDocument($invoice, $data, $path);
            });
        } catch (Throwable $exception) {
            if ($path === null || ! Invoice::withoutGlobalScopes()->useWritePdo()->where('organization_id', $organization->id)->whereKey($invoice->id)->where('justificatif_path', $path)->exists()) {
                throw $exception;
            }
            report($exception);
        }

        if ($previous !== null) {
            $connection->afterCommit(fn () => $this->documentStorage->delete($organization, $previous));
        }
    }

    private function storeSupportingDocument(Organization $organization, UploadedFile $document): string
    {
        $this->documentStorage->reserve($organization, (int) $document->getSize());
        $path = $this->uploadService->store($document, "justificatifs/{$organization->id}");
        $organization->getConnection()->afterRollBack(fn () => $this->uploadService->delete($path));

        return $path;
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
