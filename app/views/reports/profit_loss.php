<?php
/**
 * @var string $from
 * @var string $to
 * @var array{gross_revenue: float, vat_collected: float, wht_withheld: float, net_revenue: float,
 *            invoice_count: int, total_expenses: float, expense_count: int,
 *            has_historical_expenses: bool, net_profit: float} $data
 */
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Reports</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Board-ready financial reports, generated straight from the ledger.</p>
    </div>

    <?= report_tabs('profit-loss', $from, $to) ?>
    <?= date_range_filter('/reports/profit-loss', $from, $to, '/reports/profit-loss/pdf', excelAction: '/reports/profit-loss/excel') ?>
    <?= sync_indicator() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
        <div class="mb-6">
            <h2 class="text-headline-sm">Profit &amp; Loss</h2>
            <p class="text-body-md text-on-surface-variant mt-1">
                <?= View::e(date('d M Y', strtotime($from))) ?> &ndash; <?= View::e(date('d M Y', strtotime($to))) ?>
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-6">
            <?= metric_card('Net Revenue', naira($data['net_revenue']), $data['invoice_count'] . ' approved invoice(s)') ?>
            <?= metric_card('Total Expenses', format_amount($data['total_expenses'], 'debit'), $data['expense_count'] . ' expense(s)') ?>
            <?= metric_card('Net Profit', naira($data['net_profit'])) ?>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm">
                <tbody class="divide-y divide-outline-variant">
                    <tr>
                        <td class="px-4 py-3 text-on-surface font-medium">Revenue</td>
                        <td class="px-4 py-3"></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-on-surface-variant pl-8">Gross Invoiced (approved)</td>
                        <td class="px-4 py-3 text-on-surface text-right"><?= naira($data['gross_revenue']) ?></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-on-surface-variant pl-8">Less: WHT Withheld at Source</td>
                        <td class="px-4 py-3 text-right">&minus; <?= format_amount($data['wht_withheld'], 'debit') ?></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-on-surface-variant pl-8">VAT Collected <span class="text-label-sm">(pass-through, excluded from revenue)</span></td>
                        <td class="px-4 py-3 text-on-surface-variant text-right"><?= naira($data['vat_collected']) ?></td>
                    </tr>
                    <tr class="bg-surface-container-low">
                        <td class="px-4 py-3 text-on-surface font-semibold">Net Revenue</td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold"><?= naira($data['net_revenue']) ?></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-on-surface font-medium">
                            Expenses
                            <?php if ($data['has_historical_expenses']): ?> <?= backfilled_badge(true) ?><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">&minus; <?= format_amount($data['total_expenses'], 'debit') ?></td>
                    </tr>
                    <tr class="bg-surface-container-low">
                        <td class="px-4 py-3 text-on-surface font-semibold">Net Profit</td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold"><?= naira($data['net_profit']) ?></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
