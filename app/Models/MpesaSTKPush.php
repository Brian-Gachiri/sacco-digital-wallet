<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaSTKPush extends Model
{
    protected $fillable = [
        'result_desc',
        'result_code',
        'merchant_request_id',
        'checkout_request_id',
        'amount',
        'mpesa_receipt_number',
        'transaction_date',
        'phone_number',
    ];

    protected $table = 'mpesa_stk_push';
}
