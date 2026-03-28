<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.aging.title', ['type' => ucfirst($report['type'])]) }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 20mm 15mm; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 16pt; margin-bottom: 4px; }
        .header h2 { font-size: 12pt; color: #555; margin-bottom: 4px; }
        .header .as-of { font-size: 10pt; color: #555; }
        .section { margin-top: 18px; }
        .section-title { font-size: 11pt; font-weight: bold; background-color: #f0f0f0; padding: 5px 8px; border-bottom: 2px solid #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background-color: #f0f0f0; text-align: left; padding: 5px 8px; border-bottom: 1px solid #999; font-size: 9pt; text-transform: uppercase; }
        th.amount { text-align: right; }
        td { padding: 4px 8px; border-bottom: 1px solid #eee; font-size: 9pt; }
        td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        td.overdue { color: #c0392b; }
        tr.subtotal td { font-weight: bold; background-color: #f8f8f8; border-top: 1px solid #999; }
        tr.grand-total td { font-weight: bold; border-top: 3px double #333; font-size: 11pt; padding-top: 8px; }
        .empty { color: #999; font-style: italic; padding: 4px 8px; }
        .footer { margin-top: 30px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $organizationName }}</h1>
        <h2>{{ __('exports.aging.title', ['type' => ucfirst($report['type'])]) }}</h2>
        <div class="as-of">{{ __('exports.aging.as_of', ['date' => $report['as_of_date']]) }}</div>
    </div>

    @php
        $bracketLabels = [
            'current' => __('exports.aging.current'),
            '1_30'    => __('exports.aging.days_1_30'),
            '31_60'   => __('exports.aging.days_31_60'),
            '61_90'   => __('exports.aging.days_61_90'),
            '90_plus' => __('exports.aging.days_90_plus'),
        ];
    @endphp

    @foreach ($report['brackets'] as $key => $bracket)
        <div class="section">
            <div class="section-title">{{ $bracketLabels[$key] ?? $key }}</div>

            @if (count($bracket['items']) > 0)
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('exports.common.document_number') }}</th>
                            <th>{{ __('exports.common.name') }}</th>
                            <th>{{ __('exports.common.date') }}</th>
                            <th>{{ __('exports.common.due_date') }}</th>
                            <th class="amount">{{ __('exports.common.amount') }}</th>
                            <th class="amount">{{ __('exports.aging.days_overdue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bracket['items'] as $item)
                            <tr>
                                <td>{{ $item['document_number'] }}</td>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['date'] }}</td>
                                <td>{{ $item['due_date'] }}</td>
                                <td class="amount">{{ number_format((float) $item['amount'], 2, '.', "'") }}</td>
                                <td class="amount {{ $item['days_overdue'] > 0 ? 'overdue' : '' }}">
                                    {{ $item['days_overdue'] > 0 ? $item['days_overdue'] : '—' }}
                                </td>
                            </tr>
                        @endforeach
                        <tr class="subtotal">
                            <td colspan="4">{{ __('exports.common.subtotal') }}</td>
                            <td class="amount">{{ number_format((float) $bracket['total'], 2, '.', "'") }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            @else
                <p class="empty">{{ __('exports.common.no_items_in_this_bracket') }}</p>
            @endif
        </div>
    @endforeach

    <table style="margin-top:20px;">
        <tbody>
            <tr class="grand-total">
                <td colspan="4">{{ __('exports.common.grand_total') }}</td>
                <td class="amount">{{ number_format((float) $report['grand_total'], 2, '.', "'") }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        {{ __('exports.common.generated_by') }} &mdash; {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>
