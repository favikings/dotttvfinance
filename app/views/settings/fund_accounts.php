<?php /** @var array<int, array> $fundAccounts */ ?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Settings</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Departments, categories, approval rules, and users.</p>
    </div>

    <?= settings_tabs('fund_accounts') ?>
    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
        <div class="overflow-x-auto table-scroll">
            <table class="w-full text-sm min-w-[560px]">
            <thead class="bg-surface-container-low border-b border-outline-variant">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Fund account</th>
                    <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Top-ups</th>
                    <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Spent</th>
                    <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Current balance</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                <?php if (empty($fundAccounts)): ?>
                    <tr>
                        <td class="px-4 py-12 text-center text-on-surface-variant" colspan="5">
                            No fund accounts found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fundAccounts as $account): ?>
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($account['name']) ?></td>
                            <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($account['total_topups']) ?></td>
                            <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($account['total_spent']) ?></td>
                            <td class="px-4 py-3 text-on-surface text-right font-semibold whitespace-nowrap"><?= naira($account['current_balance']) ?></td>
                            <td class="px-4 py-3"><?= status_badge($account['is_active'] ? 'active' : 'inactive') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
        <h2 class="text-headline-sm mb-1">About the operating float</h2>
        <p class="text-body-md text-on-surface-variant">
            DOTT TV runs a single revolving float in v1. The balance above is computed live from approved
            top-ups minus approved or historical expenses — never a manually stored number. To add money to
            the float, use <span class="font-medium text-on-surface">Fund Top-Ups</span>; the fund account
            itself is not editable here.
        </p>
    </div>
</div>
