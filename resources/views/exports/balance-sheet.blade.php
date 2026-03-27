<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Balance Sheet</title>
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
        tr.section-header td { font-weight: bold; background-color: #f8f8f8; border-bottom: 1px solid #999; padding-top: 10px; }
        tr.total td { font-weight: bold; border-top: 2px solid #333; border-bottom: none; }
        .footer { margin-top: 30px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $organizationName }}</h1>
        <div class="date">Balance Sheet as of {{ $asOfDate }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Account</th>
                <th></th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-header">
                <td colspan="3">Assets</td>
            </tr>
            @foreach ($assets['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="amount">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total Assets</td>
                <td class="amount">{{ number_format((float) $assets['total'], 2, '.', "'") }}</td>
            </tr>

            <tr class="section-header">
                <td colspan="3">Liabilities</td>
            </tr>
            @foreach ($liabilities['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="amount">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total Liabilities</td>
                <td class="amount">{{ number_format((float) $liabilities['total'], 2, '.', "'") }}</td>
            </tr>

            <tr class="section-header">
                <td colspan="3">Equity</td>
            </tr>
            @foreach ($equity['accounts'] as $account)
                <tr>
                    <td>{{ $account['code'] }}</td>
                    <td>{{ $account['name'] }}</td>
                    <td class="amount">{{ number_format((float) $account['balance'], 2, '.', "'") }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total Equity</td>
                <td class="amount">{{ number_format((float) $equity['total'], 2, '.', "'") }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ now()->format('d.m.Y H:i') }} — {{ $organizationName }}
    </div>
</body>
</html>
