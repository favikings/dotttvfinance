<?php

declare(strict_types=1);

/**
 * Sidebar nav items — data-driven so adding an item never touches layout HTML.
 * `permission` is a "module.action" string checked against $_SESSION['permissions'];
 * null means always visible to any authenticated user.
 */
return [
    ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => '/', 'permission' => 'dashboard.view'],
    ['label' => 'Expenses', 'icon' => 'receipt', 'href' => '/expenses', 'permission' => 'expenses.view'],
    ['label' => 'Approval Queue', 'icon' => 'list-checks', 'href' => '/approvals', 'permission' => 'expenses.approve'],
    ['label' => 'Historical Entry', 'icon' => 'history', 'href' => '/historical-entry/expenses', 'permission' => 'expenses.create'],
    ['label' => 'Fund Top-Ups', 'icon' => 'arrow-up-circle', 'href' => '/fund-topups', 'permission' => 'fund_topups.view'],
    ['label' => 'Payments', 'icon' => 'banknote', 'href' => '/payments', 'permission' => 'payments.view'],
    ['label' => 'Invoices', 'icon' => 'file-text', 'href' => '/invoices', 'permission' => 'invoices.view'],
    ['label' => 'Payroll', 'icon' => 'users', 'href' => '/payroll', 'permission' => 'payroll.view'],
    ['label' => 'Reports', 'icon' => 'bar-chart-3', 'href' => '/reports', 'permission' => 'reports.view'],
    ['label' => 'Audit Log', 'icon' => 'history', 'href' => '/audit-log', 'permission' => 'audit_log.view'],
    ['label' => 'Settings', 'icon' => 'settings', 'href' => '/settings', 'permission' => 'settings.view'],
];
