<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dependent extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'relationship',
        'birth_date',
        'coverage_type',
        'is_beneficiary',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_beneficiary' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(DependentAttachment::class);
    }

    public function claims()
    {
        return $this->hasMany(Claim::class);
    }
}
