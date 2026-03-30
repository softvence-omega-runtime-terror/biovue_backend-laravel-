<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\SubscriptionNotification;
use GPBMetadata\Google\Api\Auth;
use Illuminate\Http\Request;
use App\Models\PlanPayment;
use App\Models\Plan;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


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

    //processpayment

    public function paymentProcess(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing' => 'required|in:monthly,annual',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = auth()->user();

        $finalPrice = $plan->price;
        if ($request->billing === 'annual') {
            $finalPrice = $plan->price * 12 * 0.9;
        }

        $durationDays = 0;

        if ($request->billing === 'annual') {
            $durationDays = 365;
        } elseif ($request->billing === 'monthly') {
            $durationDays = 30;
        } else {
            $durationDays = (int)($plan->duration ?? 0);
        }

        try {
            $payment = PlanPayment::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'transaction_id' => 'TEMP_' . uniqid(),
                'amount'         => $finalPrice,
                'currency'       => 'usd',
                'billing'        => $request->billing,
                'status'         => 'unpaid',
            ]);
            if ($finalPrice <= 0) {
                $payment->update(['status' => 'paid']);
                $user->update(['plan_id' => $plan->id]);

               \App\Models\ProjectionCredit::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'projection_limit' => $plan->projection_limit,
                        'member_limit' => $plan->member_limit,
                        'updated_at'       => now(),
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Free plan activated successfully.',
                    'amount' => $finalPrice,
                    'plan_duration_days' => $durationDays,
                ]);
            }


            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency'    => 'usd',
                        'unit_amount' => (int)($finalPrice * 100),
                        'product_data' => [
                            'name' => $plan->name,
                            'description' => "Billing: " . ucfirst($request->billing),
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'user_id'    => $user->id,
                    'billing'    => $request->billing,
                ],
                'success_url' => 'https://biovuedigitalwellness.com/payment/show?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => url('/api/v1/payment/cancel'),
            ]);

            $payment->update(['transaction_id' => $session->id]);

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
                'amount'       => $finalPrice,
                'plan_duration_days' => $durationDays,
            ]);

        } catch (\Exception $e) {
            \Log::error('Stripe Payment Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function processPayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing' => 'required|in:monthly,annual',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = auth()->user();

        // ✅ Calculate price
        $finalPrice = $plan->price;

        if ($request->billing === 'annual') {
            $finalPrice = $plan->price * 12 * 0.9;
        }

        // ✅ Duration based on USER INPUT (IMPORTANT)
        $durationDays = 0;

        if ($request->billing === 'annual') {
            $durationDays = 365;
        } elseif ($request->billing === 'monthly') {
            $durationDays = 30;
        } else {
            $durationDays = (int)($plan->duration ?? 0);
        }

        try {
            // ✅ Save payment with billing
            $payment = PlanPayment::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'transaction_id' => 'TEMP_' . uniqid(),
                'amount'         => $finalPrice,
                'currency'       => 'usd',
                'billing'        => $request->billing, // ✅ VERY IMPORTANT
                'status'         => 'unpaid',
            ]);

            // ✅ Free plan
            if ($finalPrice <= 0) {
                $payment->update(['status' => 'paid']);
                $user->update(['plan_id' => $plan->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Free plan activated successfully.',
                    'amount' => $finalPrice,
                    'plan_duration_days' => $durationDays,
                ]);
            }

            // ✅ Stripe
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency'    => 'usd',
                        'unit_amount' => (int)($finalPrice * 100),
                        'product_data' => [
                            'name' => $plan->name,
                            'description' => implode(", ", $plan->features ?? []),
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'user_id'    => $user->id,
                    'billing'    => $request->billing,
                ],
                'success_url' => 'https://biovuedigitalwellness.com/payment/show?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => url('/api/v1/payment/cancel'),
            ]);

            $payment->update(['transaction_id' => $session->id]);

            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
                'amount'       => $finalPrice,
                'plan_duration_days' => $durationDays,
            ]);

        } catch (\Exception $e) {
            \Log::error('Stripe Payment Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Stripe error: ' . $e->getMessage(),
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
     * Stripe Webhook: Final verification
     */

    public function webhookHandle(Request $request)
    {
        $payload = $request->getContent();
        $session = json_decode($payload)->data->object ?? null;

        if (!$session) return response('Invalid Payload', 400);

        // Metadata theke ID integer cast kore nin
        $paymentId = (int) ($session->metadata->payment_id ?? 0);

        try {
            DB::beginTransaction();

            // Database theke record khuje ber kora
            $payment = PlanPayment::where('id', $paymentId)->lockForUpdate()->first();

            if (!$payment) {
                DB::rollBack();
                return response('Payment record not found', 404);
            }

            // Status update logic
            $payment->update([
                'status'            => 'paid',
                'stripe_session_id' => $session->id,
                'start_date'        => now(),
                'end_date'          => ($payment->billing === 'annual') ? now()->addYear() : now()->addMonth(),
            ]);

            if ($payment->user) {
                $payment->user->update(['plan_id' => $payment->plan_id]);
                $projectionCredit = \App\Models\ProjectionCredit::firstOrNew(['user_id' => $payment->user_id]);

                $projectionCredit->member_limit = ($projectionCredit->member_limit ?? 0) + $payment->plan->member_limit;
                $projectionCredit->projection_limit = ($projectionCredit->projection_limit ?? 0) + $payment->plan->projection_limit;
                $projectionCredit->updated_at = now();

                $projectionCredit->save();
            }

            DB::commit();
            return response('Subscription activated', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Stripe Webhook DB Error: ' . $e->getMessage());

            // Exact error dekhar jonno Postman-e eita return korun
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );

            if (!in_array($event->type, ['checkout.session.completed', 'payment_intent.succeeded'])) {
                return response('Event ignored', 200);
            }

            $session   = $event->data->object;
            $paymentId = $session->metadata->payment_id ?? null;

            if (!$paymentId) {
                return response('Missing payment ID', 400);
            }

            $payment = PlanPayment::find($paymentId);

            if (!$payment || $payment->status === 'paid') {
                return response('Already processed', 200);
            }

            // Mark payment as paid
            $payment->update(['status' => 'paid']);

            // Update user's subscription plan
            if ($payment->user) {
                $payment->user->update(['plan_id' => $payment->plan_id]);
            }

            return response('Subscription activated', 200);

        } catch (\Exception $e) {
            \Log::error('Stripe Webhook error', ['error' => $e->getMessage()]);
            return response('Webhook error: ' . $e->getMessage(), 400);
        }
    }
}
