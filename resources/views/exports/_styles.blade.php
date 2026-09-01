<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    @page { margin: 0; }
    html { background: #fff; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #171b18; background: #fff; margin: 0; padding: 16mm 15mm 22mm 20mm; min-height: 297mm; line-height: 1.35; }

    .doc-header { display: table; width: 100%; padding-bottom: 5mm; border-bottom: 1.5px solid #1f2a24; margin-bottom: 8mm; }
    .issuer-block, .doc-meta-block { display: table-cell; vertical-align: top; }
    .issuer-block { width: 62%; font-size: 9.5pt; line-height: 1.45; }
    .issuer-block .org-name { font-size: 13pt; font-weight: bold; display: block; margin-bottom: 1.5pt; letter-spacing: 0.01em; }
    .issuer-block .org-detail { color: #666; }
    .doc-meta-block { width: 38%; text-align: right; }
    .doc-meta-block .doc-type-label { font-size: 15pt; font-weight: bold; color: #1f2a24; line-height: 1.15; }
    .doc-meta-block .doc-period-label { font-size: 10pt; color: #555; margin-top: 3pt; }
    .doc-meta-block .doc-ref-label { font-size: 8.5pt; color: #777; margin-top: 2pt; }

    .doc-logo { width: 30mm; height: 18mm; margin-bottom: 3mm; }
    .doc-logo img { max-width: 30mm; max-height: 18mm; }

    table { width: 100%; border-collapse: collapse; margin-top: 12px; page-break-inside: auto; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { background-color: #f3f5f3; text-align: left; padding: 5px 8px; border-bottom: 1.5px solid #1f2a24; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.03em; color: #4f5b53; }
    th.r { text-align: right; }
    td { padding: 4.5px 8px; border-bottom: 1px solid #e1e5e2; }
    td.r, td.amount, .right { text-align: right; font-variant-numeric: tabular-nums; }
    td.muted { color: #777; font-size: 9pt; }
    td.overdue { color: #a33b32; }
    td.chiffre { width: 60px; font-weight: bold; color: #59645c; }

    tr.section-head td, tr.section-header td { font-weight: bold; background: #f7f8f7; border-bottom: 1px solid #cbd2cd; padding-top: 10px; color: #1f2a24; }
    tr.row-total td, tr.total td, tr.total-row td { font-weight: bold; border-top: 1.5px solid #1f2a24; border-bottom: none; }
    tr.row-grand td, tr.grand-total td { font-weight: bold; border-top: 3px double #1f2a24; font-size: 11pt; padding-top: 6px; border-bottom: none; }
    tr.subtotal td { font-weight: bold; background: #f7f8f7; border-top: 1px solid #cbd2cd; }
    tr.net-profit td, tr.net-change td { font-weight: bold; border-top: 3px double #1f2a24; font-size: 11pt; padding-top: 8px; }
    tr.payable td { font-weight: bold; border-top: 3px double #1f2a24; font-size: 11pt; padding-top: 8px; background: #fcf8ed; }

    .section { margin-top: 16px; margin-bottom: 6mm; }
    .section-title { font-size: 11pt; font-weight: bold; color: #1f2a24; background-color: #f3f5f3; padding: 5px 8px; border-left: 3px solid #3f8f61; border-bottom: 1px solid #cbd2cd; }
    .notice { margin-bottom: 7mm; padding: 3mm; border: 1px solid #c9a75b; color: #705623; background: #fcf8ed; font-size: 9pt; }
    .empty { color: #777; font-style: italic; padding: 4px 8px; }

    .page-footer { position: fixed; bottom: 10mm; left: 20mm; display: table; width: 175mm; font-size: 7.5pt; color: #9aa39d; border-top: 1px solid #dfe4e0; padding-top: 2mm; }
    .page-footer > span { display: table-cell; width: 50%; }
    .page-footer > span:last-child { padding-left: 10mm; text-align: right; }
    .brand-mark { color: #78837b; letter-spacing: 0.02em; }
    .page-num::after { content: counter(page); }
</style>
