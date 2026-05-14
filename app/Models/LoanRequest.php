<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanRequest extends Model
{
    protected $guarded = ['id'];

    public function loan()
    {
        return $this->hasOne(Loan::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
