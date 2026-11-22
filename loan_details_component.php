<?php

$loan_id = (int)($_GET['loan_details'] ?? 0);

$loan_stmt = loan_prepare(
    $conn,
    "SELECT l.*, c.id AS borrower_id, c.name AS borrower_name, c.phone,
            COALESCE(p.paid_amount, 0) AS paid_amount,
            COALESCE(w.writeoff_amount, 0) AS writeoff_amount,
            dl.deleted_at AS logged_deleted_at, dl.deleted_by_username
     FROM long_term_loans l
     LEFT JOIN loan_customers c ON c.id = l.customer_id
     LEFT JOIN (SELECT loan_id, SUM(amount) AS paid_amount FROM loan_repayment_allocations GROUP BY loan_id) p ON p.loan_id = l.id
     LEFT JOIN (SELECT loan_id, SUM(amount) AS writeoff_amount FROM loan_writeoffs GROUP BY loan_id) w ON w.loan_id = l.id
     LEFT JOIN loan_deletion_logs dl ON dl.loan_id = l.id
     WHERE l.id = ?",
    "Unable to load loan details"
);
mysqli_stmt_bind_param($loan_stmt, "i", $loan_id);
loan_execute($loan_stmt, "Unable to load loan details");
$loan = mysqli_fetch_assoc(mysqli_stmt_get_result($loan_stmt));
mysqli_stmt_close($loan_stmt);

if (!$loan) {
    http_response_code(404);
    exit("Loan not found.");
}

$paid = (float)$loan['paid_amount'];
$written_off = (float)$loan['writeoff_amount'];
$legacy = (int)$loan['legacy_closed'] === 1;
$deleted = $loan['deleted_at'] !== null;
$balance = max(0, (float)$loan['amount'] - $paid - $written_off);
$status_label = $deleted ? 'Deleted' : ($legacy ? 'Legacy closed' : ($balance <= 0.00001 ? ($written_off > 0 ? 'Written off' : 'Paid') : 'Active'));
$status_class = $deleted ? 'deleted' : ($legacy ? 'legacy' : ($balance <= 0.00001 ? ($written_off > 0 ? 'writeoff' : 'cleared') : 'active'));

$credit_stmt = loan_prepare(
    $conn,
    "SELECT COALESCE(SUM(r.amount - COALESCE(a.allocated, 0)), 0) AS credit
     FROM loan_repayments r
     LEFT JOIN (
         SELECT repayment_id, SUM(amount) AS allocated
         FROM loan_repayment_allocations GROUP BY repayment_id
     ) a ON a.repayment_id = r.id
     WHERE r.customer_id = ?",
    "Unable to calculate borrower credit"
);
$customer_id = (int)$loan['customer_id'];
mysqli_stmt_bind_param($credit_stmt, "i", $customer_id);
loan_execute($credit_stmt, "Unable to calculate borrower credit");
$borrower_credit = mysqli_fetch_assoc(mysqli_stmt_get_result($credit_stmt));
mysqli_stmt_close($credit_stmt);

$payments_stmt = loan_prepare(
    $conn,
    "SELECT r.payment_date, r.amount, r.payment_method, r.reference, r.notes,
            a.amount AS allocated_to_loan, u.username AS recorded_by
     FROM loan_repayment_allocations a
     JOIN loan_repayments r ON r.id = a.repayment_id
     LEFT JOIN users u ON u.id = r.recorded_by_user_id
     WHERE a.loan_id = ?
     ORDER BY r.payment_date DESC, r.id DESC",
    "Unable to load loan repayment history"
);
mysqli_stmt_bind_param($payments_stmt, "i", $loan_id);
loan_execute($payments_stmt, "Unable to load loan repayment history");
$payments = mysqli_stmt_get_result($payments_stmt);

$writeoffs_stmt = loan_prepare(
    $conn,
    "SELECT w.amount, w.reason, w.created_at, u.username AS recorded_by
     FROM loan_writeoffs w
     LEFT JOIN users u ON u.id = w.recorded_by_user_id
     WHERE w.loan_id = ?
     ORDER BY w.created_at DESC, w.id DESC",
    "Unable to load loan write-off history"
);
mysqli_stmt_bind_param($writeoffs_stmt, "i", $loan_id);
loan_execute($writeoffs_stmt, "Unable to load loan write-off history");
$writeoffs = mysqli_stmt_get_result($writeoffs_stmt);

?>
<div class="loan-detail" data-loan-id="<?= $loan_id ?>" data-loan-title="Loan #<?= $loan_id ?> — <?= htmlspecialchars($loan['borrower_name'] ?? $loan['customer_name'], ENT_QUOTES) ?>">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <section class="detail-profile">
        <div>
            <span class="detail-label">Borrower</span>
            <strong><?= htmlspecialchars($loan['borrower_name'] ?? $loan['customer_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">Phone</span>
            <strong><?= htmlspecialchars($loan['phone'] ?? 'Not recorded') ?></strong>
        </div>
        <div>
            <span class="detail-label">Loan Status</span>
            <span class="status <?= $status_class ?>"><?= htmlspecialchars($status_label) ?></span>
        </div>
    </section>

    <section class="detail-summary">
        <div><span>Principal</span><strong>TSh <?= number_format((float)$loan['amount'], 2) ?></strong></div>
        <div><span>Paid</span><strong><?= $legacy ? 'Not tracked' : 'TSh ' . number_format($paid, 2) ?></strong></div>
        <div><span>Written Off</span><strong><?= $legacy ? '—' : 'TSh ' . number_format($written_off, 2) ?></strong></div>
        <div><span>Balance</span><strong><?= $legacy ? 'Not tracked' : 'TSh ' . number_format($balance, 2) ?></strong></div>
        <div><span>Borrower Credit</span><strong>TSh <?= number_format((float)$borrower_credit['credit'], 2) ?></strong></div>
    </section>

    <section class="loan-info-grid">
        <div><span>Loan Date</span><strong><?= htmlspecialchars($loan['loan_date']) ?></strong></div>
        <div><span>Disbursement Method</span><strong><?= htmlspecialchars($loan['payment_method']) ?></strong></div>
        <div><span>Repayment From</span><strong><?= htmlspecialchars($loan['from_date']) ?></strong></div>
        <div><span>Duration</span><strong><?= htmlspecialchars($loan['duration']) ?></strong></div>
        <div><span>Closed Date</span><strong><?= htmlspecialchars($loan['clearance_date'] ?? '—') ?></strong></div>
        <div class="loan-info-wide"><span>Particulars</span><strong><?= nl2br(htmlspecialchars($loan['particular'])) ?></strong></div>
    </section>

    <?php if ($deleted): ?>
        <p class="note">This loan was deleted from active records on <?= htmlspecialchars($loan['logged_deleted_at'] ?? $loan['deleted_at']) ?> by <?= htmlspecialchars($loan['deleted_by_username'] ?? 'Unknown staff') ?>. Its ledger history is retained.</p>
    <?php else: ?>
        <div class="drawer-actions">
            <button type="button" class="action-button action-edit" data-drawer-action="edit">Edit Details</button>
            <?php if (!$legacy && $loan['clearance'] === 'Active'): ?>
                <button type="button" class="action-button action-pay" data-drawer-action="repay">Record Repayment</button>
                <button type="button" class="action-button action-writeoff" data-drawer-action="writeoff" data-balance="<?= htmlspecialchars((string)$balance, ENT_QUOTES) ?>">Write Off</button>
            <?php endif; ?>
            <form method="POST" class="inline-action" onsubmit="return confirm('Delete this loan from active records? Its repayment history will be kept and the deletion logged.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="loan_id" value="<?= $loan_id ?>">
                <input type="hidden" name="return_loan_id" value="<?= $loan_id ?>">
                <button class="action-button action-delete" type="submit" name="delete_loan">Delete Loan</button>
            </form>
        </div>

        <form method="POST" class="drawer-action-form" id="drawer-edit-form" hidden>
        <h3>Edit Loan Details</h3>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="return_loan_id" value="<?= $loan_id ?>">
        <input type="hidden" name="id" value="<?= $loan_id ?>">
        <div class="drawer-form-grid">
            <label>Borrower Name<input type="text" name="customer_name" value="<?= htmlspecialchars($loan['borrower_name'] ?? $loan['customer_name']) ?>" required></label>
            <label>Phone<input type="tel" name="phone" maxlength="32" value="<?= htmlspecialchars($loan['phone'] ?? '') ?>" required></label>
            <label>Loan Date<input type="date" name="loan_date" value="<?= htmlspecialchars($loan['loan_date']) ?>" required></label>
            <label>Loan Amount<input type="number" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($loan['amount']) ?>" <?= ($paid > 0 || $written_off > 0 || $legacy) ? 'readonly' : '' ?> required></label>
            <label>Disbursement Method<select name="payment_method" required><?php foreach ($allowed_payment_methods as $method): ?><option value="<?= htmlspecialchars($method) ?>" <?= $loan['payment_method'] === $method ? 'selected' : '' ?>><?= htmlspecialchars($method) ?></option><?php endforeach; ?></select></label>
            <label>Repayment From<input type="date" name="from_date" value="<?= htmlspecialchars($loan['from_date']) ?>" required></label>
            <label>Duration<input type="text" name="duration" value="<?= htmlspecialchars($loan['duration']) ?>" required></label>
            <label class="drawer-form-wide">Particulars<textarea name="particular" required><?= htmlspecialchars($loan['particular']) ?></textarea></label>
        </div>
        <?php if ($paid > 0 || $written_off > 0 || $legacy): ?><p class="note">Principal cannot be changed after repayments, write-offs, or legacy closure.</p><?php endif; ?>
        <button type="submit" name="update_loan">Save Changes</button>
        </form>

        <?php if (!$legacy && $loan['clearance'] === 'Active'): ?>
            <form method="POST" class="drawer-action-form" id="drawer-repayment-form" hidden>
            <h3>Record Repayment</h3>
            <p class="note">The payment is applied to the borrower’s oldest active loan first. Any excess remains as credit.</p>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="return_loan_id" value="<?= $loan_id ?>">
            <input type="hidden" name="loan_id" value="<?= $loan_id ?>">
            <div class="drawer-form-grid">
                <label>Payment Date<input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></label>
                <label>Amount<input type="number" name="amount" min="0.01" step="0.01" required></label>
                <label>Payment Method<select name="payment_method" required><option value="">Select</option><?php foreach ($allowed_payment_methods as $method): ?><option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option><?php endforeach; ?></select></label>
                <label>Reference (optional)<input type="text" name="reference" maxlength="120"></label>
                <label class="drawer-form-wide">Notes (optional)<textarea name="notes"></textarea></label>
            </div>
            <button type="submit" name="record_repayment">Save Repayment</button>
            </form>

            <form method="POST" class="drawer-action-form" id="drawer-writeoff-form" hidden>
            <h3>Write Off Balance</h3>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="return_loan_id" value="<?= $loan_id ?>">
            <input type="hidden" name="loan_id" value="<?= $loan_id ?>">
            <div class="drawer-form-grid">
                <label>Amount<input type="number" name="amount" id="drawer-writeoff-amount" min="0.01" step="0.01" max="<?= htmlspecialchars((string)$balance, ENT_QUOTES) ?>" required></label>
                <label class="drawer-form-wide">Reason<textarea name="reason" maxlength="1000" required></textarea></label>
            </div>
            <button type="submit" name="write_off">Save Write-off</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <h3>Payment History for This Loan</h3>
    <div class="table-container drawer-table-container">
        <table>
            <thead><tr><th>Date</th><th>Received</th><th>Method</th><th>Applied to This Loan</th><th>Reference / Notes</th><th>Recorded By</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($payments) > 0): ?>
                <?php while ($payment = mysqli_fetch_assoc($payments)): ?>
                    <tr>
                        <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                        <td>TSh <?= number_format((float)$payment['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                        <td>TSh <?= number_format((float)$payment['allocated_to_loan'], 2) ?></td>
                        <td><?= htmlspecialchars(trim(($payment['reference'] ?? '') . ' ' . ($payment['notes'] ?? '')) ?: '—') ?></td>
                        <td><?= htmlspecialchars($payment['recorded_by'] ?? 'Staff account unavailable') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No payments have been applied to this loan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3>Write-off History for This Loan</h3>
    <div class="table-container drawer-table-container">
        <table>
            <thead><tr><th>Date</th><th>Amount</th><th>Reason</th><th>Recorded By</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($writeoffs) > 0): ?>
                <?php while ($writeoff = mysqli_fetch_assoc($writeoffs)): ?>
                    <tr><td><?= htmlspecialchars($writeoff['created_at']) ?></td><td>TSh <?= number_format((float)$writeoff['amount'], 2) ?></td><td><?= htmlspecialchars($writeoff['reason']) ?></td><td><?= htmlspecialchars($writeoff['recorded_by'] ?? 'Staff account unavailable') ?></td></tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No write-offs have been recorded for this loan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
