<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.journal_entries.title') }} — {{ $organization->legal_name ?? $organization->name }}</title>
    @include('exports._styles')
</head>
<body>
    @php
        $fmt = fn ($d): string => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';
    @endphp

    @include('exports._header', [
        'docTitle'  => __('exports.journal_entries.title'),
        'docPeriod' => $fmt($fromDate) . ' – ' . $fmt($toDate),
    ])

    <table>
        <thead>
            <tr>
                <th>{{ __('exports.common.date') }}</th>
                <th>{{ __('exports.common.reference') }}</th>
                <th>{{ __('exports.common.description_account') }}</th>
                <th class="r">{{ __('exports.common.debit') }} (CHF)</th>
                <th class="r">{{ __('exports.common.credit') }} (CHF)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr class="section-head">
                    <td>{{ $fmt($entry->date instanceof \DateTimeInterface ? $entry->date->format('Y-m-d') : $entry->date) }}</td>
                    <td>{{ $entry->reference }}</td>
                    <td>{{ $entry->description }}</td>
                    <td></td>
                    <td></td>
                </tr>
                @foreach ($entry->lines as $line)
                    <tr>
                        <td></td>
                        <td class="muted">{{ $line->account->code ?? '' }}</td>
                        <td>{{ $line->account->name ?? '' }}@if($line->description) — {{ $line->description }}@endif</td>
                        <td class="r">{{ bccomp((string) $line->debit, '0', 2) !== 0 ? number_format((float) $line->debit, 2, '.', "'") : '' }}</td>
                        <td class="r">{{ bccomp((string) $line->credit, '0', 2) !== 0 ? number_format((float) $line->credit, 2, '.', "'") : '' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="page-footer">
        <span>{{ __('exports.common.generated_by') }} — {{ now()->format('d.m.Y H:i') }}</span>
        <span>{{ __('exports.common.page') }} <span class="page-num"></span> / <span class="page-total"></span></span>
    </div>
</body>
</html>
