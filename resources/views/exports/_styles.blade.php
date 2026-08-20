{{-- Shared CSS for all Swiss A4 export PDFs. Included via @include('exports._styles'). --}}
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    @page { margin: 0; }
    /* DejaVu Sans is bundled with dompdf and has full Unicode coverage
       (em-dash, en-dash, minus sign, etc.). Avoid Helvetica core font which
       only supports WinAnsi and renders many Unicode chars as blanks. */
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111; margin: 16mm 15mm 22mm 20mm; }

    /* Two-column document header */
    .doc-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 4mm; border-bottom: 2px solid #111; margin-bottom: 7mm; }
    .issuer-block { font-size: 9.5pt; line-height: 1.5; }
    .issuer-block .org-name { font-size: 13pt; font-weight: bold; display: block; margin-bottom: 1pt; }
    .issuer-block .org-detail { color: #666; }
    .doc-meta-block { text-align: right; }
    .doc-meta-block .doc-type-label { font-size: 14pt; font-weight: bold; }
    .doc-meta-block .doc-period-label { font-size: 10pt; color: #555; margin-top: 2pt; }
    .doc-meta-block .doc-ref-label { font-size: 9pt; color: #777; margin-top: 2pt; }

    /* Logo (real image or placeholder when no logo configured) */
    .doc-logo { width: 30mm; height: 18mm; margin-bottom: 3mm; }
    .doc-logo img { max-width: 30mm; max-height: 18mm; }
    .doc-logo-placeholder { width: 30mm; height: 18mm; border: 1px dashed #bbb; color: #bbb; font-size: 8pt; text-align: center; line-height: 18mm; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 3mm; }

    /* Tables */
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th { background-color: #f2f2f2; text-align: left; padding: 5px 8px; border-bottom: 2px solid #111; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.03em; }
    th.r { text-align: right; }
    td { padding: 4px 8px; border-bottom: 1px solid #e0e0e0; }
    td.r { text-align: right; font-variant-numeric: tabular-nums; }
    td.muted { color: #777; font-size: 9pt; }
    td.overdue { color: #b91c1c; }

    /* Row variants */
    tr.section-head td { font-weight: bold; background: #f6f6f6; border-bottom: 1px solid #bbb; padding-top: 10px; }
    tr.row-total td { font-weight: bold; border-top: 1.5px solid #111; border-bottom: none; }
    tr.row-grand td { font-weight: bold; border-top: 3px double #111; font-size: 11pt; padding-top: 6px; border-bottom: none; }

    /* Content sections */
    .section { margin-top: 16px; }
    .section-title { font-size: 11pt; font-weight: bold; background-color: #f2f2f2; padding: 5px 8px; border-bottom: 2px solid #111; }

    /* Fixed page footer */
    .page-footer { position: fixed; bottom: 5mm; left: 20mm; right: 15mm; font-size: 7.5pt; color: #aaa; border-top: 1px solid #e0e0e0; padding-top: 2mm; display: flex; justify-content: space-between; }
    .page-num::after { content: counter(page); }
    .page-total::after { content: counter(pages); }
</style>
