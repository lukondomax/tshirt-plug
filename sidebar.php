<?php

$sidebar_active_page = basename($_SERVER['PHP_SELF']);

$sidebar_groups = [
    [
        ['page' => 'dashboard.php', 'label' => '🏠 Dashboard'],
        ['page' => 'reports.php', 'label' => '📊 Received Clothes'],
        ['page' => 'records.php', 'label' => '📁 Store Records']
    ],
    [
        ['page' => 'payments.php', 'label' => '💰 Payments'],
        ['page' => 'expenditures.php', 'label' => '💸 Expenditures'],
        ['page' => 'long_term_loans.php', 'label' => '💳 Long Term Loans'],
        ['page' => 'daily_report.php', 'label' => '📅 Daily Report']
    ],
    [
        ['page' => 'logout.php', 'label' => '🚪 Logout']
    ]
];

?>

<aside class="sidebar">

    <h2>T-SHIRTS PLUG</h2>

    <div class="sidebar-subtitle">
        BUSINESS MANAGEMENT SYSTEM
    </div>

    <nav aria-label="Main navigation">

        <?php foreach ($sidebar_groups as $group_index => $group): ?>

            <?php if ($group_index > 0): ?>
                <div class="sidebar-divider"></div>
            <?php endif; ?>

            <?php foreach ($group as $item): ?>

                <?php $is_active = $sidebar_active_page === $item['page']; ?>

                <a
                    href="<?= htmlspecialchars($item['page']) ?>"
                    class="<?= $is_active ? 'active' : '' ?>"
                    <?= $is_active ? 'aria-current="page"' : '' ?>
                >
                    <?= htmlspecialchars($item['label']) ?>
                </a>

            <?php endforeach; ?>

        <?php endforeach; ?>

    </nav>

    <div class="sidebar-footer">
        T-SHIRT PLUG MANAGEMENT SYSTEM
        <br><br>
        Version 2.0
    </div>

</aside>
