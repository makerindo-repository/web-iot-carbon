<?php

namespace App\Http\Controllers;

use App\Models\PaymentHistory;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class PaymentController extends Controller
{
    public function createTransaction($planId)
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $user = Auth::user();

        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $orderId = 'ORDER-' . $user->id . '-' . time();

        $payment = PaymentHistory::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'order_id' => $orderId,
            'transaction_status' => 'pending'
        ]);

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $plan->price
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email
            ],
        ];

        $snapToken = Snap::getSnapToken($params);

        return response()->json([
            'snap_token' => $snapToken,
            'redirect_url' => "https://app.sandbox.midtrans.com/snap/v2/vtweb/$snapToken"
        ]);
    }

    public function callback(Request $request)
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
        
        $notif = new Notification();

        $payment = PaymentHistory::where('order_id', $notif->order_id)->first();

        if ($payment) {
            $payment->update([
                'payment_type' => $notif->payment_type,
                'transaction_status' => $notif->transaction_status,
                'raw_response' => json_encode($notif)
            ]);

            // jika sukses, update subscription
            if ($notif->transaction_status == 'settlement' || $notif->transaction_status == 'capture') {
                $subscription = $payment->user->subscription;
                $subscription->update([
                    'plan_id' => $payment->plan_id,
                    'expires_at' => now()->addDays($payment->plan->duration),
                    'used_quota' => 0
                ]);
            }
        }

        return response()->json([
            'status' => 'ok'
        ]);
    }
}
