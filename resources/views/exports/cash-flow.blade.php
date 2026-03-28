<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.cash_flow.title') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 20mm 15mm; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 16pt; margin-bottom: 4px; }
        .header .period { font-size: 10pt; color: #555; }
        .section { margin-top: 18px; }
        .section-title { font-size: 11pt; font-weight: bold; background-color: #f0f0f0; padding: 5px 8px; border-bottom: 2px solid #333; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f0f0f0; text-align: left; padding: 5px 8px; border-bottom: 1px solid #999; font-size: 9pt; text-transform: uppercase; }
        th.amount { text-align: right; }
        td { padding: 4px 8px; border-bottom: 1px solid #eee; }
        td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        tr.total td { font-weight: bold; border-top: 2px solid #333; border-bottom: none; background-color: #f8f8f8; }
        tr.net-change td { font-weight: bold; border-top: 3px double #333; font-size: 11pt; padding-top: 8px; }
        .footer { margin-top: 30px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $organizationName }}</h1>
        <div class="period">{{ __('exports.cash_flow.period', ['from' => $period['from'], 'to' => $period['to']]) }}</div>
    </div>

    {{-- Operating Activities --}}
    <div class="section">
        <div class="section-title">{{ __('exports.cash_flow.operating_activities') }}</div>
        <table>
            <tbody>
                <tr>
                    <td>{{ __('exports.cash_flow.net_income') }}</td>
                    <td class="amount">{{ number_format((float) $report['net_income'], 2, '.', "'") }}</td>
                </tr>
                @foreach ($report['operating']['adjustments'] as $adj)
                    <tr>
                        <td style="padding-left: 24px;">{{ $adj['label'] }}</td>
                        <td class="amount">{{ number_format((float) $adj['amount'], 2, '.', "'") }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>{{ __('exports.cash_flow.net_cash_operating') }}</td>
                    <td class="amount">{{ number_format((float) $report['operating']['total'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Investing Activities --}}
    <div class="section">
        <div class="section-title">{{ __('exports.cash_flow.investing_activities') }}</div>
        <table>
            <tbody>
                @forelse ($report['investing']['items'] as $item)
                    <tr>
                        <td style="padding-left: 24px;">{{ $item['label'] }}</td>
                        <td class="amount">{{ number_format((float) $item['amount'], 2, '.', "'") }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="color:#999;">{{ __('exports.common.no_investing_activities') }}</td></tr>
                @endforelse
                <tr class="total">
                    <td>{{ __('exports.cash_flow.net_cash_investing') }}</td>
                    <td class="amount">{{ number_format((float) $report['investing']['total'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Financing Activities --}}
    <div class="section">
        <div class="section-title">{{ __('exports.cash_flow.financing_activities') }}</div>
        <table>
            <tbody>
                @forelse ($report['financing']['items'] as $item)
                    <tr>
                        <td style="padding-left: 24px;">{{ $item['label'] }}</td>
                        <td class="amount">{{ number_format((float) $item['amount'], 2, '.', "'") }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="color:#999;">{{ __('exports.common.no_financing_activities') }}</td></tr>
                @endforelse
                <tr class="total">
                    <td>{{ __('exports.cash_flow.net_cash_financing') }}</td>
                    <td class="amount">{{ number_format((float) $report['financing']['total'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Cash Summary --}}
    <div class="section">
        <div class="section-title">{{ __('exports.cash_flow.cash_summary') }}</div>
        <table>
            <tbody>
                <tr>
                    <td>{{ __('exports.cash_flow.beginning_cash_balance') }}</td>
                    <td class="amount">{{ number_format((float) $report['beginning_cash'], 2, '.', "'") }}</td>
                </tr>
                <tr>
                    <td>{{ __('exports.cash_flow.net_change_in_cash') }}</td>
                    <td class="amount">{{ number_format((float) $report['net_change'], 2, '.', "'") }}</td>
                </tr>
                <tr class="net-change">
                    <td>{{ __('exports.cash_flow.ending_cash_balance') }}</td>
                    <td class="amount">{{ number_format((float) $report['ending_cash'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        {{ __('exports.common.generated_by') }} &mdash; {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>
