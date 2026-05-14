<?php

namespace App\Http\Services;

use App\Enums\PaymentStatus;
use App\Http\Services\SubscriptionService;
use App\Models\MemberDetail;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PaymentActions
{
    
    public $environment;
    public $MPESA_CALLBACK_URL;
    public $MPESA_CONFIRMATION_URL;
    public $MPESA_VALIDATION_URL;
    public $MPESA_STK_PUSH_URL;
    public $MPESA_REGISTER_URL;
    public $MPESA_SIMULATE_URL;
    public $MPESA_GENERATE_ACCESS_TOKEN_URL;


    public function __construct()
    {
        $this->environment = config('services.mpesa.environment', 'sandbox');
        $MPESA_CALLBACKS_BASE_URL =  env('MPESA_CALLBACKS_BASE_URL','http://127.0.0.1:8000');
        $this->MPESA_CALLBACK_URL = $MPESA_CALLBACKS_BASE_URL.'/api/v1/sacco/stk/confirmation';
        $this->MPESA_CONFIRMATION_URL = $MPESA_CALLBACKS_BASE_URL.'/api/v1/sacco/transaction/confirmation?token=123456789';
        $this->MPESA_VALIDATION_URL = $MPESA_CALLBACKS_BASE_URL.'/api/v1/sacco/validation';

        if ($this->environment === 'sandbox') {
            $this->MPESA_STK_PUSH_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
            $this->MPESA_REGISTER_URL = 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';
            $this->MPESA_SIMULATE_URL = 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate';
            $this->MPESA_GENERATE_ACCESS_TOKEN_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        } else {
            $this->MPESA_STK_PUSH_URL = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
            $this->MPESA_REGISTER_URL = 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl';
            $this->MPESA_SIMULATE_URL = 'https://api.safaricom.co.ke/mpesa/c2b/v1/simulate';
            $this->MPESA_GENERATE_ACCESS_TOKEN_URL = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        }
    }


    public function generateAccessToken()
    {
        $consumer_key = config('services.mpesa.consumer_key');
        $consumer_secret = config('services.mpesa.consumer_secret');
        $credentials = base64_encode($consumer_key . ":" . $consumer_secret);
        $url = $this->MPESA_GENERATE_ACCESS_TOKEN_URL;
        $curl = curl_init($url);
        $headers = array(  
            'Content-Type: application/json; charset=utf-8'
        );
        // curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        // curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Basic " . $credentials));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_USERPWD, $consumer_key . ":" . $consumer_secret);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $curl_response = curl_exec($curl);
        $access_token = json_decode($curl_response);
        return $access_token->access_token;
    }

    public function lipaNaMpesaPassword()
    {
        $lipa_time = Carbon::rawParse('now')->format('YmdHms');
        $passkey = config('services.mpesa.passkey');
        logger($passkey);
        if ($this->environment === 'sandbox') {
            $shortCode = 174379;
        } else{
            $shortCode = config('services.mpesa.shortcode');
        }
        $BusinessShortCode = $shortCode;
        $timestamp = $lipa_time;
        $lipa_na_mpesa_password = base64_encode($BusinessShortCode . $passkey . $timestamp);
        return $lipa_na_mpesa_password;
    }

    public function initialize($phoneNumber, $amount)
    {
        $url = $this->MPESA_STK_PUSH_URL;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $this->generateAccessToken()));
        $phone_number = self::formatNumber($phoneNumber);

        if ($this->environment === 'sandbox') {
            $shortCode = 174379;
        } else{
            $shortCode = config('services.mpesa.shortcode');
        }
        $curl_post_data = [
            'BusinessShortCode' => $shortCode,
            'Password' => $this->lipaNaMpesaPassword(),
            'Timestamp' => Carbon::rawParse('now')->format('YmdHms'),
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone_number,
            'PartyB' => $shortCode,
            'PhoneNumber' => $phone_number,
            'CallBackURL' => $this->MPESA_CALLBACK_URL,
            'AccountReference' => 'STK_'.$phone_number,
            'TransactionDesc' => "SACCO Digital Wallet Payment"
        ];
        $data_string = json_encode($curl_post_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        //        $curl_response = curl_exec($curl);
        return json_decode(curl_exec($curl)); //Return response as PHP object
    }

    public function registerUrls(){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->MPESA_REGISTER_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $this->generateAccessToken()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
            'ShortCode' => config('services.mpesa.shortcode', '600995'),
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $this->MPESA_CONFIRMATION_URL,
            'ValidationURL' => $this->MPESA_VALIDATION_URL
        )));
        $curl_response = curl_exec($curl);
        echo $curl_response;
    }

    public function simulate(){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->MPESA_SIMULATE_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $this->generateAccessToken()));

        $curl_post_data = array(
            'ShortCode' => config('services.mpesa.shortcode', '600995'),
            'CommandID' => 'CustomerPayBillOnline',
            'Amount' => 200,
            'Msisdn' => 254707320000,
            'BillRefNumber' => 'EATSYBBHDJ'
        );

        $data_string = json_encode($curl_post_data);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        //        curl_setopt($curl, CURLOPT_HEADER, false);

        return curl_exec($curl);
    }

    public function createPush(Request $request)
    {

        $stkResponse = $this->initialize($request->phone_number, $request->amount);
        //Successful response example:
        // {
        //     "MerchantRequestID":"e02b-436e-9773-c97354f3811f41976",
        //     "CheckoutRequestID":"ws_CO_280720251501070707320000",
        //     "ResponseCode": "0",
        //     "ResponseDescription":"Success. Request accepted for processing",
        //     "CustomerMessage":"Success. Request accepted for processing"
        // }

        if ($stkResponse && property_exists($stkResponse, 'ResponseCode') && $stkResponse->ResponseCode == '0') {

            $this->createPayment($request, $stkResponse);
            return $stkResponse;
        } else {

            return $stkResponse;
        }
    }

    public function createPayment($userRequest, $stkResponse)
    {
        $user_id = $userRequest->user_id;
        $user = null;
        $formattedNumber = self::formatNumber($userRequest->phone_number);
        if (!$user_id){
            $user =  User::where('phone_number',$formattedNumber)->first();
        } else {
            $user = User::where('id', $user_id)->first();
        }
        if (!$user){
            Payment::create([
                'user_id' => $user ? $user->id : null,
                'amount' => $userRequest->amount,
                'type' => $userRequest->type,
                'transaction_ref' => $stkResponse->CheckoutRequestID,
                'user_payment_identifier' => $formattedNumber,
                'package_id' => $userRequest->plan_id,
                'loan_id' => $userRequest->loan_id ?? null,
            ]);
        }
    }

    public function updatePayment($data)
    {
        $payment_model = Payment::where('transaction_ref', $data['checkout_request_id'])->where('status', PaymentStatus::PENDING)->first();

        if (!$payment_model) {
            Log::error("Pending Payment not found for checkout request ID: " . $data['checkout_request_id']);
            return;
        }

        $transaction_status = $data['result_code'] === 0 ? PaymentStatus::COMPLETED : PaymentStatus::FAILED;

        $payment_model->amount = $data['amount'];
        $payment_model->transaction_date = $data['transaction_date'];
        $payment_model->transaction_ref = $data['mpesa_receipt_number'];
        $payment_model->status = $transaction_status;
        $payment_model->save();

        if ($transaction_status === PaymentStatus::COMPLETED){
            $this->doUserAccountReconciliation($payment_model);
        }
    }

    public static function formatNumber($data)
    {
        return '254' . substr($data, -9);
    }

    public function doUserAccountReconciliation(Payment $payment){
        $user = $payment->user;
        $memberDetails = MemberDetail::where('user_id', $user->id)->first();

        $message = "Payment successfull.";

        if ($payment->type === 'wallet_deposit') {
            $memberDetails->wallet_balance += $payment->amount;
            $memberDetails->save();

            $message .= " Your account has been credited with Ksh " . $payment->amount . "";    
        } elseif ($payment->type === 'loan_repayment') {
            $memberDetails->loan_balance -= $payment->amount;
            $memberDetails->save();

            $message .= " You now have a loan balance of Ksh " . $memberDetails->loan_balance;
        }

        $smsService = new SmsService();
        $smsService->send(
            $user->phone_number,
            $message
        );
    }
}