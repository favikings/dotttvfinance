<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Email notifications per Tech Spec §15: PHPMailer + Zoho Mail SMTP
 * (credentials from .env via config.php's SMTP_* constants — never
 * hardcoded), triggered on:
 *   - an expense entering a user's approval queue (pending_gm/pending_chairman)
 *   - an expense being rejected (to the Accountant who created it)
 *   - a fund top-up being approved/rejected (to the requesting Accountant)
 *
 * Callers invoke these AFTER the triggering DB transaction commits, so a
 * rolled-back change never fires a notification for something that didn't
 * actually happen. Every send is wrapped in try/catch: a failed email logs
 * to storage/logs/email.log and never throws — per CLAUDE.md, email is a
 * nice-to-have side effect, not a blocking dependency for the underlying
 * financial action.
 */
class EmailNotifier
{
    public static function expensePendingApproval(array $expense, string $roleName): void
    {
        $approvers = User::activeByRole($roleName);
        if ($approvers === []) {
            return;
        }

        $subject = "Expense {$expense['expense_no']} awaiting your approval";
        $body = self::body([
            'An expense is now awaiting your approval.',
            '',
            "Expense: {$expense['expense_no']}",
            "Payee: {$expense['payee']}",
            'Amount: ' . naira($expense['amount']),
            'Department: ' . ($expense['department_name'] ?? '—'),
            'Submitted by: ' . ($expense['created_by_name'] ?? '—'),
            '',
            'Review it here: ' . self::link('/approvals'),
        ]);

        foreach ($approvers as $approver) {
            self::dispatch($approver['email'], $approver['name'], $subject, $body);
        }
    }

    public static function expenseRejected(array $expense): void
    {
        if (empty($expense['created_by_email'])) {
            return;
        }

        $subject = "Expense {$expense['expense_no']} was rejected";
        $body = self::body([
            'Your expense was rejected.',
            '',
            "Expense: {$expense['expense_no']}",
            "Payee: {$expense['payee']}",
            'Amount: ' . naira($expense['amount']),
            'Reason: ' . ($expense['rejected_reason'] ?? '—'),
            '',
            'View it here: ' . self::link('/expenses'),
        ]);

        self::dispatch($expense['created_by_email'], (string) ($expense['created_by_name'] ?? ''), $subject, $body);
    }

    public static function topupDecided(array $topup, TopupStatus $status): void
    {
        if (empty($topup['requested_by_email'])) {
            return;
        }

        $verb = $status === TopupStatus::Approved ? 'approved' : 'rejected';
        $subject = "Fund top-up request {$verb}";

        $lines = [
            "Your fund top-up request was {$verb}.",
            '',
            'Amount: ' . naira($topup['amount']),
            'Date: ' . $topup['date'],
            'Reference: ' . ($topup['reference'] ?? '—'),
        ];
        if ($status === TopupStatus::Rejected && !empty($topup['rejected_reason'])) {
            $lines[] = 'Reason: ' . $topup['rejected_reason'];
        }
        $lines[] = '';
        $lines[] = 'View it here: ' . self::link('/fund-topups');

        self::dispatch($topup['requested_by_email'], (string) ($topup['requested_by_name'] ?? ''), $subject, self::body($lines));
    }

    private static function body(array $lines): string
    {
        return implode("\n", $lines);
    }

    private static function link(string $path): string
    {
        return APP_URL . '/' . ltrim($path, '/');
    }

    private static function dispatch(string $toEmail, string $toName, string $subject, string $body): void
    {
        if (SMTP_HOST === '' || SMTP_USER === '') {
            self::logFailure($toEmail, $subject, 'SMTP is not configured (.env SMTP_* values missing).');
            return;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);
            $mail->send();
        } catch (Throwable $e) {
            self::logFailure($toEmail, $subject, $e->getMessage());
        }
    }

    private static function logFailure(string $toEmail, string $subject, string $reason): void
    {
        $line = sprintf(
            "[%s] to=%s subject=\"%s\" error=%s\n",
            date('Y-m-d H:i:s'),
            $toEmail,
            $subject,
            $reason
        );
        @file_put_contents(LOGS_PATH . '/email.log', $line, FILE_APPEND | LOCK_EX);
    }
}
