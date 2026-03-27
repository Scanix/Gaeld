<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Décompte TVA / VAT Declaration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 20mm 15mm; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 16pt; margin-bottom: 4px; }
        .header h2 { font-size: 12pt; color: #555; margin-bottom: 4px; }
        .header .period { font-size: 10pt; color: #555; }
        .section { margin-top: 18px; }
        .section-title { font-size: 11pt; font-weight: bold; background-color: #f0f0f0; padding: 5px 8px; border-bottom: 2px solid #333; margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f0f0f0; text-align: left; padding: 5px 8px; border-bottom: 1px solid #999; font-size: 9pt; text-transform: uppercase; }
        th.num { text-align: right; }
        td { padding: 4px 8px; border-bottom: 1px solid #eee; }
        td.chiffre { width: 60px; font-weight: bold; color: #555; }
        td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        tr.total td { font-weight: bold; border-top: 2px solid #333; border-bottom: none; background-color: #f8f8f8; }
        tr.payable td { font-weight: bold; border-top: 3px double #333; font-size: 11pt; padding-top: 8px; background-color: #fff3cd; }
        .footer { margin-top: 30px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $organizationName }}</h1>
        <h2>Décompte TVA / VAT Declaration (AFC/ESTV)</h2>
        <div class="period">Période / Period: {{ $report['period']['from'] }} to {{ $report['period']['to'] }}</div>
    </div>

    {{-- Chiffres 200–299: Revenue by rate --}}
    <div class="section">
        <div class="section-title">I. Chiffre d'affaires imposable / Taxable Turnover (Chiffres 200–299)</div>
        <table>
            <thead>
                <tr>
                    <th class="num">Chiffre</th>
                    <th>Taux / Rate</th>
                    <th class="num">Base imposable / Base Amount</th>
                    <th class="num">TVA calculée / VAT Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['revenue_by_rate'] as $row)
                    <tr>
                        <td class="chiffre">200</td>
                        <td>{{ $row['rate_name'] }} ({{ $row['rate'] }}%)</td>
                        <td class="amount">{{ number_format((float) $row['base_amount'], 2, '.', "'") }}</td>
                        <td class="amount">{{ number_format((float) $row['vat_amount'], 2, '.', "'") }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="chiffre">299</td>
                    <td>Total chiffre d'affaires imposable</td>
                    <td class="amount">{{ number_format((float) $report['total_revenue'], 2, '.', "'") }}</td>
                    <td class="amount"></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Chiffres 300–399: Output VAT --}}
    <div class="section">
        <div class="section-title">II. TVA collectée / Output VAT (Chiffres 300–399)</div>
        <table>
            <thead>
                <tr>
                    <th class="num">Chiffre</th>
                    <th>Taux / Rate</th>
                    <th class="num">Montant TVA</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['output_vat_by_rate'] as $row)
                    <tr>
                        <td class="chiffre">300</td>
                        <td>{{ $row['rate_name'] }} ({{ $row['rate'] }}%)</td>
                        <td class="amount">{{ number_format((float) $row['amount'], 2, '.', "'") }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="chiffre">399</td>
                    <td>Total TVA collectée</td>
                    <td class="amount">{{ number_format((float) $report['total_output_vat'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Chiffre 400: Input VAT --}}
    <div class="section">
        <div class="section-title">III. TVA déductible / Input VAT (Chiffre 400)</div>
        <table>
            <tbody>
                <tr>
                    <td class="chiffre">400</td>
                    <td>TVA déductible (impôt préalable / Vorsteuer)</td>
                    <td class="amount">{{ number_format((float) $report['input_vat'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Chiffres 500–510: Net VAT --}}
    <div class="section">
        <div class="section-title">IV. Montant dû / VAT Due (Chiffres 500–510)</div>
        <table>
            <tbody>
                <tr>
                    <td class="chiffre">500</td>
                    <td>TVA nette (chiffre 399 – chiffre 400)</td>
                    <td class="amount">{{ number_format((float) $report['net_vat'], 2, '.', "'") }}</td>
                </tr>
                <tr class="payable">
                    <td class="chiffre">510</td>
                    <td>TVA à payer à l'AFC / VAT payable to AFC</td>
                    <td class="amount">{{ number_format((float) $report['vat_payable'], 2, '.', "'") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        Generated by Gäld &mdash; {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>
