<?php

namespace App\Domains\Payroll\Controllers;

use App\Domains\Payroll\Actions\GenerateSalaryCertificateAction;
use App\Domains\Payroll\Models\Employee;
use App\Http\Controllers\Controller;
use App\Support\PdfExportService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class SalaryCertificateController extends Controller
{
    public function download(
        Employee $employee,
        int $year,
        GenerateSalaryCertificateAction $action,
        PdfExportService $pdf,
    ): HttpResponse {
        $this->authorize('view', $employee);

        $certificate = $action->execute($employee, $year);

        return $pdf->download(
            'exports.salary-certificate',
            ['certificate' => $certificate],
            "salary-certificate-{$employee->last_name}-{$year}.pdf",
        );
    }
}
