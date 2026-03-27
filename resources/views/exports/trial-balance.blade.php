<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trial Balance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 20mm 15mm; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 16pt; margin-bottom: 4px; }
        .header .date { font-size: 10pt; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #f0f0f0; text-align: left; padding: 6px 8px; border-bottom: 2px solid #333; font-size: 9pt; text-transform: uppercase; }
        th.amount { text-align: right; }
        td { padding: 5px 8px; border-bottom: 1px solid #ddd; }
        td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        tr.total td { font-weight: bold; border-top: 2px solid #333; border-bottom: none; }
        .footer { margin-top: 30px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $organizationName }}</h1>
        <div class="date">Trial Balance as of {{ $asOfDate }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Account</th>
                <th>Type</th>
                <th class="amount">Debit</th>
                <th class="amount">Credit</th>
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
                <td colspan="3">Total</td>
                <td class="amount">{{ number_format((float) $totalDebit, 2, '.', "'") }}</td>
                <td class="amount">{{ number_format((float) $totalCredit, 2, '.', "'") }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ now()->format('d.m.Y H:i') }} — {{ $organizationName }}
    </div>
</body>
</html>
