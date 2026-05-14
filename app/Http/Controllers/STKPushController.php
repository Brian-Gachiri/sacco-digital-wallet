<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use App\Http\Services\STKPushService;

class STKPushController extends Controller
{
    public $result_code;
    public $result_desc;
    protected $stkPushService;

    public function  __construct(STKPushService $stkPushService)
    {
        $this->stkPushService = $stkPushService;
    }

    public function confirm(Request $request)
    {

        $stk_push_confirm = $this->stkPushService->confirm($request);

        if ($stk_push_confirm->failed()) {

            Log::error(json_encode($stk_push_confirm->getResponse()));
        } else {
            $this->result_code = 0;
            $this->result_desc = 'Success';
        }

        return response()->json([
            'ResultCode' => $this->result_code,
            'ResultDesc' => $this->result_desc,
        ]);
    }
}
