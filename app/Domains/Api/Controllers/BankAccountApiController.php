<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Resources\BankAccountResource;
use App\Domains\Banking\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Controllers\Controller;

class BankAccountApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BankAccount::class);

        $bankAccounts = BankAccount::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate($request->input('per_page', 25));

        return BankAccountResource::collection($bankAccounts);
    }

    public function show(BankAccount $bankAccount): BankAccountResource
    {
        $this->authorize('view', $bankAccount);

        return new BankAccountResource($bankAccount);
    }
}
