<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.trial_balance.title') }} — {{ $organization->legal_name ?? $organization->name }}</title>
    @include('exports._styles')
</head>
<body>
    @php
        $fmt = fn ($d): string => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    @include('exports._header', [
        'docTitle'  => __('exports.trial_balance.title'),
        'docPeriod' => __('exports.trial_balance.as_of', ['date' => $fmt($asOfDate)]),
    ])

    <table>
        <thead>
            <tr>
                <th>{{ __('exports.common.code') }}</th>
                <th>{{ __('exports.common.account') }}</th>
                <th>{{ __('exports.common.type') }}</th>
                <th class="r">{{ __('exports.common.debit') }} (CHF)</th>
                <th class="r">{{ __('exports.common.credit') }} (CHF)</th>
            </tr>
        </thead>
        <tbody>
            @php $totalDebit = '0'; $totalCredit = '0'; @endphp
            @foreach ($balances as $row)
                <tr>
                    <td class="muted">{{ $row['account_code'] }}</td>
                    <td>{{ $row['account_name'] }}</td>
                    <td class="muted">{{ $row['account_type'] }}</td>
                    <td class="r">{{ bccomp($row['debit'], '0', 2) !== 0 ? number_format((float) $row['debit'], 2, '.', "'") : '' }}</td>
                    <td class="r">{{ bccomp($row['credit'], '0', 2) !== 0 ? number_format((float) $row['credit'], 2, '.', "'") : '' }}</td>
                </tr>
                @php $totalDebit = bcadd($totalDebit, $row['debit'], 2); $totalCredit = bcadd($totalCredit, $row['credit'], 2); @endphp
            @endforeach
            <tr class="row-grand">
                <td colspan="3">{{ __('exports.common.total') }}</td>
                <td class="r">{{ number_format((float) $totalDebit, 2, '.', "'") }}</td>
                <td class="r">{{ number_format((float) $totalCredit, 2, '.', "'") }}</td>
            </tr>
        </tbody>
    </table>

    <div class="page-footer">
        <span>{{ __('exports.common.generated_by') }} — {{ now()->format('d.m.Y H:i') }}</span>
        <span>{{ __('exports.common.page') }} <span class="page-num"></span> / <span class="page-total"></span></span>
    </div>
</body>
</html>
