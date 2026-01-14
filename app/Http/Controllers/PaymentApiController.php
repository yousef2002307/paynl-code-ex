<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PayNL\Sdk\Model\Request\OrderCreateRequest;
use PayNL\Sdk\Model\Request\TransactionStatusRequest;
use PayNL\Sdk\Config\Config;
use PayNL\Sdk\Exception\PayException;

class PaymentApiController extends Controller
{
    private function getConfig(): Config
    {
        $config = new Config();
        // Username = API Token (AT-####-####)
        // Password = Token Code (API key)
        $config->setUsername(config('services.paynl.api_token'));
        $config->setPassword(config('services.paynl.token_code'));
        return $config;
    }

    /**
     * Create payment and return payment URL
     */
    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        try {
            // Create order request
            $payRequest = new OrderCreateRequest();
            $payRequest->setConfig($this->getConfig());
            $payRequest->setServiceId(config('services.paynl.service_id'));
            $payRequest->setAmount((float)$validated['amount']);
            $payRequest->setReturnurl(route('api.payment.callback'));
            $payRequest->setDescription($validated['description']);
            $payRequest->setTestmode(config('services.paynl.test_mode', true));

            // Start the payment
            $payOrder = $payRequest->start();

            // Return payment URL to frontend
            return response()->json([
                'success' => true,
                'order_id' => $payOrder->getOrderId(),
                'payment_url' => $payOrder->getPaymentUrl(),
            ], 200);

        } catch (PayException $e) {
            Log::error('Payment API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment error: ' . $e->getFriendlyMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment error: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle payment callback from Pay.nl (redirect from payment page)
     */
    public function handleCallback(Request $request)
    {
        try {
            // PayNL sends 'id' parameter, not 'orderid'
            $orderId = $request->input('id') ?? $request->input('orderid');
            $statusCode = $request->input('statusCode');
            
            if (!$orderId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No order found',
                ], 400);
            }

            // If statusCode is provided, use it directly
            if ($statusCode !== null) {
                $status = (int)$statusCode;
            } else {
                // Otherwise, fetch the status from PayNL
                $statusRequest = new TransactionStatusRequest($orderId);
                $statusRequest->setConfig($this->getConfig());
                $transaction = $statusRequest->start();
                $status = $transaction->getStatusCode();
            }

            // Status: 100 = Approved, 90 = Pending, 80 = Cancelled, 70 = Denied, etc.
            if ($status == 100) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment successful',
                    'order_id' => $orderId,
                    'status' => 'approved',
                    'status_code' => $status,
                ], 200);
            } elseif ($status == 90) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment pending',
                    'order_id' => $orderId,
                    'status' => 'pending',
                    'status_code' => $status,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment was not completed',
                    'order_id' => $orderId,
                    'status' => 'failed',
                    'status_code' => $status,
                ], 400);
            }

        } catch (PayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getFriendlyMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment callback error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing payment',
            ], 400);
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|string',
        ]);

        try {
            $statusRequest = new TransactionStatusRequest($validated['order_id']);
            $statusRequest->setConfig($this->getConfig());

            $transaction = $statusRequest->start();
            $status = $transaction->getStatusCode();

            $statusMap = [
                100 => 'approved',
                90 => 'pending',
                80 => 'cancelled',
                70 => 'denied',
            ];

            return response()->json([
                'success' => true,
                'order_id' => $validated['order_id'],
                'status_code' => $status,
                'status' => $statusMap[$status] ?? 'unknown',
            ], 200);

        } catch (PayException $e) {
            Log::error('Payment status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getFriendlyMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status',
            ], 400);
        }
    }

    /**
     * Handle webhook from Pay.nl
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            // PayNL sends 'id' parameter in webhook, but also check for 'orderid' for compatibility
            $orderId = $request->input('id') ?? $request->input('orderid');
            $statusCode = $request->input('statusCode');
            
            if ($orderId) {
                $status = $statusCode ?? null;
                
                if ($status === null) {
                    $statusRequest = new TransactionStatusRequest($orderId);
                    $statusRequest->setConfig($this->getConfig());
                    $transaction = $statusRequest->start();
                    $status = $transaction->getStatusCode();
                }

                // Update your database with transaction status
                // Example: Payment::where('paynl_order_id', $orderId)->update(['status' => $status]);
                
                Log::info('Payment webhook received', [
                    'order_id' => $orderId,
                    'status' => $status,
                ]);
            }

            return response()->json(['status' => 'ok'], 200);

        } catch (\Exception $e) {
            Log::error('Payment webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 400);
        }
    }
}
