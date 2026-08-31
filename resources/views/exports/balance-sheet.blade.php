<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.balance_sheet.title') }}</title>
    @include('exports._styles')
</head>
<body>
    @include('exports._header', [
        'docTitle' => __('exports.balance_sheet.title'),
        'docPeriod' => __('exports.balance_sheet.period', ['from' => $period['from'], 'to' => $period['to']]),
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
                <td colspan="3">{{ __('exports.balance_sheet.assets') }}</td>
            </tr>
            @foreach ($assets['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="amount">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">{{ __('exports.balance_sheet.total_assets') }}</td>
                <td class="amount">{{ number_format((float) $assets['total'], 2, '.', "'") }}</td>
            </tr>

            <tr class="section-header">
                <td colspan="3">{{ __('exports.balance_sheet.liabilities') }}</td>
            </tr>
            @foreach ($liabilities['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="amount">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">{{ __('exports.balance_sheet.total_liabilities') }}</td>
                <td class="amount">{{ number_format((float) $liabilities['total'], 2, '.', "'") }}</td>
            </tr>

            <tr class="section-header">
                <td colspan="3">{{ __('exports.balance_sheet.equity') }}</td>
            </tr>
            @foreach ($equity['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="amount">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">{{ __('exports.balance_sheet.total_equity') }}</td>
                <td class="amount">{{ number_format((float) $equity['total'], 2, '.', "'") }}</td>
            </tr>
        </tbody>
    </table>

    @include('exports._footer')
</body>
</html>
