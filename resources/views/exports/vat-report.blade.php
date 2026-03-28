<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.vat.title') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 20mm 15mm; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 16pt; margin-bottom: 4px; }
        .header h2 { font-size: 12pt; color: #555; margin-bottom: 4px; }
        .header .period { font-size: 10pt; color: #555; }
        .section { margin-top: 18px; }
        .section-title { font-size: 11pt; font-weight: bold; background-color: #f0f0f0; padding: 5px 8px; border-bottom: 2px solid #333; margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f0f0f0; text-align: left; padding: 5px 8px; border-bottom: 1px solid #999; font-size: 9pt; text-transform: uppercase; }
        th.num { text-align: right; }
        td { padding: 4px 8px; border-bottom: 1px solid #eee; }
        td.chiffre { width: 60px; font-weight: bold; color: #555; }
        td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        tr.total td { font-weight: bold; border-top: 2px solid #333; border-bottom: none; background-color: #f8f8f8; }
        tr.payable td { font-weight: bold; border-top: 3px double #333; font-size: 11pt; padding-top: 8px; background-color: #fff3cd; }
        .footer { margin-top: 30px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $organizationName }}</h1>
        <h2>{{ __('exports.vat.subtitle') }}</h2>
        <div class="period">{{ __('exports.vat.period', ['from' => $report['period']['from'], 'to' => $report['period']['to']]) }}</div>
    </div>

    {{-- Chiffres 200–299: Revenue by rate --}}
    <div class="section">
        <div class="section-title">{{ __('exports.vat.section_1') }}</div>
        <table>
            <thead>
                <tr>
                    <th class="num">{{ __('exports.vat.code') }}</th>
                    <th>{{ __('exports.vat.rate') }}</th>
                    <th class="num">{{ __('exports.vat.base_amount') }}</th>
                    <th class="num">{{ __('exports.vat.vat_amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['revenue_by_rate'] as $row)
                    <tr>
                        <td class="chiffre">200</td>
                        <td>{{ $row['rate_name'] }} ({{ $row['rate'] }}%)</td>
                        <td class="amount">{{ number_format((float) $row['base_amount'], 2, '.', "'") }}</td>
                        <td class="amount">{{ number_format((float) $row['vat_amount'], 2, '.', "'") }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="chiffre">299</td>
                    <td>{{ __('exports.vat.taxable_turnover_total') }}</td>
                    <td class="amount">{{ number_format((float) $report['total_revenue'], 2, '.', "'") }}</td>
                    <td class="amount"></td>
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
                    <th class="num">{{ __('exports.vat.code') }}</th>
                    <th>{{ __('exports.vat.rate') }}</th>
                    <th class="num">{{ __('exports.vat.vat_amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['output_vat_by_rate'] as $row)
                    <tr>
                        <td class="chiffre">300</td>
                        <td>{{ $row['rate_name'] }} ({{ $row['rate'] }}%)</td>
                        <td class="amount">{{ number_format((float) $row['amount'], 2, '.', "'") }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="chiffre">399</td>
                    <td>{{ __('exports.vat.output_vat_total') }}</td>
                    <td class="amount">{{ number_format((float) $report['total_output_vat'], 2, '.', "'") }}</td>
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
                    <td class="chiffre">400</td>
                    <td>{{ __('exports.vat.input_vat') }}</td>
                    <td class="amount">{{ number_format((float) $report['input_vat'], 2, '.', "'") }}</td>
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
                    <td class="chiffre">500</td>
                    <td>{{ __('exports.vat.net_vat') }}</td>
                    <td class="amount">{{ number_format((float) $report['net_vat'], 2, '.', "'") }}</td>
                </tr>
                <tr class="payable">
                    <td class="chiffre">510</td>
                    <td>{{ __('exports.vat.vat_payable') }}</td>
                    <td class="amount">{{ number_format((float) $report['vat_payable'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        {{ __('exports.common.generated_by') }} &mdash; {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>
