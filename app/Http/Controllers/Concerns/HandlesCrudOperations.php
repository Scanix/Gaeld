<?php

namespace App\Http\Controllers\Concerns;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared CRUD logic for simple resource controllers.
 *
 * Implement the abstract config methods and the trait provides
 * index / create / store / show / edit / update / destroy.
 */
trait HandlesCrudOperations
{
    abstract protected function modelClass(): string;

    abstract protected function createDtoClass(): string;

    abstract protected function updateDtoClass(): string;

    abstract protected function queryClass(): string;

    abstract protected function storeRequestClass(): string;

    abstract protected function inertiaPrefix(): string;

    abstract protected function routePrefix(): string;

    abstract protected function resourceName(): string;

    /** @return array<int, string> */
    abstract protected function showRelations(): array;

    protected function indexDefaultSort(): string
    {
        return 'name';
    }

    protected function indexDefaultDirection(): string
    {
        return 'asc';
    }

    /** @return array<string, mixed> */
    protected function createFormProps(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function editFormProps(mixed $model): array
    {
        return [];
    }

    protected function resolveModel(): mixed
    {
        $key = Str::singular($this->routePrefix());

        return $this->modelClass()::findOrFail(request()->route($key));
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', $this->modelClass());

        $queryClass = $this->queryClass();
        $plural = Str::plural($this->resourceName());

        return Inertia::render($this->inertiaPrefix().'/Index', [
            $plural => $queryClass::list($request),
            'query' => [
                'sort' => $request->input('sort', $this->indexDefaultSort()),
                'direction' => $request->input('direction', $this->indexDefaultDirection()),
                'search' => $request->input('search', ''),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', $this->modelClass());

        return Inertia::render($this->inertiaPrefix().'/Create', $this->createFormProps());
    }

    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse|JsonResponse
    {
        $this->authorize('create', $this->modelClass());

        $formRequest = app($this->storeRequestClass());
        $validated = $formRequest->validated();
        $validated['organization_id'] = $currentOrg->id();

        $createDto = $this->createDtoClass();
        $model = $this->modelClass()::create($createDto::fromArray($validated)->toArray());

        if ($request->wantsJson()) {
            return response()->json([$this->resourceName() => $model], 201);
        }

        return redirect()->route($this->routePrefix().'.show', $model)
            ->with('success', __('app.'.$this->resourceName().'_created'));
    }

    public function show(): Response
    {
        $model = $this->resolveModel();
        $this->authorize('view', $model);

        return Inertia::render($this->inertiaPrefix().'/Show', [
            $this->resourceName() => $model->load($this->showRelations()),
        ]);
    }

    public function edit(): Response
    {
        $model = $this->resolveModel();
        $this->authorize('update', $model);

        return Inertia::render($this->inertiaPrefix().'/Edit', array_merge(
            [$this->resourceName() => $model],
            $this->editFormProps($model),
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $model = $this->resolveModel();
        $this->authorize('update', $model);

        $formRequest = app($this->storeRequestClass());
        $validated = $formRequest->validated();

        $updateDto = $this->updateDtoClass();
        $model->update($updateDto::fromArray($validated)->toArray());

        return redirect()->route($this->routePrefix().'.show', $model)
            ->with('success', __('app.'.$this->resourceName().'_updated'));
    }

    public function destroy(): RedirectResponse
    {
        $model = $this->resolveModel();
        $this->authorize('delete', $model);

        $model->delete();

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', __('app.'.$this->resourceName().'_deleted'));
    }
}
