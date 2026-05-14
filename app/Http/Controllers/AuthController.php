<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Http\Services\SmsService;
use App\Models\MemberDetail;
use App\Models\MpesaSTKPush;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    public $smsService;
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    public function register(Request $request)
    {

        $data = $request->all();

        if ($request->filled('phone_number')) {
            $data['phone_number'] = $this->formatNumber($data['phone_number'] ?? '');
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'numeric', 'unique:users,phone_number'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($request->password),
        ]);

        MemberDetail::create([
            'user_id' => $user->id,
            'national_id' => $request->national_id,
            'member_number' => rand(100000, 999999),
        ]);

        // Auth::login($user);

        $this->smsService->send($data['phone_number'], 'Welcome to ChezaCoop. Account created successfully');

        $user->tokens()->delete();

        $token = $user->createToken('web-app')->plainTextToken;
        $user->last_login = now();
        $user->save();

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['nullable', 'email'],
            'phone_number' => ['nullable', 'numeric'],
            'password' => ['required'],
        ]);

        if (!$request->filled('email') && !$request->filled('phone_number')) {
            throw ValidationException::withMessages([
                'contact' => ['Either email or phone number is required.'],
            ]);
        }

        $credentials = ['password' => $request->password];

        if ($request->filled('email')) {

            $credentials['email'] = $request->email;
        } else {

            $normalizedPhone = ltrim($request->phone_number, '+');
            $credentials['phone_number'] = $normalizedPhone;
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                ($request->filled('email') ? 'email' : 'phone_number') => ['Invalid login credentials.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->load([
            'roles',
            'subscriptions' => function ($query) {
                $query->select(['id', 'package_id', 'user_id', 'start_date', 'expiry_date'])
                    ->notExpired()
                    ->with([
                        'package' => function ($q) {
                            $q->select('name', 'type', 'id');
                        }
                    ]);
            }
        ]);
        $user->last_login = now();
        $user->save();

        // $user->tokens()->delete();

        $token = $user->createToken('web-app')->plainTextToken;


        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    public function resetPin(Request $request)
    {

        $user = $request->user;
        if ($user) {
            return response()->json(['errors' => "You are already logged in"], 422);
        }

        $validator = Validator::make($request->all(), [
            'phoneNumber' => ['nullable', 'numeric'],
            'email' => ['nullable', 'email'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!$request->filled('email') && !$request->filled('phoneNumber')) {
                $validator->errors()->add('contact', 'Either email or phone number is required.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $stkTransaction = MpesaSTKPush::where(
            'merchant_request_id',
            $request->MerchantRequestID
        )
            ->orWhere('checkout_request_id', $request->CheckoutRequestID)->first();

        if (!$stkTransaction) {
            $payment = Payment::with('user')->where('transaction_ref', $request->CheckoutRequestID)->first();
        } else {
            $payment = Payment::with('user')->where('transaction_ref', $stkTransaction->mpesa_receipt_number)->first();
        }

        if (!$payment) {
            throw ValidationException::withMessages([
                'payment' => ['Invalid Checkout Request ID'],
            ]);
        }

        $user = $payment->user;

        // if (!$user && $payment->status !== PaymentStatus::FAILED) {
        //      $plainPassword = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        //         $user = User::create([
        //             'phone_number' => $request->phone_number,    
        //             'name' => 'Subscriber',
        //             'password' => Hash::make($plainPassword),
        //         ]);
        //         $user->sendAccountCreatedNotification($plainPassword);
        //         $payment->user_id = $user->id;
        //         $payment->save();
        // }

        if ($user) {

            $plainPassword = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $user->password = Hash::make($plainPassword);
            $user->save();

            $loginUrl = config('app.frontend_url') . '/auth/signin';
            $message = "Your acount pin has been reset successfully. Pin: {$plainPassword}. Please log in here {$loginUrl}.";
            $number = $this->formatNumber($request->phoneNumber);
            $this->smsService->send($number, $message);

            return response()->json(['success' => true, 'message' => 'Pin Reset Successful'], 200);

        }

        return response()->json(['success' => false, 'message' => 'User Does not exist'], 200);

    }
    public static function formatNumber($data)
    {
        return '254' . substr($data, -9);
    }
}