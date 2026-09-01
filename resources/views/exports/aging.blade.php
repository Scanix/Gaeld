<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.aging.title', ['type' => ucfirst($report['type'])]) }}</title>
    @include('exports._styles')
</head>
<body>
    @include('exports._header', [
        'docTitle' => __('exports.aging.title', ['type' => ucfirst($report['type'])]),
        'docPeriod' => __('exports.aging.as_of', ['date' => $report['as_of_date']]),
    ])

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

    @include('exports._footer')
</body>
</html>
