<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.profit_loss.title') }} — {{ $organization->legal_name ?? $organization->name }}</title>
    @include('exports._styles')
</head>
<body>
    @php
        $fmt = fn (?string $d): string => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    @include('exports._header', [
        'docTitle'  => __('exports.profit_loss.title'),
        'docPeriod' => $fmt($period['from']) . ' – ' . $fmt($period['to']),
    ])

    <table>
        <thead>
            <tr>
                <th>{{ __('exports.common.code') }}</th>
                <th>{{ __('exports.common.account') }}</th>
                <th class="r">{{ __('exports.common.amount') }} (CHF)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-head">
                <td colspan="3">{{ __('exports.profit_loss.revenue') }}</td>
            </tr>
            @foreach ($revenue as $account)
                <tr>
                    <td class="muted">{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="r">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="2">{{ __('exports.profit_loss.total_revenue') }}</td>
                <td class="r">{{ number_format((float) $totalRevenue, 2, '.', "'") }}</td>
            </tr>

            <tr class="section-head">
                <td colspan="3">{{ __('exports.profit_loss.expenses') }}</td>
            </tr>
            @foreach ($expenses as $account)
                <tr>
                    <td class="muted">{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="r">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="2">{{ __('exports.profit_loss.total_expenses') }}</td>
                <td class="r">{{ number_format((float) $totalExpenses, 2, '.', "'") }}</td>
            </tr>

            <tr class="row-grand">
                <td colspan="2">{{ __('exports.profit_loss.net_profit_loss') }}</td>
                <td class="r">{{ number_format((float) $netProfit, 2, '.', "'") }}</td>
            </tr>
        </tbody>
    </table>

    <div class="page-footer">
        <span>{{ __('exports.common.generated_by') }} — {{ now()->format('d.m.Y H:i') }}</span>
        <span>{{ __('exports.common.page') }} <span class="page-num"></span> / <span class="page-total"></span></span>
    </div>
</body>
</html>
