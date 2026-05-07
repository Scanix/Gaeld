<?php

namespace App\Domains\Banking\Controllers;

use App\Domains\Banking\Services\SwissBicResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resolves a SWIFT/BIC from a Swiss/Liechtenstein IBAN using the
 * embedded SIX IID lookup table. Used by the bank-account and
 * supplier-contact forms to auto-fill the BIC field.
 */
class BicLookupController extends Controller
{
    public function __construct(private readonly SwissBicResolver $resolver) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'iban' => ['required', 'string', 'max:34'],
        ]);

        $bic = $this->resolver->resolveFromIban($data['iban']);

        return response()->json([
            'bic' => $bic,
        ]);
    }
}
