<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.cash_flow.title') }} — {{ $organization->legal_name ?? $organization->name }}</title>
    @include('exports._styles')
</head>
<body>
    @php
        $fmt = fn ($d): string => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    @include('exports._header', [
        'docTitle'  => __('exports.cash_flow.title'),
        'docPeriod' => $fmt($period['from']) . ' – ' . $fmt($period['to']),
    ])

    {{-- Operating Activities --}}
    <div class="section">
        <div class="section-title">{{ __('exports.cash_flow.operating_activities') }}</div>
        <table>
            <tbody>
                <tr>
                    <td>{{ __('exports.cash_flow.net_income') }}</td>
                    <td class="r">{{ number_format((float) $report['net_income'], 2, '.', "'") }}</td>
                </tr>
                @foreach ($report['operating']['adjustments'] as $adj)
                    <tr>
                        <td style="padding-left:24px">{{ $adj['label'] }}</td>
                        <td class="r">{{ number_format((float) $adj['amount'], 2, '.', "'") }}</td>
                    </tr>
                @endforeach
                <tr class="row-total">
                    <td>{{ __('exports.cash_flow.net_cash_operating') }}</td>
                    <td class="r">{{ number_format((float) $report['operating']['total'], 2, '.', "'") }}</td>
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
                        <td style="padding-left:24px">{{ $item['label'] }}</td>
                        <td class="r">{{ number_format((float) $item['amount'], 2, '.', "'") }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">{{ __('exports.common.no_investing_activities') }}</td></tr>
                @endforelse
                <tr class="row-total">
                    <td>{{ __('exports.cash_flow.net_cash_investing') }}</td>
                    <td class="r">{{ number_format((float) $report['investing']['total'], 2, '.', "'") }}</td>
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
                        <td style="padding-left:24px">{{ $item['label'] }}</td>
                        <td class="r">{{ number_format((float) $item['amount'], 2, '.', "'") }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">{{ __('exports.common.no_financing_activities') }}</td></tr>
                @endforelse
                <tr class="row-total">
                    <td>{{ __('exports.cash_flow.net_cash_financing') }}</td>
                    <td class="r">{{ number_format((float) $report['financing']['total'], 2, '.', "'") }}</td>
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
                    <td class="r">{{ number_format((float) $report['beginning_cash'], 2, '.', "'") }}</td>
                </tr>
                <tr>
                    <td>{{ __('exports.cash_flow.net_change_in_cash') }}</td>
                    <td class="r">{{ number_format((float) $report['net_change'], 2, '.', "'") }}</td>
                </tr>
                <tr class="row-grand">
                    <td>{{ __('exports.cash_flow.ending_cash_balance') }}</td>
                    <td class="r">{{ number_format((float) $report['ending_cash'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="page-footer">
        <span>{{ __('exports.common.generated_by') }} — {{ now()->format('d.m.Y H:i') }}</span>
        <span>{{ __('exports.common.page') }} <span class="page-num"></span> / <span class="page-total"></span></span>
    </div>
</body>
</html>
