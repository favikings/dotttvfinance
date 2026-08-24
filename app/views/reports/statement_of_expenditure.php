<?php
/**
 * @var string $from
 * @var string $to
 * @var array{opening_balance: float, funds_received: float, funds_received_count: int,
 *            has_historical_funds: bool, expenditure: float, expenditure_count: int,
 *            has_historical_expenditure: bool, outstanding_liabilities: float,
 *            outstanding_liabilities_count: int, closing_balance: float} $data
 */
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Reports</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Board-ready financial reports, generated straight from the ledger.</p>
    </div>

    <?= report_tabs('statement-of-expenditure', $from, $to) ?>
    <?= date_range_filter('/reports/statement-of-expenditure', $from, $to, '/reports/statement-of-expenditure/pdf', excelAction: '/reports/statement-of-expenditure/excel') ?>
    <?= sync_indicator() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-headline-sm">Statement of Expenditure</h2>
                <p class="text-body-md text-on-surface-variant mt-1">
                    <?= View::e(date('d M Y', strtotime($from))) ?> &ndash; <?= View::e(date('d M Y', strtotime($to))) ?>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <?= metric_card('Opening Balance', naira($data['opening_balance'])) ?>
            <?= metric_card('Closing Balance', naira($data['closing_balance'])) ?>
            <?= metric_card('Funds Received', format_amount($data['funds_received'], 'credit'), $data['funds_received_count'] . ' top-up(s)') ?>
            <?= metric_card('Approved Expenditure', format_amount($data['expenditure'], 'debit'), $data['expenditure_count'] . ' expense(s)') ?>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm">
                <tbody class="divide-y divide-outline-variant">
                    <tr>
                        <td class="px-4 py-3 text-on-surface">Opening Balance <span class="text-on-surface-variant">(as of <?= View::e(date('d/m/Y', strtotime($from . ' -1 day'))) ?>)</span></td>
                        <td class="px-4 py-3 text-on-surface text-right font-medium"><?= naira($data['opening_balance']) ?></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-on-surface">
                            Add: Funds Received in Period
                            <?php if ($data['has_historical_funds']): ?> <?= backfilled_badge(true) ?><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">+ <?= format_amount($data['funds_received'], 'credit') ?></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-on-surface">
                            Less: Approved Expenditure in Period
                            <?php if ($data['has_historical_expenditure']): ?> <?= backfilled_badge(true) ?><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">&minus; <?= format_amount($data['expenditure'], 'debit') ?></td>
                    </tr>
                    <tr class="bg-surface-container-low">
                        <td class="px-4 py-3 text-on-surface font-semibold">Closing Balance</td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold"><?= naira($data['closing_balance']) ?></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-on-surface">
                            Outstanding Liabilities <span class="text-on-surface-variant">(approved, not yet paid, as of <?= View::e(date('d/m/Y', strtotime($to))) ?>)</span>
                        </td>
                        <td class="px-4 py-3 text-warning text-right font-medium"><?= naira($data['outstanding_liabilities']) ?> <span class="text-label-sm text-on-surface-variant">(<?= $data['outstanding_liabilities_count'] ?>)</span></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <p class="text-label-sm text-on-surface-variant mt-4">
            Opening balance, funds received, and approved expenditure are computed from the same date-filtered
            fund_balances calculation (Tech Spec §10) — closing balance always reconciles as opening + received &minus; expenditure.
        </p>
    </div>
</div>
