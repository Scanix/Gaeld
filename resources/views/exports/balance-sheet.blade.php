<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.balance_sheet.title') }} — {{ $organization->legal_name ?? $organization->name }}</title>
    @include('exports._styles')
</head>
<body>
    @php
        $fmt = fn (?string $d): string => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    @include('exports._header', [
        'docTitle'  => __('exports.balance_sheet.title'),
        'docPeriod' => __('exports.balance_sheet.as_of', ['date' => $fmt($asOfDate)]),
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
                <td colspan="3">{{ __('exports.balance_sheet.assets') }}</td>
            </tr>
            @foreach ($assets['accounts'] as $account)
                <tr>
                    <td class="muted">{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="r">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="2">{{ __('exports.balance_sheet.total_assets') }}</td>
                <td class="r">{{ number_format((float) $assets['total'], 2, '.', "'") }}</td>
            </tr>

            <tr class="section-head">
                <td colspan="3">{{ __('exports.balance_sheet.liabilities') }}</td>
            </tr>
            @foreach ($liabilities['accounts'] as $account)
                <tr>
                    <td class="muted">{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="r">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="2">{{ __('exports.balance_sheet.total_liabilities') }}</td>
                <td class="r">{{ number_format((float) $liabilities['total'], 2, '.', "'") }}</td>
            </tr>

            <tr class="section-head">
                <td colspan="3">{{ __('exports.balance_sheet.equity') }}</td>
            </tr>
            @foreach ($equity['accounts'] as $account)
                <tr>
                    <td class="muted">{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="r">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="2">{{ __('exports.balance_sheet.total_equity') }}</td>
                <td class="r">{{ number_format((float) $equity['total'], 2, '.', "'") }}</td>
            </tr>

            {{-- Balance check: assets = liabilities + equity --}}
            @php
                $check = bccomp(
                    (string) $assets['total'],
                    bcadd((string) $liabilities['total'], (string) $equity['total'], 2),
                    2,
                );
            @endphp
            <tr class="row-grand">
                <td colspan="2">{{ __('exports.balance_sheet.total_liabilities_equity') }}</td>
                <td class="r">
                    {{ number_format((float) bcadd((string) $liabilities['total'], (string) $equity['total'], 2), 2, '.', "'") }}
                    @if($check !== 0)
                        ⚠
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <div class="page-footer">
        <span>{{ __('exports.common.generated_by') }} — {{ now()->format('d.m.Y H:i') }}</span>
        <span>{{ __('exports.common.page') }} <span class="page-num"></span> / <span class="page-total"></span></span>
    </div>
</body>
</html>
