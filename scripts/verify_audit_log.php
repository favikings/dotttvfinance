<?php

declare(strict_types=1);

/**
 * Standalone CLI tamper check for the audit_log hash chain (Tech Spec §11 /
 * Build Prompt 2.5) — not a web route, run manually or via monthly cron
 * (schema.sql §9 / Tech Spec §18).
 *
 * Walks audit_log in id order and, for each row, recomputes what its
 * row_hash SHOULD be from that row's own stored fields plus the CHAIN'S
 * running expected-previous-hash (never the row's own stored prev_hash
 * column) — exactly the formula AuditLogger::record() used to produce it.
 * Deliberately never re-syncs the chain to a stored value: once one row's
 * data has been altered outside the application, its recomputed hash no
 * longer matches what's stored, and every row after it inherits that
 * divergence too, since the expected-previous-hash carried forward is
 * always the freshly recomputed one. That cascade is the point — a single
 * edited row is reported as itself AND everything downstream of it, which
 * is what makes the hash chain tamper-EVIDENT rather than just tamper-
 * logged (a bare per-row hash with no chaining wouldn't catch a row and
 * its hash being edited together, only a row edited alone).
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/config/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

$rows = AuditLog::allInOrder();

if ($rows === []) {
    echo "audit_log is empty — nothing to verify.\n";
    exit(0);
}

$expectedPrevHash = AuditLogger::GENESIS_HASH;
$mismatches = [];

foreach ($rows as $row) {
    $computed = hash('sha256', implode('|', [
        (string) ($row['user_id'] ?? ''),
        $row['action'],
        $row['entity_type'],
        (string) ($row['entity_id'] ?? ''),
        $row['before_json'] ?? '',
        $row['after_json'] ?? '',
        $row['created_at'],
        $expectedPrevHash,
    ]));

    if ($computed !== $row['row_hash']) {
        $mismatches[] = [
            'id'            => (int) $row['id'],
            'created_at'    => $row['created_at'],
            'entity'        => $row['entity_type'] . '#' . ($row['entity_id'] ?? '—'),
            'stored_hash'   => $row['row_hash'],
            'expected_hash' => $computed,
        ];
    }

    // Always advance with the recomputed value, never the stored one — this
    // is what makes a mismatch propagate to every subsequent row instead of
    // resetting the chain to "trust the database again" after one bad row.
    $expectedPrevHash = $computed;
}

$count = count($rows);

if ($mismatches === []) {
    echo "OK — verified {$count} audit_log row(s). Hash chain is intact.\n";
    exit(0);
}

$badCount = count($mismatches);
echo "TAMPER DETECTED — {$badCount} of {$count} audit_log row(s) failed hash verification:\n\n";

foreach ($mismatches as $m) {
    echo "  Row id {$m['id']} ({$m['created_at']}, {$m['entity']})\n";
    echo "    stored_hash:   {$m['stored_hash']}\n";
    echo "    expected_hash: {$m['expected_hash']}\n\n";
}

echo "The first flagged row is where the chain broke; rows after it are flagged\n";
echo "as a consequence of that break, not necessarily edited themselves.\n";

exit(1);
