# Reporting Domain

Financial reports, dashboards, aging analysis, and data export for the organization.

## Scope

- **Dashboard**: KPI widgets, revenue/expense charts, recent activity
- **Financial Reports**: balance sheet, profit & loss, trial balance
- **Aging Reports**: accounts receivable and payable aging analysis
- **Export**: PDF and CSV export of financial reports and accounting data

## Services

- **DashboardService** — Aggregates KPIs and chart data for the main dashboard
- **ReportingService** — Generates balance sheet, P&L, and trial balance reports
- **AgingReportService** — Calculates receivable/payable aging buckets
- **AccountingExportService** — Formats accounting data for external tools
- **ExportReportService** — PDF/CSV rendering of report output

## Integration

- Reads from Accounting domain's `LedgerQueryService` for all financial data
- References Invoicing (receivables) and Expenses (payables) for aging reports
- Dashboard injects `ChecklistService` from Organizations domain for onboarding status
