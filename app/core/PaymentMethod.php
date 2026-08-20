<?php

declare(strict_types=1);

/**
 * Payment method, mirroring the `payment_vouchers.payment_method` DB ENUM in
 * schema.sql exactly (CLAUDE.md rule 7 — never bare string literals for
 * status comparisons).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Pos = 'pos';

    public static function tryFromString(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }
        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Cheque => 'Cheque',
            self::Pos => 'POS',
        };
    }
}
