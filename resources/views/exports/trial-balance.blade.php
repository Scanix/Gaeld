<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.trial_balance.title') }}</title>
    @include('exports._styles')
</head>
<body>
    @include('exports._header', [
        'docTitle' => __('exports.trial_balance.title'),
        'docPeriod' => __('exports.trial_balance.as_of', ['date' => $asOfDate]),
    ])

    <table>
        <thead>
            <tr>
                <th>{{ __('exports.common.code') }}</th>
                <th>{{ __('exports.common.account') }}</th>
                <th>{{ __('exports.common.type') }}</th>
                <th class="amount">{{ __('exports.common.debit') }}</th>
                <th class="amount">{{ __('exports.common.credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $totalDebit = '0'; $totalCredit = '0'; @endphp
            @foreach ($balances as $row)
                <tr>
                    <td>{{ $row['account_code'] }}</td>
                    <td>{{ $row['account_name'] }}</td>
                    <td>{{ $row['account_type'] }}</td>
                    <td class="amount">{{ bccomp($row['debit'], '0', 2) !== 0 ? number_format((float) $row['debit'], 2, '.', "'") : '' }}</td>
                    <td class="amount">{{ bccomp($row['credit'], '0', 2) !== 0 ? number_format((float) $row['credit'], 2, '.', "'") : '' }}</td>
                </tr>
                @php $totalDebit = bcadd($totalDebit, $row['debit'], 2); $totalCredit = bcadd($totalCredit, $row['credit'], 2); @endphp
            @endforeach
            <tr class="total">
                <td colspan="3">{{ __('exports.common.total') }}</td>
                <td class="amount">{{ number_format((float) $totalDebit, 2, '.', "'") }}</td>
                <td class="amount">{{ number_format((float) $totalCredit, 2, '.', "'") }}</td>
            </tr>
        </tbody>
    </table>

    @include('exports._footer')
</body>
</html>
