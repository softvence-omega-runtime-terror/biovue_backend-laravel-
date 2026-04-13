<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PlanPayment;
use App\Models\Plan;
use App\Models\ProjectionCredit;
use App\Notifications\AdminNotification;
use App\Notifications\SubscriptionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Stripe\Webhook;

class PlanPaymentController extends Controller
{
    /**
     * List all payments (with pagination)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);

            $payments = PlanPayment::with(['user', 'plan'])
                ->latest()
                ->paginate($perPage);

            $formatted = $payments->getCollection()->map(function ($payment) {
                return [
                    'id'             => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'amount'         => $payment->amount,
                    'currency'       => $payment->currency,
                    'status'         => $payment->status,
                    'created_at'     => $payment->created_at,
                    'updated_at'     => $payment->updated_at,
                    'user' => $payment->user ? [
                        'id'      => $payment->user->id,
                        'name'    => $payment->user->name,
                        'email'   => $payment->user->email,
                        'plan_id' => $payment->user->plan_id
                    ] : null,
                    'plan' => $payment->plan ? [
                        'id'    => $payment->plan->id,
                        'name'  => $payment->plan->name,
                        'price' => $payment->plan->price
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'last_page'    => $payments->lastPage(),
                    'per_page'     => $payments->perPage(),
                    'total'        => $payments->total(),
                ],
                'data' => $formatted,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show authenticated user's payments
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $payments = PlanPayment::with('plan')
                ->where('user_id', $user->id)
                ->latest()
                ->get();

            $latestPayment = $payments->first();

            $paymentHistory = $payments->map(function ($payment) {
                return [
                    'id'             => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'amount'         => $payment->amount,
                    'currency'       => $payment->currency,
                    'status'         => $payment->status,
                    'created_at'     => $payment->created_at,
                    'updated_at'     => $payment->updated_at,
                    'plan' => $payment->plan ? [
                        'id'    => $payment->plan->id,
                        'name'  => $payment->plan->name,
                        'price' => $payment->plan->price
                    ] : null,
                ];
            });

            $formattedLatest = $latestPayment ? [
                'id'             => $latestPayment->id,
                'transaction_id' => $latestPayment->transaction_id,
                'amount'         => $latestPayment->amount,
                'currency'       => $latestPayment->currency,
                'status'         => $latestPayment->status,
                'created_at'     => $latestPayment->created_at,
                'updated_at'     => $latestPayment->updated_at,
                'plan' => $latestPayment->plan ? [
                    'id'    => $latestPayment->plan->id,
                    'name'  => $latestPayment->plan->name,
                    'price' => $latestPayment->plan->price
                ] : null,
            ] : null;

            return response()->json([
                'success' => true,
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'latest_payment'   => $formattedLatest,
                'payment_history'  => $paymentHistory,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user info: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Payment success
     */
    public function paymentSuccess(Request $request)
    {
        $user = auth()->user();

        $admin = User::find(1);

        $admin->notify(new AdminNotification('New Subscription', "$user->name is onboarded",'subscription_message'));

        $user->notify(new SubscriptionNotification('New Subscription', 'Your Subscription Is Successful','subscription_message'));

        return response()->json([
            'success'    => true,
            'session_id' => $request->query('session_id'),
            'message'    => 'Payment completed. Subscription will be activated shortly after verification.',
        ]);
    }

    /**
     * Payment cancel
     */
    public function paymentCancel()
    {
        return response()->json([
            'success' => false,
            'message' => 'Payment cancelled. You can retry anytime.',
        ]);
    }
    /**
     * Process Subscription Payment
     */
    public function processPayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing' => 'required|in:monthly,half_annual,annual,custom',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = auth()->user();

        $stripePriceId = ($request->billing === 'annual') 
                        ? $plan->stripe_price_id_annual 
                        : $plan->stripe_price_id;

        if (!$stripePriceId) {
            return response()->json([
                'success' => false,
                'message' => "Stripe Price ID missing for this plan."
            ], 400);
        }

        $finalPrice = ($request->billing === 'annual') ? ($plan->price * 12 * 0.9) : $plan->price;

        try {
            $payment = PlanPayment::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'amount'         => $finalPrice,
                'currency'       => 'usd',
                'billing'        => $request->billing,
                'status'         => 'unpaid',
                'transaction_id' => 'PENDING_' . uniqid(),
            ]);

            $stripe = new StripeClient(config('services.stripe.secret'));

            $session = $stripe->checkout->sessions->create([
                'customer_email' => $user->email,
                'line_items' => [[
                    'price' => $stripePriceId, 
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'metadata' => [
                    'payment_id' => $payment->id, 
                    'user_id'    => $user->id,
                ],
                'success_url' => 'https://biovuedigitalwellness.com/payment/show?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => url('/api/v1/payment/cancel'),
            ]);

            $payment->update(['transaction_id' => $session->id]);

            return response()->json([
                'success'      => true,
                'checkout_url' => $session->url,
                'payment_id'   => $payment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe Checkout Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Final & Stable Webhook Handler
     */
    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Signature Error: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        $session = $event->data->object;
        $subscriptionId = $session->subscription ?? null;

        if ($event->type === 'checkout.session.completed' || $event->type === 'invoice.payment_succeeded') {
            
            $paymentId = $session->metadata->payment_id ?? null;

            if (!$paymentId && $subscriptionId) {
                $lastPayment = PlanPayment::where('stripe_subscription_id', $subscriptionId)->latest()->first();
                $paymentId = $lastPayment ? $lastPayment->id : null;
            }

            if ($paymentId) {
                DB::beginTransaction();
                try {
                    $payment = PlanPayment::with('user', 'plan')->find($paymentId);

                    if ($payment && $payment->status !== 'paid') {
                        $payment->update([
                            'status' => 'paid',
                            'stripe_subscription_id' => $subscriptionId,
                            'paid_at' => now(),
                            'start_date' => now(),
                            'end_date' => ($payment->billing === 'annual') ? now()->addYear() : now()->addMonth(),
                        ]);

                        if ($payment->user) {
                            $payment->user->update(['plan_id' => $payment->plan_id]);
                            
                            $credit = ProjectionCredit::firstOrNew(['user_id' => $payment->user_id]);
                            $credit->projection_limit = ($credit->projection_limit ?? 0) + ($payment->plan->projection_limit ?? 0);
                            $credit->member_limit = ($credit->member_limit ?? 0) + ($payment->plan->member_limit ?? 0);
                            $credit->save();

                            $payment->user->notify(new SubscriptionNotification('Subscription Active', 'Your payment was successful.', 'subscription_message'));
                            $admin = User::find(1);
                            if($admin) $admin->notify(new AdminNotification('New Sale', $payment->user->name . ' bought a plan.', 'subscription_message'));
                        }
                    }
                    DB::commit();
                    return response('Success', 200);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Webhook Update Error: ' . $e->getMessage());
                    return response('DB Error', 500);
                }
            }
        }

        return response('Webhook Received', 200);
    }

    /**
     * Cancel Subscription
     */
    public function cancelSubscription(Request $request)
    {
        $user = auth()->user();

        if ($user->user_type === 'professional') {
            if ($user->created_at->gt(now()->subMonths(6))) {
                return response()->json([
                    'success' => false,
                    'message' => "Professional users can cancel only after 6 months."
                ], 403);
            }
        }

        $payment = PlanPayment::where('user_id', $user->id)
                    ->where('status', 'paid')
                    ->whereNotNull('stripe_subscription_id')
                    ->latest()
                    ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'No active subscription found.'], 404);
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            
            $stripe->subscriptions->update($payment->stripe_subscription_id, [
                'cancel_at_period_end' => true
            ]);

            return response()->json(['success' => true, 'message' => 'Cancellation processed. Access remains until period end.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}