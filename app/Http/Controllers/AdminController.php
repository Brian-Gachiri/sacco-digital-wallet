<?php

namespace App\Http\Controllers;

use App\Http\Services\PaymentActions;
use App\Models\MpesaTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function mpesaTransactions(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        $mpesaTransactions = MpesaTransaction::whereMonth('created_at', $month)->whereYear('created_at', $year)->get();

        return response()->json($mpesaTransactions);
    }

    public function triggerManualReconciliation(Request $request){
        $checkout_request_id = $request->input('checkout_request_id');

        // TODO
    }
}
