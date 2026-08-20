<?php
/**
 * @var string $from
 * @var string $to
 * @var array{cash_in: float, cash_in_count: int, has_historical_in: bool, cash_out: float,
 *            cash_out_paid_count: int, cash_out_historical_count: int, has_historical_out: bool,
 *            net_cash_flow: float} $data
 */
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Reports</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Board-ready financial reports, generated straight from the ledger.</p>
    </div>

    <?= report_tabs('cash-flow', $from, $to) ?>
    <?= date_range_filter('/reports/cash-flow', $from, $to, '/reports/cash-flow/pdf', excelAction: '/reports/cash-flow/excel') ?>
    <?= sync_indicator() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
        <div class="mb-6">
            <h2 class="text-headline-sm">Cash Flow</h2>
            <p class="text-body-md text-on-surface-variant mt-1">
                <?= View::e(date('d M Y', strtotime($from))) ?> &ndash; <?= View::e(date('d M Y', strtotime($to))) ?>
                &middot; actual cash movement (top-up date / payment date), not accrual
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-6">
            <?= metric_card('Cash In', naira($data['cash_in']), $data['cash_in_count'] . ' top-up(s)') ?>
            <?= metric_card('Cash Out', naira($data['cash_out']), ($data['cash_out_paid_count'] + $data['cash_out_historical_count']) . ' disbursement(s)') ?>
            <?= metric_card('Net Cash Flow', naira($data['net_cash_flow'])) ?>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm">
                <tbody class="divide-y divide-outline-variant">
                    <tr>
                        <td class="px-4 py-3 text-on-surface">
                            Cash In &mdash; Fund Top-Ups Received
                            <?php if ($data['has_historical_in']): ?> <?= backfilled_badge(true) ?><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-success text-right font-medium">+ <?= naira($data['cash_in']) ?></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-on-surface">
                            Cash Out &mdash; Payments Disbursed
                            <?php if ($data['has_historical_out']): ?> <?= backfilled_badge(true) ?><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-error text-right font-medium">&minus; <?= naira($data['cash_out']) ?></td>
                    </tr>
                    <tr class="bg-surface-container-low">
                        <td class="px-4 py-3 text-on-surface font-semibold">Net Cash Flow</td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold"><?= naira($data['net_cash_flow']) ?></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <p class="text-label-sm text-on-surface-variant mt-4">
            Unlike the Statement of Expenditure (which counts an expense the moment it's approved), Cash Flow tracks
            money actually moving: top-ups on their recorded date, expenses on their payment voucher's paid date
            (or their own date for backfilled entries, which were already spent before this system existed).
        </p>
    </div>
</div>
