<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentImportBatch extends Model
{
    protected $fillable = [
        'file_name',
        'file_hash',
        'file_path',
        'uploaded_by',
        'rows_total',
        'rows_imported',
        'rows_skipped',
        'rows_failed',
        'amount_total',
        'status',
        'rolled_back_by',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_total' => 'decimal:2',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'import_batch_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rollbackUser()
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }
}
