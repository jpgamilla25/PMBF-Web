<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DependentAttachment extends Model
{
    protected $fillable = ['dependent_id', 'file_name', 'file_path', 'file_type'];

    public function dependent()
    {
        return $this->belongsTo(Dependent::class);
    }
}
