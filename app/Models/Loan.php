<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $guarded = ['id'];

    public function loanRequest()
    {
        return $this->belongsTo(LoanRequest::class);
    }


    public function payments()
    {
        return $this->hasMany(Payment::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
