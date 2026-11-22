<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include "database.php";

$allowed_payment_methods = ['none','Cash', 'Bank', 'Mobile'];
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

if (empty($_SESSION['user_id'])) {
    $username = $_SESSION['username'] ?? 'max';
    $user_stmt = loan_prepare(
        $conn,
        "SELECT id FROM users WHERE username = ?",
        "Unable to load signed-in staff account"
    );
    mysqli_stmt_bind_param($user_stmt, "s", $username);
    loan_execute($user_stmt, "Unable to load signed-in staff account");
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
    mysqli_stmt_close($user_stmt);
    if ($user) {
        $_SESSION['user_id'] = (int)$user['id'];
    }
}

function loan_redirect()
{
    $loan_id = (int)($_POST['return_loan_id'] ?? 0);
    if ($loan_id > 0) {
        $url = "long_term_loans.php?view_loan_id=" . $loan_id;
    } else {
        $url = "long_term_loans.php";
    }
    header("Location: " . $url);
    exit();
}

function loan_require_csrf()
{
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        $_SESSION['error'] = "Your session expired. Please try again.";
        loan_redirect();
    }
}

function loan_prepare($conn, $sql, $error_message)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        die($error_message . ": " . mysqli_error($conn));
    }
    return $stmt;
}

function loan_execute($stmt, $error_message)
{
    if (!mysqli_stmt_execute($stmt)) {
        die($error_message . ": " . mysqli_stmt_error($stmt));
    }
}

function normalize_phone($phone)
{
    return preg_replace('/[\s()-]/', '', trim($phone));
}

function valid_loan_date($date)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
        return false;
    }
    return checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]);
}

function valid_loan_amount($amount)
{
    return is_string($amount) &&
        preg_match('/^\d+(?:\.\d{1,2})?$/', trim($amount)) &&
        (float)$amount > 0 &&
        (float)$amount <= 9999999999.99;
}

function get_or_create_borrower($conn, $name, $phone)
{
    $stmt = loan_prepare(
        $conn,
        "INSERT INTO loan_customers (name, phone)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name)",
        "Unable to save borrower"
    );
    mysqli_stmt_bind_param($stmt, "ss", $name, $phone);
    loan_execute($stmt, "Unable to save borrower");
    mysqli_stmt_close($stmt);

    $stmt = loan_prepare(
        $conn,
        "SELECT id FROM loan_customers WHERE phone = ?",
        "Unable to find borrower"
    );
    mysqli_stmt_bind_param($stmt, "s", $phone);
    loan_execute($stmt, "Unable to find borrower");
    $borrower = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$borrower) {
        die("Unable to find the saved borrower.");
    }
    return (int)$borrower['id'];
}

function get_loan_ledger_totals($conn, $loan_id)
{
    $stmt = loan_prepare(
        $conn,
        "SELECT
            COALESCE((SELECT SUM(amount) FROM loan_repayment_allocations WHERE loan_id = ?), 0) AS paid,
            COALESCE((SELECT SUM(amount) FROM loan_writeoffs WHERE loan_id = ?), 0) AS written_off",
        "Unable to calculate loan balance"
    );
    mysqli_stmt_bind_param($stmt, "ii", $loan_id, $loan_id);
    loan_execute($stmt, "Unable to calculate loan balance");
    $totals = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $totals;
}

function apply_available_borrower_credit($conn, $customer_id)
{
    $receipts_stmt = loan_prepare(
        $conn,
        "SELECT id, amount, payment_date
         FROM loan_repayments
         WHERE customer_id = ?
         ORDER BY payment_date, id
         FOR UPDATE",
        "Unable to load borrower credit"
    );
    mysqli_stmt_bind_param($receipts_stmt, "i", $customer_id);
    loan_execute($receipts_stmt, "Unable to load borrower credit");
    $receipts = mysqli_stmt_get_result($receipts_stmt);

    while ($receipt = mysqli_fetch_assoc($receipts)) {
        $receipt_id = (int)$receipt['id'];
        $allocated_stmt = loan_prepare(
            $conn,
            "SELECT COALESCE(SUM(amount), 0) AS allocated
             FROM loan_repayment_allocations
             WHERE repayment_id = ?",
            "Unable to calculate borrower credit"
        );
        mysqli_stmt_bind_param($allocated_stmt, "i", $receipt_id);
        loan_execute($allocated_stmt, "Unable to calculate borrower credit");
        $allocated_row = mysqli_fetch_assoc(mysqli_stmt_get_result($allocated_stmt));
        mysqli_stmt_close($allocated_stmt);
        $available = (float)$receipt['amount'] - (float)$allocated_row['allocated'];
        if ($available <= 0) {
            continue;
        }

        $loans_stmt = loan_prepare(
            $conn,
            "SELECT id, amount
             FROM long_term_loans
             WHERE customer_id = ?
               AND clearance = 'Active'
               AND legacy_closed = 0
               AND deleted_at IS NULL
             ORDER BY loan_date, id
             FOR UPDATE",
            "Unable to load active borrower loans"
        );
        mysqli_stmt_bind_param($loans_stmt, "i", $customer_id);
        loan_execute($loans_stmt, "Unable to load active borrower loans");
        $loans = mysqli_stmt_get_result($loans_stmt);

        while ($loan = mysqli_fetch_assoc($loans)) {
            if ($available <= 0) {
                break;
            }

            $loan_id = (int)$loan['id'];
            $totals = get_loan_ledger_totals($conn, $loan_id);
            $balance = max(
                0,
                (float)$loan['amount'] - (float)$totals['paid'] - (float)$totals['written_off']
            );
            if ($balance <= 0) {
                continue;
            }

            $allocation = min($available, $balance);
            $allocation_stmt = loan_prepare(
                $conn,
                "INSERT INTO loan_repayment_allocations (repayment_id, loan_id, amount)
                 VALUES (?, ?, ?)",
                "Unable to allocate repayment"
            );
            $receipt_id = (int)$receipt['id'];
            mysqli_stmt_bind_param($allocation_stmt, "iid", $receipt_id, $loan_id, $allocation);
            loan_execute($allocation_stmt, "Unable to allocate repayment");
            mysqli_stmt_close($allocation_stmt);
            $available -= $allocation;

            if ($balance - $allocation <= 0.00001) {
                $date = $receipt['payment_date'];
                $close_stmt = loan_prepare(
                    $conn,
                    "UPDATE long_term_loans
                     SET clearance = 'Cleared', clearance_date = ?
                     WHERE id = ? AND legacy_closed = 0",
                    "Unable to close fully-paid loan"
                );
                mysqli_stmt_bind_param($close_stmt, "si", $date, $loan_id);
                loan_execute($close_stmt, "Unable to close fully-paid loan");
                mysqli_stmt_close($close_stmt);
            }
        }
        mysqli_stmt_close($loans_stmt);
    }
    mysqli_stmt_close($receipts_stmt);
}

if (isset($_GET['loan_details'])) {
    header('Content-Type: text/html; charset=utf-8');
    include 'loan_details_component.php';
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    loan_require_csrf();

    if (isset($_POST['add_loan']) || isset($_POST['update_loan'])) {
        $is_update = isset($_POST['update_loan']);
        $id = (int)($_POST['id'] ?? 0);
        $loan_date = trim($_POST['loan_date'] ?? '');
        $customer_name = trim($_POST['customer_name'] ?? '');
        $phone = normalize_phone($_POST['phone'] ?? '');
        $existing_customer_id = $is_update ? 0 : (int)($_POST['existing_customer_id'] ?? 0);
        $amount_input = trim($_POST['amount'] ?? '');
        $amount = (float)$amount_input;
        $payment_method = trim($_POST['payment_method'] ?? '');
        $particular = trim($_POST['particular'] ?? '');
        $from_date = trim($_POST['from_date'] ?? '');
        $duration = trim($_POST['duration'] ?? '');

        if ($existing_customer_id > 0) {
            $existing_customer_stmt = loan_prepare(
                $conn,
                "SELECT name, phone FROM loan_customers WHERE id = ?",
                "Unable to load selected borrower"
            );
            mysqli_stmt_bind_param($existing_customer_stmt, "i", $existing_customer_id);
            loan_execute($existing_customer_stmt, "Unable to load selected borrower");
            $existing_customer = mysqli_fetch_assoc(mysqli_stmt_get_result($existing_customer_stmt));
            mysqli_stmt_close($existing_customer_stmt);
            if (!$existing_customer) {
                $_SESSION['error'] = "The selected customer could not be found. Please choose again.";
                loan_redirect();
            }
            $customer_name = $existing_customer['name'];
            $phone = normalize_phone($existing_customer['phone'] ?? '');
        }

        if (
            ($is_update && $id <= 0) ||
            !valid_loan_date($loan_date) ||
            $customer_name === '' ||
            $phone === '' ||
            strlen($phone) > 32 ||
            !valid_loan_amount($amount_input) ||
            !in_array($payment_method, $allowed_payment_methods, true) ||
            $particular === '' ||
            !valid_loan_date($from_date) ||
            $duration === ''
        ) {
            $_SESSION['error'] = "Please complete all loan information, including a valid borrower phone number.";
            loan_redirect();
        }

        mysqli_begin_transaction($conn);
        $customer_id = $existing_customer_id > 0
            ? $existing_customer_id
            : get_or_create_borrower($conn, $customer_name, $phone);

        if ($is_update) {
            $current_stmt = loan_prepare(
                $conn,
                "SELECT amount, legacy_closed FROM long_term_loans WHERE id = ? AND deleted_at IS NULL FOR UPDATE",
                "Unable to load loan"
            );
            mysqli_stmt_bind_param($current_stmt, "i", $id);
            loan_execute($current_stmt, "Unable to load loan");
            $current = mysqli_fetch_assoc(mysqli_stmt_get_result($current_stmt));
            mysqli_stmt_close($current_stmt);
            if (!$current) {
                mysqli_rollback($conn);
                $_SESSION['error'] = "Loan not found.";
                loan_redirect();
            }

            $ledger = get_loan_ledger_totals($conn, $id);
            if (
                (
                    (float)$ledger['paid'] > 0 ||
                    (float)$ledger['written_off'] > 0 ||
                    (int)$current['legacy_closed'] === 1
                ) &&
                abs((float)$current['amount'] - $amount) > 0.00001
            ) {
                mysqli_rollback($conn);
                $_SESSION['error'] = "The principal cannot be changed for a loan with repayment history or a legacy-closed status.";
                loan_redirect();
            }

            $stmt = loan_prepare(
                $conn,
                "UPDATE long_term_loans
                 SET loan_date = ?, customer_name = ?, customer_id = ?, amount = ?,
                     payment_method = ?, particular = ?, from_date = ?, duration = ?
                 WHERE id = ? AND deleted_at IS NULL",
                "Unable to update loan"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssidssssi",
                $loan_date,
                $customer_name,
                $customer_id,
                $amount,
                $payment_method,
                $particular,
                $from_date,
                $duration,
                $id
            );
            loan_execute($stmt, "Unable to update loan");
            mysqli_stmt_close($stmt);
            apply_available_borrower_credit($conn, $customer_id);
            mysqli_commit($conn);
            $_SESSION['success'] = "Loan updated successfully.";
        } else {
            $stmt = loan_prepare(
                $conn,
                "INSERT INTO long_term_loans
                    (loan_date, customer_name, customer_id, amount, payment_method,
                     particular, from_date, duration, clearance, legacy_closed)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', 0)",
                "Unable to add loan"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssidssss",
                $loan_date,
                $customer_name,
                $customer_id,
                $amount,
                $payment_method,
                $particular,
                $from_date,
                $duration
            );
            loan_execute($stmt, "Unable to add loan");
            mysqli_stmt_close($stmt);
            apply_available_borrower_credit($conn, $customer_id);
            mysqli_commit($conn);
            $_SESSION['success'] = "Long term loan added successfully.";
        }
        loan_redirect();
    }

    if (isset($_POST['record_repayment'])) {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        $payment_date = trim($_POST['payment_date'] ?? '');
        $amount_input = trim($_POST['amount'] ?? '');
        $amount = (float)$amount_input;
        $payment_method = trim($_POST['payment_method'] ?? '');
        $reference = trim($_POST['reference'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $recorded_by = (int)($_SESSION['user_id'] ?? 0);

        if (
            $loan_id <= 0 || !valid_loan_amount($amount_input) ||
            !valid_loan_date($payment_date) ||
            !in_array($payment_method, $allowed_payment_methods, true) ||
            $recorded_by <= 0 || strlen($reference) > 120
        ) {
            $_SESSION['error'] = "Enter a valid repayment date, amount and payment method.";
            loan_redirect();
        }

        mysqli_begin_transaction($conn);
        $loan_stmt = loan_prepare(
            $conn,
            "SELECT customer_id, clearance, legacy_closed
             FROM long_term_loans WHERE id = ? AND deleted_at IS NULL",
            "Unable to find loan"
        );
        mysqli_stmt_bind_param($loan_stmt, "i", $loan_id);
        loan_execute($loan_stmt, "Unable to find loan");
        $loan = mysqli_fetch_assoc(mysqli_stmt_get_result($loan_stmt));
        mysqli_stmt_close($loan_stmt);

        if (!$loan || $loan['clearance'] !== 'Active' || (int)$loan['legacy_closed'] === 1) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Repayments can only be recorded against an active loan.";
            loan_redirect();
        }

        $customer_id = (int)$loan['customer_id'];
        $receipt_stmt = loan_prepare(
            $conn,
            "INSERT INTO loan_repayments
                (customer_id, payment_date, amount, payment_method, reference, notes, recorded_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            "Unable to record repayment"
        );
        mysqli_stmt_bind_param(
            $receipt_stmt,
            "isdsssi",
            $customer_id,
            $payment_date,
            $amount,
            $payment_method,
            $reference,
            $notes,
            $recorded_by
        );
        loan_execute($receipt_stmt, "Unable to record repayment");
        mysqli_stmt_close($receipt_stmt);
        apply_available_borrower_credit($conn, $customer_id);
        mysqli_commit($conn);
        $_SESSION['success'] = "Repayment recorded and allocated to the borrower's oldest active loans. Any excess remains as borrower credit.";
        loan_redirect();
    }

    if (isset($_POST['write_off'])) {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        $amount_input = trim($_POST['amount'] ?? '');
        $amount = (float)$amount_input;
        $reason = trim($_POST['reason'] ?? '');
        $recorded_by = (int)($_SESSION['user_id'] ?? 0);

        if ($loan_id <= 0 || !valid_loan_amount($amount_input) || $reason === '' || $recorded_by <= 0) {
            $_SESSION['error'] = "Enter a write-off amount and reason.";
            loan_redirect();
        }

        mysqli_begin_transaction($conn);
        $loan_stmt = loan_prepare(
            $conn,
            "SELECT amount, clearance, legacy_closed
             FROM long_term_loans WHERE id = ? AND deleted_at IS NULL FOR UPDATE",
            "Unable to find loan"
        );
        mysqli_stmt_bind_param($loan_stmt, "i", $loan_id);
        loan_execute($loan_stmt, "Unable to find loan");
        $loan = mysqli_fetch_assoc(mysqli_stmt_get_result($loan_stmt));
        mysqli_stmt_close($loan_stmt);

        if (!$loan || $loan['clearance'] !== 'Active' || (int)$loan['legacy_closed'] === 1) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Only active, tracked loans can be written off.";
            loan_redirect();
        }
        $totals = get_loan_ledger_totals($conn, $loan_id);
        $balance = (float)$loan['amount'] - (float)$totals['paid'] - (float)$totals['written_off'];
        if ($amount - $balance > 0.00001) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "The write-off cannot exceed the remaining loan balance.";
            loan_redirect();
        }

        $writeoff_stmt = loan_prepare(
            $conn,
            "INSERT INTO loan_writeoffs (loan_id, amount, reason, recorded_by_user_id)
             VALUES (?, ?, ?, ?)",
            "Unable to save write-off"
        );
        mysqli_stmt_bind_param($writeoff_stmt, "idsi", $loan_id, $amount, $reason, $recorded_by);
        loan_execute($writeoff_stmt, "Unable to save write-off");
        mysqli_stmt_close($writeoff_stmt);

        if ($balance - $amount <= 0.00001) {
            $date = date('Y-m-d');
            $close_stmt = loan_prepare(
                $conn,
                "UPDATE long_term_loans SET clearance = 'Cleared', clearance_date = ? WHERE id = ?",
                "Unable to close written-off loan"
            );
            mysqli_stmt_bind_param($close_stmt, "si", $date, $loan_id);
            loan_execute($close_stmt, "Unable to close written-off loan");
            mysqli_stmt_close($close_stmt);
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Write-off recorded with its reason.";
        loan_redirect();
    }

    if (isset($_POST['delete_loan'])) {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        $recorded_by = (int)($_SESSION['user_id'] ?? 0);
        $fallback_username = (string)($_SESSION['username'] ?? $_SESSION['admin'] ?? 'Unknown staff');
        if ($loan_id <= 0 || $recorded_by <= 0) {
            $_SESSION['error'] = "Unable to log this loan deletion. Please sign in again and retry.";
            loan_redirect();
        }

        mysqli_begin_transaction($conn);
        $log_stmt = loan_prepare(
            $conn,
            "INSERT INTO loan_deletion_logs
                (loan_id, borrower_name, borrower_phone, loan_amount,
                 deleted_by_user_id, deleted_by_username, deleted_at)
             SELECT l.id, COALESCE(c.name, l.customer_name), c.phone, l.amount,
                    ?, COALESCE(u.username, ?), NOW()
             FROM long_term_loans l
             LEFT JOIN loan_customers c ON c.id = l.customer_id
             LEFT JOIN users u ON u.id = ?
             WHERE l.id = ? AND l.deleted_at IS NULL",
            "Unable to record loan deletion"
        );
        mysqli_stmt_bind_param($log_stmt, "isii", $recorded_by, $fallback_username, $recorded_by, $loan_id);
        loan_execute($log_stmt, "Unable to record loan deletion");
        $log_created = mysqli_stmt_affected_rows($log_stmt) === 1;
        mysqli_stmt_close($log_stmt);

        if (!$log_created) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Loan not found or already deleted.";
            loan_redirect();
        }

        $delete_stmt = loan_prepare(
            $conn,
            "UPDATE long_term_loans
             SET deleted_at = NOW(), deleted_by_user_id = ?
             WHERE id = ? AND deleted_at IS NULL",
            "Unable to archive loan"
        );
        mysqli_stmt_bind_param($delete_stmt, "ii", $recorded_by, $loan_id);
        loan_execute($delete_stmt, "Unable to archive loan");
        $loan_archived = mysqli_stmt_affected_rows($delete_stmt) === 1;
        mysqli_stmt_close($delete_stmt);
        if (!$loan_archived) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Loan was not deleted; its deletion could not be recorded.";
            loan_redirect();
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Loan deleted from active records. Its repayment history and deletion log were preserved.";
        loan_redirect();
    }
}

$edit_record = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = loan_prepare(
        $conn,
        "SELECT l.*, c.phone, c.name AS borrower_name,
                COALESCE(p.paid_amount, 0) AS paid_amount,
                COALESCE(w.writeoff_amount, 0) AS writeoff_amount
         FROM long_term_loans l
         LEFT JOIN loan_customers c ON c.id = l.customer_id
         LEFT JOIN (SELECT loan_id, SUM(amount) AS paid_amount FROM loan_repayment_allocations GROUP BY loan_id) p ON p.loan_id = l.id
         LEFT JOIN (SELECT loan_id, SUM(amount) AS writeoff_amount FROM loan_writeoffs GROUP BY loan_id) w ON w.loan_id = l.id
         WHERE l.id = ? AND l.deleted_at IS NULL",
        "Unable to load loan for editing"
    );
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    loan_execute($stmt, "Unable to load loan for editing");
    $edit_record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$search = trim($_GET['search'] ?? '');
$page_size = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$where_sql = " WHERE l.deleted_at IS NULL";
$like = '%' . $search . '%';
if ($search !== '') {
    $where_sql .= " AND (l.customer_name LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR l.particular LIKE ? OR l.duration LIKE ? OR l.clearance LIKE ? OR (l.legacy_closed = 1 AND ? LIKE '%legacy%'))";
}

$count_sql = "SELECT COUNT(*) FROM long_term_loans l LEFT JOIN loan_customers c ON c.id = l.customer_id" . $where_sql;
$count_stmt = loan_prepare($conn, $count_sql, "Unable to count loans");
if ($search !== '') {
    mysqli_stmt_bind_param($count_stmt, "sssssss", $like, $like, $like, $like, $like, $like, $search);
}
loan_execute($count_stmt, "Unable to count loans");
mysqli_stmt_bind_result($count_stmt, $total_records);
mysqli_stmt_fetch($count_stmt);
mysqli_stmt_close($count_stmt);
$total_records = (int)$total_records;
$total_pages = max(1, (int)ceil($total_records / $page_size));
$page = min($page, $total_pages);
$offset = ($page - 1) * $page_size;

$records_sql = "SELECT l.*, c.phone, c.name AS borrower_name,
                       COALESCE(p.paid_amount, 0) AS paid_amount,
                       COALESCE(w.writeoff_amount, 0) AS writeoff_amount
                FROM long_term_loans l
                LEFT JOIN loan_customers c ON c.id = l.customer_id
                LEFT JOIN (SELECT loan_id, SUM(amount) AS paid_amount FROM loan_repayment_allocations GROUP BY loan_id) p ON p.loan_id = l.id
                LEFT JOIN (SELECT loan_id, SUM(amount) AS writeoff_amount FROM loan_writeoffs GROUP BY loan_id) w ON w.loan_id = l.id" . $where_sql . "
                ORDER BY l.loan_date DESC, l.id DESC LIMIT ? OFFSET ?";
$records_stmt = loan_prepare($conn, $records_sql, "Unable to load loans");
if ($search !== '') {
    mysqli_stmt_bind_param($records_stmt, "sssssssii", $like, $like, $like, $like, $like, $like, $search, $page_size, $offset);
} else {
    mysqli_stmt_bind_param($records_stmt, "ii", $page_size, $offset);
}
loan_execute($records_stmt, "Unable to load loans");
$records = mysqli_stmt_get_result($records_stmt);

$summary_result = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_loans,
        COALESCE(SUM(amount), 0) AS total_amount,
        SUM(CASE WHEN clearance = 'Active' AND legacy_closed = 0 THEN 1 ELSE 0 END) AS active_loans,
        SUM(CASE WHEN clearance = 'Cleared' AND legacy_closed = 0 THEN 1 ELSE 0 END) AS cleared_loans,
        SUM(CASE WHEN legacy_closed = 1 THEN 1 ELSE 0 END) AS legacy_loans,
        COALESCE(SUM(CASE WHEN clearance = 'Cleared' AND legacy_closed = 0 THEN amount ELSE 0 END), 0) AS cleared_amount,
        COALESCE(SUM(CASE WHEN legacy_closed = 1 THEN amount ELSE 0 END), 0) AS legacy_amount,
        COALESCE((
            SELECT SUM(l.amount - COALESCE(p.paid, 0) - COALESCE(w.written_off, 0))
            FROM long_term_loans l
            LEFT JOIN (SELECT loan_id, SUM(amount) AS paid FROM loan_repayment_allocations GROUP BY loan_id) p ON p.loan_id = l.id
            LEFT JOIN (SELECT loan_id, SUM(amount) AS written_off FROM loan_writeoffs GROUP BY loan_id) w ON w.loan_id = l.id
            WHERE l.clearance = 'Active' AND l.legacy_closed = 0 AND l.deleted_at IS NULL
        ), 0) AS active_balance
     FROM long_term_loans
     WHERE deleted_at IS NULL"
);
if (!$summary_result) {
    die("Unable to load loan summary: " . mysqli_error($conn));
}
$summary = mysqli_fetch_assoc($summary_result);
$ledger_result = mysqli_query(
    $conn,
    "SELECT
        COALESCE((SELECT SUM(amount) FROM loan_repayment_allocations), 0) AS paid_total,
        COALESCE((SELECT COUNT(*) FROM loan_repayments), 0) AS repayment_count,
        COALESCE((SELECT SUM(amount) FROM loan_repayments), 0) AS received_total,
        COALESCE((
            SELECT COUNT(DISTINCT r.customer_id)
            FROM loan_repayments r
            LEFT JOIN (
                SELECT repayment_id, SUM(amount) AS allocated
                FROM loan_repayment_allocations GROUP BY repayment_id
            ) a ON a.repayment_id = r.id
            WHERE r.amount > COALESCE(a.allocated, 0)
        ), 0) AS credit_customers,
        COALESCE((
            SELECT SUM(r.amount - COALESCE(a.allocated, 0))
            FROM loan_repayments r
            LEFT JOIN (
                SELECT repayment_id, SUM(amount) AS allocated
                FROM loan_repayment_allocations GROUP BY repayment_id
            ) a ON a.repayment_id = r.id
        ), 0) AS credit_total"
);
if (!$ledger_result) {
    die("Unable to load repayment summary: " . mysqli_error($conn));
}
$ledger_summary = mysqli_fetch_assoc($ledger_result);
$borrowers_stmt = loan_prepare(
    $conn,
    "SELECT id, name, phone
     FROM loan_customers
     WHERE phone IS NOT NULL AND phone <> ''
     ORDER BY name, phone",
    "Unable to load existing customers"
);
loan_execute($borrowers_stmt, "Unable to load existing customers");
$borrowers_result = mysqli_stmt_get_result($borrowers_stmt);
$existing_borrowers = [];
while ($borrower = mysqli_fetch_assoc($borrowers_result)) {
    $existing_borrowers[] = $borrower;
}
mysqli_stmt_close($borrowers_stmt);
$pagination_params = ['search' => $search];
$previous_page_url = 'long_term_loans.php?' . http_build_query(array_merge($pagination_params, ['page' => max(1, $page - 1)]));
$next_page_url = 'long_term_loans.php?' . http_build_query(array_merge($pagination_params, ['page' => min($total_pages, $page + 1)]));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>T-Shirt Plug - Long Term Loans</title>
<link rel="stylesheet" href="sidebar.css">
<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #f4f7fb; margin: 0; color: #222; }
.main { margin-left: 250px; padding: 30px; }
.page-header { margin-bottom: 25px; }
.page-header h1 { margin: 0 0 5px; }
.page-header p { color: #666; }
.box, .summary-card { background: #fff; padding: 22px; margin-bottom: 22px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,.07); }
.success, .error { padding: 13px; margin-bottom: 18px; border-radius: 7px; }
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }
.summary-grid { display: flex; flex-wrap: wrap; gap: 14px; }
.summary-card { flex: 1 1 175px; border-left: 5px solid #0d47a1; }
.summary-card.active { border-left-color: #f59e0b; }
.summary-card.cleared { border-left-color: #198754; }
.summary-card.legacy { border-left-color: #6c757d; }
.summary-title { color: #666; font-size: 14px; margin-bottom: 8px; }
.summary-number { font-size: 22px; font-weight: bold; }
.summary-amount { margin-top: 6px; color: #35506e; font-size: 14px; font-weight: 600; }
.loan-add-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.loan-add-heading h2 { margin: 0; }
.loan-add-toggle, button { padding: 11px 16px; background: #0d47a1; color: #fff; border: 0; border-radius: 7px; cursor: pointer; font-size: 15px; }
.loan-add-toggle { width: 42px; height: 42px; padding: 0; border-radius: 50%; font-size: 26px; }
.loan-add-panel { margin-top: 20px; }
.loan-add-panel[hidden] { display: none; }
.loan-form { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
input, textarea, select { width: 100%; padding: 11px; border: 1px solid #ccc; border-radius: 7px; font: inherit; }
textarea { min-height: 85px; resize: vertical; }
.cancel-button { display: inline-block; padding: 11px 16px; margin-left: 8px; background: #777; color: #fff; text-decoration: none; border-radius: 7px; }
.table-container { width: 100%; overflow-x: auto; }
table { width: 100%; min-width: 850px; border-collapse: collapse; }
th { background: #0d47a1; color: #fff; }
td, th { padding: 10px; border: 1px solid #ddd; text-align: center; }
td.particular { text-align: left; min-width: 220px; white-space: pre-wrap; }
.status { display: inline-block; padding: 6px 10px; border-radius: 20px; font-size: 13px; font-weight: bold; }
.status.active { background: #fff3cd; color: #856404; }
.status.cleared { background: #d4edda; color: #155724; }
.status.legacy { background: #e2e3e5; color: #383d41; }
.status.writeoff { background: #f8d7da; color: #721c24; }
.status.deleted { background: #343a40; color: #fff; }
.search-form { display: flex; gap: 8px; margin-bottom: 18px; }
.search-form input { flex: 1; }
.action-button { display: inline-block; border: 0; padding: 7px 9px; margin: 2px; color: #fff; text-decoration: none; border-radius: 5px; font-size: 13px; cursor: pointer; }
.action-view { background: #0d47a1; }
.action-edit { background: #1565c0; }
.action-pay { background: #198754; }
.action-writeoff { background: #b02a37; }
.action-delete { background: #777; }
.pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 16px; }
.pagination a, .pagination span { display: inline-block; padding: 8px 12px; border-radius: 5px; }
.pagination a { background: #0d47a1; color: #fff; text-decoration: none; }
.pagination .disabled { background: #e9ecef; color: #777; }
.note { color: #666; font-size: 13px; }
.detail-profile { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 18px 0; }
.detail-profile > div, .loan-info-grid > div { padding: 14px; background: #fff; border: 1px solid #e1e7ef; border-radius: 8px; }
.detail-label { display: block; margin-bottom: 5px; color: #666; font-size: 13px; }
.detail-summary { display: grid; grid-template-columns: repeat(5, minmax(120px, 1fr)); gap: 10px; margin: 18px 0; }
.detail-summary > div { padding: 14px; background: #eef4fb; border-radius: 8px; }
.detail-summary span, .detail-summary strong { display: block; }
.detail-summary span { margin-bottom: 6px; color: #666; font-size: 12px; }
.detail-summary strong { color: #173a64; }
.loan-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin: 16px 0; }
.loan-info-grid span, .loan-info-grid strong { display: block; }
.loan-info-grid span { margin-bottom: 6px; color: #666; font-size: 12px; }
.loan-info-grid strong { color: #263d57; }
.loan-info-wide { grid-column: 1 / -1; }
.drawer-actions { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0; }
.drawer-actions .action-button { padding: 10px 13px; }
.drawer-overlay { position: fixed; inset: 0; z-index: 40; background: rgba(15, 23, 42, .45); }
.borrower-drawer { position: fixed; z-index: 41; inset: 0 0 0 auto; display: flex; flex-direction: column; width: min(980px, 96vw); background: #f8fafc; box-shadow: -12px 0 36px rgba(0, 0, 0, .2); transform: translateX(100%); transition: transform .24s ease; }
.borrower-drawer.is-open { transform: translateX(0); }
.drawer-overlay[hidden], .borrower-drawer[hidden] { display: none; }
.drawer-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 24px; color: #fff; background: #0d47a1; }
.drawer-header h2 { margin: 0; font-size: 20px; }
.drawer-header button { background: rgba(255,255,255,.16); }
.drawer-body { overflow-y: auto; padding: 22px; }
.loan-detail > h3 { margin: 24px 0 10px; color: #173a64; }
.drawer-table-container { margin-bottom: 14px; border: 1px solid #e1e7ef; border-radius: 8px; background: #fff; }
.drawer-table-container table { min-width: 900px; }
.drawer-action-form { margin: 18px 0; padding: 18px; border: 1px solid #cbd9ea; border-radius: 10px; background: #fff; }
.drawer-action-form[hidden] { display: none; }
.drawer-action-form h3 { margin: 0 0 8px; }
.drawer-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 12px 0; }
.drawer-form-grid label { display: flex; flex-direction: column; gap: 6px; font-weight: 600; }
.drawer-form-wide { grid-column: 1 / -1; }
.inline-action { display: inline; }
.drawer-loading, .drawer-error { padding: 24px; color: #555; }
.drawer-error { color: #9c2525; }
body.drawer-open { overflow: hidden; }
@media (max-width: 1200px) { .summary-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 800px) { .main { margin-left: 0; padding: 15px; } .summary-grid { grid-template-columns: repeat(2, 1fr); } .detail-profile { grid-template-columns: repeat(2, minmax(0, 1fr)); } .detail-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .loan-form { grid-template-columns: 1fr; } .form-group.full, .drawer-form-wide { grid-column: auto; } .summary-grid, .detail-profile, .detail-summary, .loan-info-grid, .drawer-form-grid { grid-template-columns: 1fr; } .search-form, .pagination { flex-direction: column; align-items: stretch; } .cancel-button { margin: 8px 0 0; } .drawer-header { padding: 14px; } .drawer-body { padding: 14px; } }
</style>
</head>
<body>
<?php include "sidebar.php"; ?>
<main class="main">
    <header class="page-header">
        <h1>Long Term Loans</h1>
        <p>Manage borrowers, loan balances, repayments and write-offs.</p>
    </header>

    <?php if (isset($_SESSION['success']) && !isset($_GET['view_loan_id'])): ?>
        <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error']) && !isset($_GET['view_loan_id'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <section class="summary-grid">
        <div class="summary-card"><div class="summary-title">Total Loans</div><div class="summary-number"><?= (int)$summary['total_loans'] ?></div><div class="summary-amount">TSh <?= number_format((float)$summary['total_amount'], 2) ?></div></div>
        <div class="summary-card active"><div class="summary-title">Active Loans</div><div class="summary-number"><?= (int)$summary['active_loans'] ?></div><div class="summary-amount">Balance TSh <?= number_format((float)$summary['active_balance'], 2) ?></div></div>
        <div class="summary-card cleared"><div class="summary-title">Closed Loans</div><div class="summary-number"><?= (int)$summary['cleared_loans'] ?></div><div class="summary-amount">Principal TSh <?= number_format((float)$summary['cleared_amount'], 2) ?></div></div>
        <div class="summary-card"><div class="summary-title">Repayments</div><div class="summary-number"><?= (int)$ledger_summary['repayment_count'] ?></div><div class="summary-amount">TSh <?= number_format((float)$ledger_summary['received_total'], 2) ?> received</div></div>
    </section>

    <section class="box">
        <?php if ($edit_record): ?>
            <h2>Edit Long Term Loan</h2>
        <?php else: ?>
            <div class="loan-add-heading">
                <h2>Add Long Term Loan</h2>
                <button type="button" class="loan-add-toggle" id="loan-add-toggle" aria-label="Add long term loan" aria-expanded="false" aria-controls="loan-add-panel"><span aria-hidden="true">+</span></button>
            </div>
        <?php endif; ?>
        <div class="loan-add-panel" id="loan-add-panel" <?= $edit_record ? '' : 'hidden' ?>>
            <form method="POST" class="loan-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php if ($edit_record): ?><input type="hidden" name="id" value="<?= (int)$edit_record['id'] ?>"><?php endif; ?>
                <div class="form-group"><label>Loan Date</label><input type="date" name="loan_date" value="<?= htmlspecialchars($edit_record['loan_date'] ?? date('Y-m-d')) ?>" required></div>
                <?php if (!$edit_record): ?>
                    <div class="form-group full">
                        <label for="existing-customer-select">Existing Customer (optional)</label>
                        <select name="existing_customer_id" id="existing-customer-select">
                            <option value="0">New customer — enter details below</option>
                            <?php foreach ($existing_borrowers as $borrower): ?>
                                <option value="<?= (int)$borrower['id'] ?>" data-name="<?= htmlspecialchars($borrower['name'], ENT_QUOTES) ?>" data-phone="<?= htmlspecialchars($borrower['phone'], ENT_QUOTES) ?>"><?= htmlspecialchars($borrower['name'] . ' — ' . $borrower['phone']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="note">Select a customer to reuse their borrower profile and credit.</span>
                    </div>
                <?php endif; ?>
                <div class="form-group"><label>Customer / Borrower</label><input type="text" name="customer_name" value="<?= htmlspecialchars($edit_record['borrower_name'] ?? '') ?>" placeholder="Enter borrower name" required></div>
                <div class="form-group"><label>Borrower Phone (unique)</label><input type="tel" name="phone" value="<?= htmlspecialchars($edit_record['phone'] ?? '') ?>" maxlength="32" placeholder="Use the same phone to link this borrower's loans" required></div>
                <?php $principal_locked = $edit_record && ((float)$edit_record['paid_amount'] > 0 || (float)$edit_record['writeoff_amount'] > 0 || (int)$edit_record['legacy_closed'] === 1); ?>
                <div class="form-group"><label>Loan Amount</label><input type="number" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($edit_record['amount'] ?? '') ?>" <?= $principal_locked ? 'readonly' : '' ?> required></div>
                <div class="form-group"><label>Disbursement Method</label><select name="payment_method" required><option value="">Select method</option><?php foreach ($allowed_payment_methods as $method): ?><option value="<?= htmlspecialchars($method) ?>" <?= ($edit_record['payment_method'] ?? '') === $method ? 'selected' : '' ?>><?= htmlspecialchars($method) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>From Date</label><input type="date" name="from_date" value="<?= htmlspecialchars($edit_record['from_date'] ?? date('Y-m-d')) ?>" required></div>
                <div class="form-group"><label>Duration</label><input type="text" name="duration" value="<?= htmlspecialchars($edit_record['duration'] ?? '') ?>" placeholder="Example: 12 months" required></div>
                <div class="form-group full"><label>Particular / Notes</label><textarea name="particular" required><?= htmlspecialchars($edit_record['particular'] ?? '') ?></textarea></div>
                <?php if ($principal_locked): ?><p class="note form-group full">Principal cannot be changed after repayments, write-offs, or legacy closure.</p><?php endif; ?>
                <div class="form-group full"><div><button type="submit" name="<?= $edit_record ? 'update_loan' : 'add_loan' ?>"><?= $edit_record ? 'Update Loan' : 'Save Long Term Loan' ?></button><?php if ($edit_record): ?> <a class="cancel-button" href="long_term_loans.php">Cancel</a><?php endif; ?></div></div>
            </form>
        </div>
    </section>

    <section class="box">
        <h2>Loan Records</h2>
        <p class="note">Existing loans were initially linked to separate borrower profiles because phone numbers were not recorded. Edit each related loan and enter the same phone number to link it to one borrower; new loans with that phone number share the profile automatically.</p>
        <form method="GET" class="search-form">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search borrower, phone, notes or status...">
            <button type="submit">Search</button>
        </form>
        <div class="table-container">
            <table>
                <thead><tr>
                    <th>#</th><th>Loan Date</th><th>Borrower</th><th>Phone</th><th>Loan Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($records) > 0): ?>
                    <?php $count = $offset + 1; while ($row = mysqli_fetch_assoc($records)): ?>
                        <?php
                        $paid = (float)$row['paid_amount'];
                        $written_off = (float)$row['writeoff_amount'];
                        $balance = max(0, (float)$row['amount'] - $paid - $written_off);
                        $legacy = (int)$row['legacy_closed'] === 1;
                        $status_label = $legacy ? 'Legacy closed' : ($balance <= 0.00001 ? ($written_off > 0 ? 'Written off' : 'Paid') : 'Active');
                        $status_class = $legacy ? 'legacy' : ($balance <= 0.00001 ? ($written_off > 0 ? 'writeoff' : 'cleared') : 'active');
                        ?>
                        <tr>
                            <td><?= $count++ ?></td>
                            <td><?= htmlspecialchars($row['loan_date']) ?></td>
                            <td><strong><?= htmlspecialchars($row['borrower_name'] ?? $row['customer_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['phone'] ?? 'Not recorded') ?></td>
                            <td>TSh <?= number_format((float)$row['amount'], 2) ?></td>
                            <td><?= $legacy ? 'Not tracked' : 'TSh ' . number_format($paid, 2) ?></td>
                            <td><?= $legacy ? 'Not tracked' : 'TSh ' . number_format($balance, 2) ?></td>
                            <td><span class="status <?= $status_class ?>"><?= htmlspecialchars($status_label) ?></span></td>
                            <td>
                                <button type="button" class="action-button action-view" data-open-loan="<?= (int)$row['id'] ?>" data-loan-title="Loan #<?= (int)$row['id'] ?> — <?= htmlspecialchars($row['borrower_name'] ?? $row['customer_name'], ENT_QUOTES) ?>">View More</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9">No long term loan records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_records > 0): ?>
            <nav class="pagination" aria-label="Long term loan pages">
                <span>Showing <?= $offset + 1 ?>–<?= min($offset + $page_size, $total_records) ?> of <?= $total_records ?> loans</span>
                <div><?php if ($page > 1): ?><a href="<?= htmlspecialchars($previous_page_url) ?>">Previous</a><?php else: ?><span class="disabled">Previous</span><?php endif; ?> <?php if ($page < $total_pages): ?><a href="<?= htmlspecialchars($next_page_url) ?>">Next</a><?php else: ?><span class="disabled">Next</span><?php endif; ?></div>
            </nav>
        <?php endif; ?>
    </section>

    <section class="box">
        <h2>Repayment Summary</h2>
        <p><strong>Total principal issued:</strong> TSh <?= number_format((float)$summary['total_amount'], 2) ?></p>
        <p><strong>Outstanding active balance:</strong> TSh <?= number_format((float)$summary['active_balance'], 2) ?></p>
        <p class="note">Legacy-closed loans were closed before repayment tracking began. Their payment history and remaining balance are not available, so no repayment entries have been created for them.</p>
    </section>
</main>

<div class="drawer-overlay" id="borrower-drawer-overlay" hidden></div>
<aside class="borrower-drawer" id="borrower-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="borrower-drawer-title" hidden>
    <header class="drawer-header">
        <h2 id="borrower-drawer-title">Loan Details</h2>
        <button type="button" id="close-borrower-drawer" aria-label="Close loan details">Close</button>
    </header>
    <div class="drawer-body" id="borrower-drawer-content"></div>
</aside>

<script>
const addToggle = document.getElementById('loan-add-toggle');
if (addToggle) {
    const panel = document.getElementById('loan-add-panel');
    const icon = addToggle.querySelector('span');
    addToggle.addEventListener('click', function () {
        const expanded = addToggle.getAttribute('aria-expanded') === 'true';
        addToggle.setAttribute('aria-expanded', String(!expanded));
        addToggle.setAttribute('aria-label', expanded ? 'Add long term loan' : 'Close add long term loan form');
        icon.textContent = expanded ? '+' : '−';
        panel.hidden = expanded;
    });
}
const existingCustomerSelect = document.getElementById('existing-customer-select');
if (existingCustomerSelect) {
    const customerNameInput = document.querySelector('input[name="customer_name"]');
    const customerPhoneInput = document.querySelector('input[name="phone"]');
    let selectedExistingCustomer = '';

    existingCustomerSelect.addEventListener('change', function () {
        const selectedOption = existingCustomerSelect.options[existingCustomerSelect.selectedIndex];
        if (existingCustomerSelect.value !== '0') {
            customerNameInput.value = selectedOption.dataset.name;
            customerPhoneInput.value = selectedOption.dataset.phone;
            customerNameInput.readOnly = true;
            customerPhoneInput.readOnly = true;
            selectedExistingCustomer = existingCustomerSelect.value;
            return;
        }

        if (selectedExistingCustomer !== '') {
            customerNameInput.value = '';
            customerPhoneInput.value = '';
        }
        customerNameInput.readOnly = false;
        customerPhoneInput.readOnly = false;
        selectedExistingCustomer = '';
    });
}
const borrowerDrawer = document.getElementById('borrower-drawer');
const borrowerDrawerOverlay = document.getElementById('borrower-drawer-overlay');
const borrowerDrawerContent = document.getElementById('borrower-drawer-content');
const borrowerDrawerTitle = document.getElementById('borrower-drawer-title');
const closeBorrowerDrawer = document.getElementById('close-borrower-drawer');

function closeDrawer() {
    borrowerDrawer.classList.remove('is-open');
    borrowerDrawer.setAttribute('aria-hidden', 'true');
    borrowerDrawerOverlay.hidden = true;
    document.body.classList.remove('drawer-open');
    window.setTimeout(function () {
        if (!borrowerDrawer.classList.contains('is-open')) {
            borrowerDrawer.hidden = true;
        }
    }, 250);
    const url = new URL(window.location.href);
    url.searchParams.delete('view_loan_id');
    window.history.replaceState({}, '', url.toString());
}

async function openLoanDrawer(loanId, loanTitle) {
    borrowerDrawer.hidden = false;
    borrowerDrawerOverlay.hidden = false;
    borrowerDrawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('drawer-open');
    borrowerDrawerTitle.textContent = loanTitle || 'Loan Details';
    borrowerDrawerContent.innerHTML = '<div class="drawer-loading">Loading loan details...</div>';
    const url = new URL(window.location.href);
    url.searchParams.set('view_loan_id', loanId);
    window.history.replaceState({}, '', url.toString());
    window.requestAnimationFrame(function () {
        borrowerDrawer.classList.add('is-open');
    });

    try {
        const response = await fetch(
            'long_term_loans.php?loan_details=' + encodeURIComponent(loanId),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        if (!response.ok) {
            throw new Error('Unable to load loan details.');
        }
        borrowerDrawerContent.innerHTML = await response.text();
        const detail = borrowerDrawerContent.querySelector('[data-loan-title]');
        if (detail) {
            borrowerDrawerTitle.textContent = detail.dataset.loanTitle;
        }
        closeBorrowerDrawer.focus();
    } catch (error) {
        borrowerDrawerContent.innerHTML = '<p class="drawer-error">Loan details could not be loaded. Please try again.</p>';
    }
}

document.querySelectorAll('[data-open-loan]').forEach(function (button) {
    button.addEventListener('click', function () {
        openLoanDrawer(button.dataset.openLoan, button.dataset.loanTitle);
    });
});

borrowerDrawerContent.addEventListener('click', function (event) {
    const action = event.target.closest('[data-drawer-action]');
    if (!action) {
        return;
    }

    const forms = [
        document.getElementById('drawer-edit-form'),
        document.getElementById('drawer-repayment-form'),
        document.getElementById('drawer-writeoff-form')
    ];
    forms.forEach(function (form) {
        if (form) form.hidden = true;
    });

    const formId = action.dataset.drawerAction === 'edit'
        ? 'drawer-edit-form'
        : (action.dataset.drawerAction === 'repay'
            ? 'drawer-repayment-form'
            : 'drawer-writeoff-form');
    const form = document.getElementById(formId);
    if (!form) return;
    if (action.dataset.drawerAction === 'writeoff') {
        const amount = document.getElementById('drawer-writeoff-amount');
        amount.max = action.dataset.balance;
        amount.value = '';
    }
    form.hidden = false;
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

closeBorrowerDrawer.addEventListener('click', closeDrawer);
borrowerDrawerOverlay.addEventListener('click', closeDrawer);
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !borrowerDrawer.hidden) {
        closeDrawer();
    }
});

const initialLoanId = new URLSearchParams(window.location.search).get('view_loan_id');
if (initialLoanId) {
    openLoanDrawer(initialLoanId, '');
}
</script>
</body>
</html>
