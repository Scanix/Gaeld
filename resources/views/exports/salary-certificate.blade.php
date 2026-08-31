<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.salary_certificate.title') }} — {{ $certificate['employee']->fullName() }}</title>
    @include('exports._styles')
</head>
<body>
    @include('exports._header', [
        'docTitle' => __('exports.salary_certificate.title'),
        'docPeriod' => (string) $certificate['year'],
        'docRef' => $certificate['employee']->fullName(),
    ])

    <div class="notice">{{ __('exports.salary_certificate.disclaimer') }}</div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_certificate.employee') }}</div>
        <table>
            <tr><td style="width:30%;">{{ __('exports.salary_certificate.name') }}</td><td>{{ $certificate['employee']->fullName() }}</td></tr>
            @if($certificate['employee']->ahv_number)
                <tr><td>{{ __('exports.salary_certificate.ahv_number') }}</td><td>{{ $certificate['employee']->ahv_number }}</td></tr>
            @endif
            <tr><td>{{ __('exports.salary_certificate.months_covered') }}</td><td>{{ $certificate['months_covered'] }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_certificate.employee_totals') }}</div>
        <table>
            <tr><td>{{ __('exports.salary_certificate.gross_salary') }}</td><td class="right">{{ number_format((float) $certificate['gross_salary'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.avs_employee') }}</td><td class="right">-{{ number_format((float) $certificate['avs_employee'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.ac_employee') }}</td><td class="right">-{{ number_format((float) $certificate['ac_employee'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.aanp_employee') }}</td><td class="right">-{{ number_format((float) $certificate['aanp_employee'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.lpp_employee') }}</td><td class="right">-{{ number_format((float) $certificate['lpp_employee'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.source_tax') }}</td><td class="right">-{{ number_format((float) $certificate['source_tax'], 2, '.', "'") }}</td></tr>
            <tr class="total-row"><td>{{ __('exports.salary_certificate.net_salary') }}</td><td class="right">{{ number_format((float) $certificate['net_salary'], 2, '.', "'") }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_certificate.additional_payments') }}</div>
        <table>
            <tr><td>{{ __('exports.salary_certificate.reimbursements') }}</td><td class="right">{{ number_format((float) $certificate['reimbursements'], 2, '.', "'") }}</td></tr>
            <tr class="net-row"><td>{{ __('exports.salary_certificate.total_paid') }}</td><td class="right">{{ number_format((float) $certificate['total_paid'], 2, '.', "'") }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_certificate.employer_charges') }}</div>
        <table>
            <tr><td>{{ __('exports.salary_certificate.total_employer_charges') }}</td><td class="right">{{ number_format((float) $certificate['employer_charges'], 2, '.', "'") }}</td></tr>
        </table>
    </div>

    @include('exports._footer')
</body>
</html>