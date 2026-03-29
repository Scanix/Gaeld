# Payroll Domain

Swiss payroll processing: employee management, salary calculations, and social charge deductions.

## Scope

- **Employees**: employment records linked to organizations
- **Salary Slips**: monthly payroll computations with Swiss deduction rates
- **Payroll Runs**: batch posting of salary journal entries to the ledger
- **Swiss Deductions**: AHV/IV/EO, ALV, BVG, KTG/UVG rate calculations

Uses the Accounting domain's `LedgerService` for journal entry posting via `PostPayrollAction`.
