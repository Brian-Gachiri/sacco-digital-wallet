<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\PaymentActions;
use App\Jobs\ProcessMpesaTransactionConfirmationJob;
use App\Models\MpesaSTKPush;
use App\Models\MpesaTransaction;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
class MpesaController extends Controller
{
   
    protected $paymentActions;

    public function __construct(PaymentActions $paymentActions)
    {
        $this->paymentActions = $paymentActions;
    }

    public function pay(Request $request)
    {

        $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|in:loan_repayment,wallet_deposit',
        ]);

        $response = $this->paymentActions->createPush($request);
        return response()->json($response);
    }

    public function verifyPayment(Request $request)
    {

        // Expects at least one of these:
        //     "MerchantRequestID":"e02b-436e-9773-c97354f3811f41976",
        //     "CheckoutRequestID":"ws_CO_280720251501070707320000",
        if (!$request->filled('MerchantRequestID') && !$request->filled('CheckoutRequestID')) {
            throw ValidationException::withMessages([
                'id' => ['Either CheckoutRequestID or MerchantRequestID is required.'],
            ]);
        }
        
        $stkTransaction = MpesaSTKPush::where(
            'merchant_request_id', $request->MerchantRequestID)
            ->orWhere('checkout_request_id', $request->CheckoutRequestID)->first();

        if ($stkTransaction && $stkTransaction->result_code == "0"){
            return response()->json([
                'success'=> true,
                'message'=> 'Payment Successful. Log into your account to check the latest tips. If you did not have an account and have not received log in details, kindly contact support on: +254759054876'
            ]);
        }

        return response()->json([
            'success'=> false,
            'message'=> 'Payment Unsuccessful'
        ]);
        
    }

    /**
     * J-son Response to M-pesa API feedback - Success or Failure
     */
    public function createValidationResponse($result_code, $result_description)
    {
        $result = json_encode(["ResultCode" => $result_code, "ResultDesc" => $result_description]);
        $response = new Response();
        $response->headers->set("Content-Type", "application/json; charset=utf-8");
        $response->setContent($result);
        return $response;
    }
    /**
     *  M-pesa Validation Method
     * Safaricom will only call your validation if you have requested by writing an official letter to them
     */
    public function mpesaValidation(Request $request)
    {
        // if ($request->token !== env('MPESA_CONFIRMATION_SECRET', 123456)) {
        //     $response = new Response();
        //     $response->headers->set("Content-Type", "application/json; charset=utf-8");
        //     $response->setContent(json_encode(["C2BPaymentConfirmationResult" => "Invalid Authorization"]));
        //     return $response;
        // }

        $result_code = "0";
        $result_description = "Accepted validation request.";
        return $this->createValidationResponse($result_code, $result_description);
    }
    /**
     * M-pesa Transaction confirmation method, we save the transaction in our databases
     */
    public function mpesaConfirmation(Request $request)
    {
        if ($request->token !== env('MPESA_CONFIRMATION_SECRET', 123456)) {
            $response = new Response();
            $response->headers->set("Content-Type", "application/json; charset=utf-8");
            $response->setContent(json_encode(["C2BPaymentConfirmationResult" => "Invalid Authorization"]));
            return $response;
        }

        if ($request->isMethod('get')) {
            $response = new Response();
            $response->headers->set("Content-Type", "application/json; charset=utf-8");
            $response->setContent(json_encode(["C2BPaymentConfirmationResult" => "Success"]));
            return $response;
        }        

        $content = json_decode($request->getContent());

        $transactionType = $content->TransactionType;
        $cleanRef = $content->BillRefNumber;

        if ($content->BillRefNumber) {
            if (stripos($content->BillRefNumber, 'STK') !== false) {
                $transactionType = 'Mpesa';
                $cleanRef = str_replace('STK_', '', $content->BillRefNumber);
            }
        }

        $mpesa_transaction = new MpesaTransaction();
        $mpesa_transaction->TransactionType = $transactionType;
        $mpesa_transaction->TransID = $content->TransID;
        $mpesa_transaction->TransTime = $content->TransTime;
        $mpesa_transaction->TransAmount = $content->TransAmount;
        $mpesa_transaction->BusinessShortCode = $content->BusinessShortCode;
        $mpesa_transaction->BillRefNumber = $cleanRef;
        $mpesa_transaction->InvoiceNumber = $content->InvoiceNumber;
        $mpesa_transaction->ThirdPartyTransID = $content->ThirdPartyTransID;
        $mpesa_transaction->MSISDN = $content->MSISDN;
        $mpesa_transaction->FirstName = $content->FirstName;
        $mpesa_transaction->MiddleName = $content->MiddleName;
        $mpesa_transaction->LastName = $content->LastName;
        $mpesa_transaction->save();

        ProcessMpesaTransactionConfirmationJob::dispatch($mpesa_transaction);
        // Responding to the confirmation request
        $response = new Response();
        $response->headers->set("Content-Type", "application/json; charset=utf-8");
        $response->setContent(json_encode(["C2BPaymentConfirmationResult" => "Success"]));
        return $response;
    }

    /**
     * M-pesa Register Validation and Confirmation method
     */
    public function mpesaRegisterUrls()
    {
        return $this->paymentActions->registerUrls();
    }

    public function simulate()
    {
        return $this->paymentActions->simulate();
    }
}
