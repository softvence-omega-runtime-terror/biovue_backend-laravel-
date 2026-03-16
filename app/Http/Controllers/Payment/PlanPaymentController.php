<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Notifications\SubscriptionNotification;
use GPBMetadata\Google\Api\Auth;
use Illuminate\Http\Request;
use App\Models\PlanPayment;
use App\Models\Plan;
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
 * Process payment (Stripe Checkout Session)
 */
public function processPayment(Request $request)
{
    $request->validate([
        'plan_id' => 'required|exists:plans,id',
        'billing' => 'required|in:monthly,annual', // add billing input
    ]);

    $plan = Plan::findOrFail($request->plan_id);
    $user = auth()->user();

    // Calculate final amount
    $finalPrice = $plan->price; // default monthly
    if ($request->billing === 'annual') {
        $finalPrice = $plan->price * 12 * 0.9; // 12 months × 20% off
    }

    try {
        // Create temporary payment record
        $payment = PlanPayment::create([
            'user_id'        => $user->id,
            'plan_id'        => $plan->id,
            'transaction_id' => 'TEMP_' . uniqid(),
            'amount'         => $finalPrice,
            'currency'       => 'usd',
            'status'         => 'unpaid',
        ]);

        // Free plan check
        if ($finalPrice <= 0) {
            $payment->update(['status' => 'paid']);
            $user->update(['plan_id' => $plan->id]);

            return response()->json([
                'success' => true,
                'message' => 'Free plan activated successfully.',
                'amount'  => $finalPrice,
            ]);
        }

        // Stripe Checkout Session
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
                'billing'    => $request->billing, // optional
            ],
            // 'success_url' => url('/api/v1/payment/success') . '?session_id={CHECKOUT_SESSION_ID}',
            // 'cancel_url'  => url('/api/v1/payment/cancel'),


            'success_url' => 'https://biovue-frontend.vercel.app/payment/show?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => url('/api/v1/payment/cancel'),
        ]);

        $payment->update(['transaction_id' => $session->id]);

        return response()->json([
            'success'      => true,
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
            'amount'       => $finalPrice,
        ]);

    } catch (\Exception $e) {
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
