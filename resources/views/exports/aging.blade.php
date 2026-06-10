<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.aging.title', ['type' => ucfirst($report['type'])]) }} — {{ $organization->legal_name ?? $organization->name }}</title>
    @include('exports._styles')
</head>
<body>
    @php
        $fmt = fn ($d): string => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    @include('exports._header', [
        'docTitle'  => __('exports.aging.title', ['type' => ucfirst($report['type'])]),
        'docPeriod' => __('exports.aging.as_of', ['date' => $fmt($report['as_of_date'])]),
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
                            <th class="r">{{ __('exports.common.amount') }} (CHF)</th>
                            <th class="r">{{ __('exports.aging.days_overdue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bracket['items'] as $item)
                            <tr>
                                <td>{{ $item['document_number'] }}</td>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $fmt($item['date']) }}</td>
                                <td>{{ $fmt($item['due_date']) }}</td>
                                <td class="r">{{ number_format((float) $item['amount'], 2, '.', "'") }}</td>
                                <td class="r {{ $item['days_overdue'] > 0 ? 'overdue' : '' }}">
                                    {{ $item['days_overdue'] > 0 ? $item['days_overdue'] : '—' }}
                                </td>
                            </tr>
                        @endforeach
                        <tr class="row-total">
                            <td colspan="4">{{ __('exports.common.subtotal') }}</td>
                            <td class="r">{{ number_format((float) $bracket['total'], 2, '.', "'") }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            @else
                <p class="muted" style="padding:6px 8px">{{ __('exports.common.no_items_in_this_bracket') }}</p>
            @endif
        </div>
    @endforeach

    <table style="margin-top:20px">
        <tbody>
            <tr class="row-grand">
                <td colspan="4">{{ __('exports.common.grand_total') }}</td>
                <td class="r">{{ number_format((float) $report['grand_total'], 2, '.', "'") }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="page-footer">
        <span>{{ __('exports.common.generated_by') }} — {{ now()->format('d.m.Y H:i') }}</span>
        <span>{{ __('exports.common.page') }} <span class="page-num"></span> / <span class="page-total"></span></span>
    </div>
</body>
</html>
