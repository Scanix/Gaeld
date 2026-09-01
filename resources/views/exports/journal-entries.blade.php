<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.journal_entries.title') }}</title>
    @include('exports._styles')
</head>
<body>
    @include('exports._header', [
        'docTitle' => __('exports.journal_entries.title'),
        'docPeriod' => __('exports.journal_entries.period', ['from' => $fromDate, 'to' => $toDate]),
    ])

    <table>
        <thead>
            <tr>
                <th>{{ __('exports.common.date') }}</th>
                <th>{{ __('exports.common.reference') }}</th>
                <th>{{ __('exports.common.description_account') }}</th>
                <th class="amount">{{ __('exports.common.debit') }}</th>
                <th class="amount">{{ __('exports.common.credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr class="entry-header">
                    <td>{{ $entry->date instanceof \DateTimeInterface ? $entry->date->format('d.m.Y') : $entry->date }}</td>
                    <td>{{ $entry->reference }}</td>
                    <td>{{ $entry->description }}</td>
                    <td></td>
                    <td></td>
                </tr>
                @foreach ($entry->lines as $line)
                    <tr class="line">
                        <td></td>
                        <td>{{ $line->account->code ?? '' }}</td>
                        <td>{{ $line->account->name ?? '' }}@if($line->description) — {{ $line->description }}@endif</td>
                        <td class="amount">{{ bccomp((string) $line->debit, '0', 2) !== 0 ? number_format((float) $line->debit, 2, '.', "'") : '' }}</td>
                        <td class="amount">{{ bccomp((string) $line->credit, '0', 2) !== 0 ? number_format((float) $line->credit, 2, '.', "'") : '' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    @include('exports._footer')
</body>
</html>
