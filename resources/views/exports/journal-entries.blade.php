<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Journal Entries</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 9pt; color: #1a1a1a; padding: 20mm 15mm; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 16pt; margin-bottom: 4px; }
        .header .period { font-size: 10pt; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f0f0f0; text-align: left; padding: 5px 6px; border-bottom: 2px solid #333; font-size: 8pt; text-transform: uppercase; }
        th.amount { text-align: right; }
        td { padding: 4px 6px; border-bottom: 1px solid #eee; }
        td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        tr.entry-header td { background-color: #f8f8f8; font-weight: bold; border-bottom: 1px solid #ccc; padding-top: 8px; }
        tr.line td { padding-left: 20px; }
        .footer { margin-top: 30px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $organizationName }}</h1>
        <div class="period">Journal Entries — {{ $fromDate }} to {{ $toDate }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Description / Account</th>
                <th class="amount">Debit</th>
                <th class="amount">Credit</th>
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

    <div class="footer">
        Generated on {{ now()->format('d.m.Y H:i') }} — {{ $organizationName }}
    </div>
</body>
</html>
