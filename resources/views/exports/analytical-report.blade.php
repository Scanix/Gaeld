<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.analytical_report.title') }}</title>
    @include('exports._styles')
</head>
<body>
    @include('exports._header', [
        'docTitle' => __('exports.analytical_report.title'),
        'docPeriod' => __('exports.analytical_report.period', ['from' => $period['from'], 'to' => $period['to']]),
    ])

    <table>
        <thead>
            <tr>
                <th>{{ __('exports.common.account') }}</th>
                <th></th>
                <th class="amount">{{ __('exports.common.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-header">
                <td colspan="3">{{ __('exports.analytical_report.revenue') }}</td>
            </tr>
            @foreach ($revenue as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="amount">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">{{ __('exports.analytical_report.total_revenue') }}</td>
                <td class="amount">{{ number_format((float) $totalRevenue, 2, '.', "'") }}</td>
            </tr>

            <tr class="section-header">
                <td colspan="3">{{ __('exports.analytical_report.expenses') }}</td>
            </tr>
            @foreach ($expenses as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="amount">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">{{ __('exports.analytical_report.total_expenses') }}</td>
                <td class="amount">{{ number_format((float) $totalExpenses, 2, '.', "'") }}</td>
            </tr>

            <tr class="net-profit">
                <td colspan="2">{{ __('exports.analytical_report.net_profit_loss') }}</td>
                <td class="amount">{{ number_format((float) $netProfit, 2, '.', "'") }}</td>
            </tr>
        </tbody>
    </table>

    @include('exports._footer')
</body>
</html>
