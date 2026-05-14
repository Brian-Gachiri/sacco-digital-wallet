<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Http\Services\PaymentActions;
use App\Models\MpesaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMpesaTransactionConfirmationJob implements ShouldQueue
{
     use Queueable;

    protected $mpesaTransaction;
    protected string $contactNumber;

    /**
     * Create a new job instance.
     */
    public function __construct(MpesaTransaction $mpesaTransaction)
    {
        $this->mpesaTransaction = $mpesaTransaction;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentActions $paymentActions): void
    {

        $phone_number = null;
        if ($this->mpesaTransaction->TransactionType == 'Pay Bill') {
            // For Paybill transactions, the Bill ref number is the phone number
            // Till transactions don't have a BillRefNumber
            $phone_number = $this->standardizeMsisdn($this->mpesaTransaction->BillRefNumber);
        }

        // Tries to get user by hashed phone number or phone number
        // If user doesn't exist, it creates a new user with the hashed phone number
        $hashedPhone = $this->mpesaTransaction->MSISDN;
        $user = $this->getUser($hashedPhone, $phone_number);

        
        if (Payment::where('transaction_ref', $this->mpesaTransaction->TransID)->exists()) return;

        $payment = Payment::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'amount' => $this->mpesaTransaction->TransAmount,
            'user_payment_identifier' => $this->mpesaTransaction->BillRefNumber,
            'status' => PaymentStatus::COMPLETED,
            'transaction_ref' => $this->mpesaTransaction->TransID,
            'transaction_date' => $this->mpesaTransaction->TransTime,
            'loan_id' => null,
            'type' => 'wallet_deposit',
        ]);

        $paymentActions->doUserAccountReconciliation($payment);
    }

    function getUser(string $hashedPhone, $phone_number): ?User
    {
        $user = User::where(function ($query) use ($phone_number, $hashedPhone) {
            $query->where('hashed_phone', $hashedPhone);

            if ($phone_number) {
                $query->orWhere('phone_number', $phone_number);
            }
        })->first();

        return $user;
    }

    function standardizeMsisdn(?string $raw): ?string
    {
        if (!$raw || !is_numeric($raw)) return null;

        $digits = substr(preg_replace('/\D/', '', $raw), -9);
        $formatted = '254' . $digits;

        return (strlen($digits) === 9 && strlen($formatted) === 12) ? $formatted : null;
    }
}
