<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'loan_id',
        'recorded_by',
        'import_batch_id',
        'loan_import_batch_id',
        'amount',
        'or_number',
        'payment_method',
        'payment_date',
        'remarks',
        'receipt_path',
        'dedupe_hash',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function importBatch()
    {
        return $this->belongsTo(PaymentImportBatch::class, 'import_batch_id');
    }

    /**
     * Fingerprint identifying "the same payment" on a loan: same loan, same
     * day, same amount, same OR number. The bulk importer writes this to a
     * UNIQUE column so a re-uploaded payroll file cannot post twice.
     *
     * Mirrors the SQL backfill in the payment_import_batches migration —
     * change one and you must change the other.
     */
    public static function dedupeHash(int $loanId, string $paymentDate, float $amount, ?string $orNumber): string
    {
        return hash('sha256', implode('|', [
            $loanId,
            $paymentDate,
            number_format($amount, 2, '.', ''),
            strtoupper(trim((string) $orNumber)),
        ]));
    }
}
