<?php

declare(strict_types=1);

$router = new Router();

// Any route not explicitly marked public=true requires a valid session —
// Router::dispatch() enforces this centrally (Tech Spec §2's "Auth
// middleware checks session validity" step), so controllers don't each
// need to remember to call it themselves.
$router->get('/', 'DashboardController@index');
$router->get('/dev/permissions', 'DevPermissionsController@index');

// Expenses (Accountant creates, all with expenses.view see the list)
$router->get('/expenses', 'ExpenseController@index');
$router->get('/expenses/create', 'ExpenseController@create');
$router->post('/expenses/create', 'ExpenseController@store');

// GM/Chairman approval queues (Build Prompt 2.1) — queue view is HTML,
// approve/reject are Alpine-driven JSON endpoints per Tech Spec §16.
$router->get('/approvals', 'ApprovalController@index');
$router->post('/approvals/approve', 'ApprovalController@approve');
$router->post('/approvals/reject', 'ApprovalController@reject');

// Bulk Historical Entry (Build Prompt 1.5) — distinct from the live expense
// form above: backfilled paper-book records, inserted directly as approved.
$router->get('/historical-entry/expenses', 'HistoricalEntryController@expenses');
$router->post('/historical-entry/expenses', 'HistoricalEntryController@storeExpenses');
$router->get('/historical-entry/topups', 'HistoricalEntryController@topups');
$router->post('/historical-entry/topups', 'HistoricalEntryController@storeTopups');

// Fund top-ups (Build Prompt 2.3 / Tech Spec §8) — Accountant requests,
// either GM or Chairman clears it with a single sign-off (no sequencing).
$router->get('/fund-topups', 'FundTopupController@index');
$router->get('/fund-topups/create', 'FundTopupController@create');
$router->post('/fund-topups/create', 'FundTopupController@store');
$router->post('/fund-topups/approve', 'FundTopupController@approve');
$router->post('/fund-topups/reject', 'FundTopupController@reject');

// Payments (Build Prompt 2.4 / Tech Spec §9) — Option A resolved: an
// approved expense is sufficient authorization to release payment, so the
// Accountant creates the voucher directly and the expense flips to 'paid'.
$router->get('/payments', 'PaymentController@index');
$router->get('/payments/create/{id}', 'PaymentController@create');
$router->post('/payments/create/{id}', 'PaymentController@store');
$router->get('/payments/{id}', 'PaymentController@show');

// Invoices (Build Prompt 3.1 / PRD §6.2) — Accountant creates/edits, GM
// approves with a single sign-off (no tiered chain), Chairman views,
// Super Admin full CRUD including delete.
$router->get('/invoices', 'InvoiceController@index');
$router->get('/invoices/create', 'InvoiceController@create');
$router->post('/invoices/create', 'InvoiceController@store');
$router->get('/invoices/edit/{id}', 'InvoiceController@edit');
$router->post('/invoices/edit/{id}', 'InvoiceController@update');
$router->post('/invoices/delete/{id}', 'InvoiceController@destroy');
$router->post('/invoices/approve', 'InvoiceController@approve');
$router->post('/invoices/reject', 'InvoiceController@reject');

// Payroll (Build Prompt 3.2 / PRD §6.6) — Accountant creates a run per
// period and adds employees while it's draft, GM approves with a single
// sign-off, then payment_status is tracked per item until the run clears.
$router->get('/payroll', 'PayrollController@index');
$router->get('/payroll/create', 'PayrollController@create');
$router->post('/payroll/create', 'PayrollController@store');
$router->get('/payroll/{id}', 'PayrollController@show');
$router->post('/payroll/{id}/items', 'PayrollController@storeItems');
$router->post('/payroll/{id}/approve', 'PayrollController@approve');
$router->post('/payroll/items/{itemId}/delete', 'PayrollController@deleteItem');
$router->post('/payroll/items/{itemId}/pay', 'PayrollController@payItem');

// Reports (Build Prompt 3.3, 3.3a / PRD §6.7 / Tech Spec §10, §14) —
// read-only, date-range filterable, each with "Export PDF" and "Export Excel"
// companion routes sharing the same underlying data-fetch.
$router->get('/reports', 'ReportController@index');
$router->get('/reports/statement-of-expenditure', 'ReportController@statementOfExpenditure');
$router->get('/reports/statement-of-expenditure/pdf', 'ReportController@statementOfExpenditurePdf');
$router->get('/reports/statement-of-expenditure/excel', 'ReportController@statementOfExpenditureExcel');
$router->get('/reports/fund-ledger', 'ReportController@fundLedger');
$router->get('/reports/fund-ledger/pdf', 'ReportController@fundLedgerPdf');
$router->get('/reports/fund-ledger/excel', 'ReportController@fundLedgerExcel');
$router->get('/reports/profit-loss', 'ReportController@profitLoss');
$router->get('/reports/profit-loss/pdf', 'ReportController@profitLossPdf');
$router->get('/reports/profit-loss/excel', 'ReportController@profitLossExcel');
$router->get('/reports/cash-flow', 'ReportController@cashFlow');
$router->get('/reports/cash-flow/pdf', 'ReportController@cashFlowPdf');
$router->get('/reports/cash-flow/excel', 'ReportController@cashFlowExcel');
$router->get('/reports/departmental-expenses', 'ReportController@departmentalExpenses');
$router->get('/reports/departmental-expenses/pdf', 'ReportController@departmentalExpensesPdf');
$router->get('/reports/departmental-expenses/excel', 'ReportController@departmentalExpensesExcel');
$router->get('/reports/budget-vs-actual', 'ReportController@budgetVsActual');
$router->post('/reports/budget-vs-actual/save', 'ReportController@saveBudgets');
$router->get('/reports/budget-vs-actual/pdf', 'ReportController@budgetVsActualPdf');
$router->get('/reports/budget-vs-actual/excel', 'ReportController@budgetVsActualExcel');
$router->get('/reports/receivables', 'ReportController@receivables');
$router->get('/reports/receivables/pdf', 'ReportController@receivablesPdf');
$router->get('/reports/receivables/excel', 'ReportController@receivablesExcel');
$router->get('/reports/payables', 'ReportController@payables');
$router->get('/reports/payables/pdf', 'ReportController@payablesPdf');
$router->get('/reports/payables/excel', 'ReportController@payablesExcel');

// Audit log viewer (Build Prompt 2.5 / Tech Spec §11) — read-only, filterable.
$router->get('/audit-log', 'AuditLogController@index');

// Web Push subscription (Build Prompt 2.7 / Tech Spec §15a) — any
// authenticated user can register their own browser/device.
$router->post('/push/subscribe', 'PushSubscriptionController@subscribe');

$router->get('/login', 'AuthController@showLogin', public: true);
$router->post('/login', 'AuthController@login', public: true);
$router->post('/logout', 'AuthController@logout');

// Settings (Super Admin only — every action guarded in the controller)
$router->get('/settings', 'SettingsController@index');
$router->get('/settings/departments', 'SettingsController@departments');
$router->post('/settings/departments/create', 'SettingsController@createDepartment');
$router->post('/settings/departments/update', 'SettingsController@updateDepartment');
$router->post('/settings/departments/delete', 'SettingsController@deleteDepartment');
$router->get('/settings/categories', 'SettingsController@categories');
$router->post('/settings/categories/create', 'SettingsController@createCategory');
$router->post('/settings/categories/update', 'SettingsController@updateCategory');
$router->post('/settings/categories/delete', 'SettingsController@deleteCategory');
$router->get('/settings/fund-accounts', 'SettingsController@fundAccounts');
$router->get('/settings/approval-rules', 'SettingsController@approvalRules');
$router->post('/settings/approval-rules/save', 'SettingsController@saveApprovalRules');
$router->get('/settings/users', 'SettingsController@users');
$router->post('/settings/users/create', 'SettingsController@createUser');
$router->post('/settings/users/update', 'SettingsController@updateUser');
$router->post('/settings/users/delete', 'SettingsController@deleteUser');

return $router;
