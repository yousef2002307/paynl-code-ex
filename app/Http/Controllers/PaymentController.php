<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PayNL\Sdk\Model\Request\OrderCreateRequest;
use PayNL\Sdk\Model\Request\TransactionStatusRequest;
use PayNL\Sdk\Config\Config;
use PayNL\Sdk\Exception\PayException;

class PaymentController extends Controller
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
     * Show payment form
     */
    public function create()
    {
        return view('payment.create');
    }

    /**
     * Process payment
     */
    public function store(Request $request)
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
            $payRequest->setReturnurl(route('payment.return'));
            $payRequest->setDescription($validated['description']);
            $payRequest->setTestmode(config('services.paynl.test_mode', true));

            // Start the payment
            $payOrder = $payRequest->start();

            // Store order ID in session
            session(['paynl_order_id' => $payOrder->getOrderId()]);
  
            // Redirect to Pay.nl payment page
            return redirect($payOrder->getPaymentUrl());

        } catch (PayException $e) {
            Log::error('Payment error: ' . $e->getMessage());
            return redirect()->route('payment.create')->with('error', 'Payment error: ' . $e->getFriendlyMessage());
        } catch (\Exception $e) {
            Log::error('Payment error: ' . $e->getMessage());
            return redirect()->route('payment.create')->with('error', 'Payment error: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment return
     */
    public function handleReturn(Request $request)
    {
        try {
            $orderId = session('paynl_order_id');
            
            if (!$orderId) {
                return redirect()->route('payment.create')->with('error', 'No order found');
            }

            // Get order status
            $statusRequest = new TransactionStatusRequest($orderId);
            $statusRequest->setConfig($this->getConfig());

            $transaction = $statusRequest->start();
            $status = $transaction->getStatusCode();

            // Status: 100 = Approved, 90 = Pending, 80 = Cancelled, 70 = Denied, etc.
            if ($status == 100) {
                session()->forget('paynl_order_id');
           //     \App\Models\User::factory()->create(); // Example action on successful payment
                return redirect()->route('payment.create')->with('success', 'Payment successful! Order ID: ' . $orderId);
            } elseif ($status == 90) {
                return redirect()->route('payment.create')->with('warning', 'Payment pending');
            } else {
                return redirect()->route('payment.create')->with('error', 'Payment was not completed');
            }

        } catch (PayException $e) {
            return redirect()->route('payment.create')->with('error', 'Error: ' . $e->getFriendlyMessage());
        } catch (\Exception $e) {
            Log::error('Payment return error: ' . $e->getMessage());
            return redirect()->route('payment.create')->with('error', 'Error processing payment');
        }
    }

    /**
     * Handle webhook from Pay.nl
     */
    public function handleWebhook(Request $request)
    {
        try {
            $orderId = $request->input('orderid');
            
            if ($orderId) {
                $statusRequest = new TransactionStatusRequest($orderId);
                $statusRequest->setConfig($this->getConfig());

                $transaction = $statusRequest->start();
                $status = $transaction->getStatusCode();

                // Update your database with transaction status
                // Example: Payment::where('paynl_order_id', $orderId)->update(['status' => $status]);
            }

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error'], 400);
        }
    }
}
