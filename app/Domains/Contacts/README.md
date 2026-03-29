# Contacts Domain

Manages customers, suppliers, and their contact persons for the organization.

## Scope

- **Customers**: invoicing recipients; referenced by Invoicing domain
- **Suppliers**: expense vendors; referenced by Expenses domain
- **Contact Persons**: people associated with customers or suppliers

Intentionally minimal — contacts serve as a reference domain for other business domains
(invoicing, expenses, banking reconciliation). No standalone business logic beyond CRUD.
