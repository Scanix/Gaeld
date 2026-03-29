<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Displays the organization-scoped activity / audit log.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $orgId = $currentOrg->id();

        $query = Activity::query()
            ->where(function ($q) use ($orgId) {
                $q->where('properties->organization_id', $orgId)
                    ->orWhere(function ($q2) use ($orgId) {
                        $q2->where('subject_type', 'App\\Domains\\Organizations\\Models\\Organization')
                            ->where('subject_id', $orgId);
                    });
            })
            ->with('causer:id,name,email')
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->input('causer_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%");
            });
        }

        $activities = $query->paginate(25)->withQueryString();

        // Get distinct subject types for filter dropdown
        $subjectTypes = Activity::query()
            ->where('properties->organization_id', $orgId)
            ->select('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn ($type) => [
                'value' => $type,
                'label' => class_basename($type),
            ])
            ->values();

        return Inertia::render('Organizations/ActivityLog', [
            'activities' => $activities,
            'subjectTypes' => $subjectTypes,
            'filters' => $request->only(['subject_type', 'event', 'causer_id', 'search']),
        ]);
    }
}
