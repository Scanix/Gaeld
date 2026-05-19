<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.salary_slip.title') }} — {{ $slip->employee->fullName() }}</title>
    @include('exports._styles')
    <style>
        /* Swiss letter layout (SN 010130 / DIN 5008) */
        .letter-sender { font-size: 8.5pt; line-height: 1.4; color: #555; padding-bottom: 2mm; border-bottom: 1px solid #999; width: 85mm; }
        .letter-sender .sender-name { color: #111; font-weight: bold; }

        /* Recipient block — placed at the window-envelope position (right side, ~50mm from page top) */
        .letter-recipient { position: absolute; top: 38mm; right: 0; width: 75mm; font-size: 10pt; line-height: 1.45; }
        .letter-recipient .recipient-label { font-size: 7.5pt; color: #888; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 1.5mm; }
        .letter-recipient .recipient-name { font-size: 11pt; font-weight: bold; }
        .letter-recipient .recipient-detail { font-size: 9pt; color: #555; }

        /* Place + date — left aligned, between address block and document title */
        .letter-place-date { margin-top: 35mm; font-size: 10pt; color: #333; }

        /* Document title (Lohnausweis / Salary slip) */
        .letter-title { margin-top: 8mm; padding-bottom: 4mm; border-bottom: 2px solid #111; margin-bottom: 7mm; }
        .letter-title .doc-type-label { font-size: 16pt; font-weight: bold; }
        .letter-title .doc-period-label { font-size: 11pt; color: #555; margin-top: 1mm; }

        /* Net salary highlight */
        tr.row-net td { font-size: 12pt; font-weight: bold; border-top: 3px double #111; border-bottom: none; padding-top: 6px; }
    </style>
</head>
<body>
    @php $deductions = $slip->deductions; @endphp

    {{-- Swiss SN 010130 letter layout: sender top-left, recipient on right (window position) --}}
    @php
        $logoFullPath = $organization->logo_path ? storage_path('app/'.$organization->logo_path) : null;
    @endphp
    @if($logoFullPath && file_exists($logoFullPath))
        <div class="doc-logo"><img src="{{ $logoFullPath }}" alt="Logo"></div>
    @else
        <div class="doc-logo-placeholder">Logo</div>
    @endif

    <div class="letter-sender">
        <span class="sender-name">{{ $organization->legal_name ?? $organization->name }}</span>
        @if($organization->address)
            &nbsp;·&nbsp;{{ $organization->address }}
        @endif
        @if($organization->postal_code || $organization->city)
            &nbsp;·&nbsp;{{ implode(' ', array_filter([$organization->postal_code ?? null, $organization->city ?? null])) }}
        @endif
    </div>

    <div class="letter-recipient">
        <div class="recipient-label">{{ __('exports.salary_slip.employee') }}</div>
        <div class="recipient-name">{{ $slip->employee->fullName() }}</div>
        @if($slip->employee->ahv_number)
            <div class="recipient-detail">{{ __('exports.salary_slip.ahv_number') }}: {{ $slip->employee->ahv_number }}</div>
        @endif
    </div>

    <div class="letter-place-date">
        {{ $organization->city ?? '' }}{{ $organization->city ? ',' : '' }} {{ \Carbon\Carbon::now()->format('d.m.Y') }}
    </div>

    <div class="letter-title">
        <div class="doc-type-label">{{ __('exports.salary_slip.title') }}</div>
        <div class="doc-period-label">{{ \Carbon\Carbon::create($slip->period_year, $slip->period_month, 1)->locale(app()->getLocale())->isoFormat('MMMM YYYY') }}</div>
    </div>

    {{-- Gross salary --}}
    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.salary') }}</div>
        <table>
            <tbody>
                <tr>
                    <td>{{ __('exports.salary_slip.gross_salary') }}</td>
                    <td class="r">{{ number_format((float) $slip->gross_salary, 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Employee deductions --}}
    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.employee_deductions') }}</div>
        <table>
            <tbody>
                @if(isset($deductions['avs_employee']) && bccomp((string) $deductions['avs_employee'], '0', 2) > 0)
                    <tr>
                        <td>{{ __('exports.salary_slip.avs_ai_apg') }}</td>
                        <td class="r">−{{ number_format((float) $deductions['avs_employee'], 2, '.', "'") }}</td>
                    </tr>
                @endif
                @if(isset($deductions['ac_employee']) && bccomp((string) $deductions['ac_employee'], '0', 2) > 0)
                    <tr>
                        <td>{{ __('exports.salary_slip.unemployment_insurance') }}</td>
                        <td class="r">−{{ number_format((float) $deductions['ac_employee'], 2, '.', "'") }}</td>
                    </tr>
                @endif
                @if(isset($deductions['aanp_employee']) && bccomp((string) $deductions['aanp_employee'], '0', 2) > 0)
                    <tr>
                        <td>{{ __('exports.salary_slip.aanp') }}</td>
                        <td class="r">−{{ number_format((float) $deductions['aanp_employee'], 2, '.', "'") }}</td>
                    </tr>
                @endif
                @if(isset($deductions['lpp_employee']) && bccomp((string) $deductions['lpp_employee'], '0', 2) > 0)
                    <tr>
                        <td>{{ __('exports.salary_slip.pension_lpp') }}</td>
                        <td class="r">−{{ number_format((float) $deductions['lpp_employee'], 2, '.', "'") }}</td>
                    </tr>
                @endif
                <tr class="row-total">
                    <td>{{ __('exports.salary_slip.total_deductions') }}</td>
                    <td class="r">−{{ number_format((float) ($deductions['total_employee'] ?? '0'), 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Net salary --}}
    <div class="section">
        <table>
            <tbody>
                <tr class="row-net">
                    <td>{{ __('exports.salary_slip.net_salary') }}</td>
                    <td class="r">{{ number_format((float) $slip->net_salary, 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Employer charges (informational) --}}
    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.employer_charges') }}</div>
        <table>
            <tbody>
                @if(isset($deductions['avs_employer']) && bccomp((string) $deductions['avs_employer'], '0', 2) > 0)
                    <tr>
                        <td>{{ __('exports.salary_slip.avs_ai_apg_employer') }}</td>
                        <td class="r">{{ number_format((float) $deductions['avs_employer'], 2, '.', "'") }}</td>
                    </tr>
                @endif
                @if(isset($deductions['ac_employer']) && bccomp((string) $deductions['ac_employer'], '0', 2) > 0)
                    <tr>
                        <td>{{ __('exports.salary_slip.unemployment_insurance_employer') }}</td>
                        <td class="r">{{ number_format((float) $deductions['ac_employer'], 2, '.', "'") }}</td>
                    </tr>
                @endif
                @if(isset($deductions['lpp_employer']) && bccomp((string) $deductions['lpp_employer'], '0', 2) > 0)
                    <tr>
                        <td>{{ __('exports.salary_slip.pension_lpp_employer') }}</td>
                        <td class="r">{{ number_format((float) $deductions['lpp_employer'], 2, '.', "'") }}</td>
                    </tr>
                @endif
                <tr class="row-total">
                    <td>{{ __('exports.salary_slip.total_employer_charges') }}</td>
                    <td class="r">{{ number_format((float) ($deductions['total_employer'] ?? '0'), 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="page-footer">
        <span>{{ __('exports.common.generated_by') }} — {{ now()->format('d.m.Y H:i') }}</span>
        <span>{{ __('exports.common.page') }} <span class="page-num"></span> / <span class="page-total"></span></span>
    </div>
</body>
</html>
