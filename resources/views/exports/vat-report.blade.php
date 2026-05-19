<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.vat.title') }} — {{ $organization->legal_name ?? $organization->name }}</title>
    @include('exports._styles')
</head>
<body>
    @php
        $fmt = fn ($d): string => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    @include('exports._header', [
        'docTitle'  => __('exports.vat.subtitle'),
        'docPeriod' => __('exports.vat.period', [
            'from' => $fmt($report['period']['from']),
            'to'   => $fmt($report['period']['to']),
        ]),
    ])

    {{-- Chiffres 200–299: Revenue by rate --}}
    <div class="section">
        <div class="section-title">{{ __('exports.vat.section_1') }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width:60px">{{ __('exports.vat.code') }}</th>
                    <th>{{ __('exports.vat.rate') }}</th>
                    <th class="r">{{ __('exports.vat.base_amount') }} (CHF)</th>
                    <th class="r">{{ __('exports.vat.vat_amount') }} (CHF)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['revenue_by_rate'] as $row)
                    <tr>
                        <td class="muted">200</td>
                        <td>{{ $row['rate_name'] }} ({{ $row['rate'] }}%)</td>
                        <td class="r">{{ number_format((float) $row['base_amount'], 2, '.', "'") }}</td>
                        <td class="r">{{ number_format((float) $row['vat_amount'], 2, '.', "'") }}</td>
                    </tr>
                @endforeach
                <tr class="row-total">
                    <td class="muted">299</td>
                    <td>{{ __('exports.vat.taxable_turnover_total') }}</td>
                    <td class="r">{{ number_format((float) $report['total_revenue'], 2, '.', "'") }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Chiffres 300–399: Output VAT --}}
    <div class="section">
        <div class="section-title">{{ __('exports.vat.section_2') }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width:60px">{{ __('exports.vat.code') }}</th>
                    <th>{{ __('exports.vat.rate') }}</th>
                    <th class="r">{{ __('exports.vat.vat_amount') }} (CHF)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['output_vat_by_rate'] as $row)
                    <tr>
                        <td class="muted">300</td>
                        <td>{{ $row['rate_name'] }} ({{ $row['rate'] }}%)</td>
                        <td class="r">{{ number_format((float) $row['amount'], 2, '.', "'") }}</td>
                    </tr>
                @endforeach
                <tr class="row-total">
                    <td class="muted">399</td>
                    <td>{{ __('exports.vat.output_vat_total') }}</td>
                    <td class="r">{{ number_format((float) $report['total_output_vat'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Chiffre 400: Input VAT --}}
    <div class="section">
        <div class="section-title">{{ __('exports.vat.section_3') }}</div>
        <table>
            <tbody>
                <tr>
                    <td class="muted" style="width:60px">400</td>
                    <td>{{ __('exports.vat.input_vat') }}</td>
                    <td class="r">{{ number_format((float) $report['input_vat'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Chiffres 500–510: Net VAT --}}
    <div class="section">
        <div class="section-title">{{ __('exports.vat.section_4') }}</div>
        <table>
            <tbody>
                <tr>
                    <td class="muted" style="width:60px">500</td>
                    <td>{{ __('exports.vat.net_vat') }}</td>
                    <td class="r">{{ number_format((float) $report['net_vat'], 2, '.', "'") }}</td>
                </tr>
                <tr class="row-grand">
                    <td class="muted" style="width:60px">510</td>
                    <td>{{ __('exports.vat.vat_payable') }}</td>
                    <td class="r">{{ number_format((float) $report['vat_payable'], 2, '.', "'") }}</td>
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
