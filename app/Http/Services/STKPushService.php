<?php
namespace App\Http\Services;


use App\Models\MpesaSTKPush;
use Illuminate\Http\Request;

class STKPushService
{

    private $pass_key;
    private $short_code;
    private $amount;
    private $sender_phone;
    private $payer_phone;
    private $receiving_shortcode;
    private $callback_url;
    private $account_reference;
    private $transaction_type = 'CustomerPayBillOnline';
    private $remarks;

    private $failed = false;
    private $response = 'An an unknown error occurred';
    public $paymentActions;

    public function __construct(PaymentActions $paymentActions)
    {
        $this->paymentActions = $paymentActions;
    }


    public function confirm(Request $request)
    {
        $payload = json_decode($request->getContent());
        logger(json_encode($payload));

        if (property_exists($payload, 'Body') && $payload->Body->stkCallback->ResultCode == '0') {

            $merchant_request_id = $payload->Body->stkCallback->MerchantRequestID;
            $checkout_request_id = $payload->Body->stkCallback->CheckoutRequestID;

            $stk_push_model = MpesaSTKPush::where('merchant_request_id', $merchant_request_id)
                ->where('checkout_request_id', $checkout_request_id)->first();


            $data = [
                'result_desc' => $payload->Body->stkCallback->ResultDesc,
                'result_code' => $payload->Body->stkCallback->ResultCode,
                'merchant_request_id' => $merchant_request_id,
                'checkout_request_id' => $checkout_request_id,
                'amount' => $payload->Body->stkCallback->CallbackMetadata->Item[0]->Value,
                'mpesa_receipt_number' => $payload->Body->stkCallback->CallbackMetadata->Item[1]->Value,
                'transaction_date' => $payload->Body->stkCallback->CallbackMetadata->Item[3]->Value,
                'phone_number' => $payload->Body->stkCallback->CallbackMetadata->Item[4]->Value
            ];

            $this->paymentActions->updatePayment($data);

            if ($stk_push_model) {

                $stk_push_model->fill($data)->save();

            } else {
                MpesaSTKPush::create($data);
            }

        } else {
            $this->response = $payload;
            $this->failed = true;
        }

        return $this;
    }

    public function getResponse(){

        return $this->response;
    }

    public function failed()
    {
        return $this->failed;
    }
}