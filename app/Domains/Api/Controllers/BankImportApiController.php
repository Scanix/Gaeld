<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Exceptions\ApiIdempotencyConflictException;
use App\Domains\Api\Requests\ImportCamtApiRequest;
use App\Domains\Api\Resources\BankImportResource;
use App\Domains\Api\Services\ApiIdempotencyService;
use App\Domains\Api\Support\ApiIdempotencyReservation;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\BankImportService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BankImportApiController extends Controller
{
    public function store(
        ImportCamtApiRequest $request,
        BankAccount $bankAccount,
        CurrentOrganization $currentOrganization,
        BankImportService $importService,
        ApiIdempotencyService $idempotency,
    ): BankImportResource|JsonResponse {
        abort_unless($bankAccount->organization_id === $currentOrganization->id(), 404);
        $this->authorize('import', $bankAccount);

        $file = $request->file('camt_file');
        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            return response()->json([
                'message' => 'Could not read the uploaded file.',
                'code' => 'bank_import_unreadable',
            ], 422);
        }

        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file->getClientOriginalName())) ?: 'camt.xml';
        $fallback = hash('sha256', $content);
        $reservation = null;

        try {
            $reservation = $idempotency->reserve($request, $currentOrganization->id(), $fallback);
            if ($reservation === null) {
                return response()->json([
                    'message' => 'An Idempotency-Key or file reference is required for imports.',
                    'code' => 'idempotency_key_required',
                ], 422);
            }

            if ($reservation->replay) {
                return $idempotency->replay($reservation);
            }

            $import = $importService->importCamtFile($bankAccount, $content, $filename);
            $response = (new BankImportResource($import->load('bankAccount')))
                ->response()
                ->setStatusCode(201);
            $idempotency->complete($reservation, $response);

            return $response;
        } catch (\InvalidArgumentException $exception) {
            $this->releaseReservation($reservation, $idempotency);

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'bank_import_invalid',
            ], 422);
        } catch (\DomainException $exception) {
            $this->releaseReservation($reservation, $idempotency);

            $code = $exception instanceof ApiIdempotencyConflictException
                ? 'idempotency_conflict'
                : 'bank_import_failed';

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $code,
            ], $code === 'idempotency_conflict' ? 409 : 422);
        }
    }

    private function releaseReservation(
        ?ApiIdempotencyReservation $reservation,
        ApiIdempotencyService $idempotency,
    ): void {
        if ($reservation !== null && ! $reservation->replay) {
            $idempotency->release($reservation);
        }
    }
}
