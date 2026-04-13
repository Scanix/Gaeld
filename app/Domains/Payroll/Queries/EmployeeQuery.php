<?php

namespace App\Domains\Payroll\Queries;

use App\Domains\Payroll\Models\Employee;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EmployeeQuery
{
    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    public static function list(Request $request, int $perPage = 25): LengthAwarePaginator
    {
        return QueryBuilder::for(Employee::query(), $request)
            ->allowedSorts(['last_name', 'first_name', 'email', 'entry_date', 'created_at'], 'last_name', 'asc')
            ->allowedFilters(['is_active'])
            ->searchable(['first_name', 'last_name', 'email', 'ahv_number'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }
}
