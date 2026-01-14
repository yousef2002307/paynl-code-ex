# Pay.nl Payment Integration - Beginner's Guide

## 📋 Overview
This project integrates **Pay.nl** (a Dutch payment processor) into a Laravel application. It allows users to make online payments securely.

---

## 🔄 Payment Flow (Simple Explanation)

```
User fills form → Server creates order → User redirected to Pay.nl → User pays → Returns to app → Status checked
```

---

## 📁 File Structure

### 1. **Routes** (`routes/web.php`)
Routes are URLs that trigger specific actions.

```php
Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
```
- **URL**: `http://localhost:8000/payment`
- **Action**: Shows the payment form
- **Method**: `create()`

```php
Route::post('/payment', [PaymentController::class, 'store'])->name('payment.store');
```
- **URL**: `http://localhost:8000/payment` (via form submission)
- **Action**: Processes the payment form data
- **Method**: `store()`

```php
Route::get('/payment/return', [PaymentController::class, 'handleReturn'])->name('payment.return');
```
- **URL**: `http://localhost:8000/payment/return`
- **Action**: Handles when user returns from Pay.nl
- **Method**: `handleReturn()`

```php
Route::post('/payment/webhook', [PaymentController::class, 'handleWebhook'])->name('payment.webhook');
```
- **URL**: `http://localhost:8000/payment/webhook`
- **Action**: Receives payment status updates from Pay.nl server
- **Method**: `handleWebhook()`

---

## 🎮 Controller Methods (`PaymentController.php`)

### Method 1: `getConfig()` - Setup Connection
```php
private function getConfig(): Config
{
    $config = new Config();
    $config->setUsername(config('services.paynl.api_token'));      // Your API Token
    $config->setPassword(config('services.paynl.token_code'));     // Your Token Code
    return $config;
}
```
**What it does**: Creates a configuration object with your Pay.nl credentials
- Think of it as logging into Pay.nl
- Called before making any payment requests
- **Credentials are stored in `.env` file** (secure location)

---

### Method 2: `create()` - Show Payment Form
```php
public function create()
{
    return view('payment.create');
}
```
**What it does**: Displays the payment form to the user
- Shows input fields for amount and description
- User fills it with their payment details

---

### Method 3: `store()` - Create Payment Order

```php
public function store(Request $request)
{
    // Validate input
    $validated = $request->validate([
        'amount' => 'required|numeric|min:0.01',
        'description' => 'required|string|max:255',
    ]);
```
**Validates**: 
- Amount must be a number and at least €0.01
- Description must be text and max 255 characters

```php
    try {
        // Create order request
        $payRequest = new OrderCreateRequest();
        $payRequest->setConfig($this->getConfig());                          // Add credentials
        $payRequest->setServiceId(config('services.paynl.service_id'));      // Your sales location
        $payRequest->setAmount((float)$validated['amount']);                 // Payment amount
        $payRequest->setReturnurl(route('payment.return'));                  // Where to send user after payment
        $payRequest->setDescription($validated['description']);              // Order description
        $payRequest->setTestmode(config('services.paynl.test_mode', true));  // Use test mode
```
**Creates**: An order request with all payment details

```php
        // Start the payment
        $payOrder = $payRequest->start();
        
        // Store order ID in session
        session(['paynl_order_id' => $payOrder->getOrderId()]);
```
**Sends** the request to Pay.nl and stores the order ID (for later reference)

```php
        // Redirect to Pay.nl payment page
        return redirect($payOrder->getPaymentUrl());
```
**Redirects** user to Pay.nl's payment page where they enter card details

```php
    } catch (PayException $e) {
        Log::error('Payment error: ' . $e->getMessage());
        return redirect()->route('payment.create')->with('error', 'Payment error: ' . $e->getFriendlyMessage());
    }
```
**Error handling**: If something goes wrong, show an error message and log it

---

### Method 4: `handleReturn()` - Check Payment Status

```php
public function handleReturn(Request $request)
{
    try {
        $orderId = session('paynl_order_id');
        
        if (!$orderId) {
            return redirect()->route('payment.create')->with('error', 'No order found');
        }
```
**Gets**: The order ID we stored earlier from the session

```php
        // Get order status
        $statusRequest = new TransactionStatusRequest($orderId);
        $statusRequest->setConfig($this->getConfig());
        
        $transaction = $statusRequest->start();
        $status = $transaction->getStatusCode();
```
**Checks**: The payment status from Pay.nl

```php
        // Status: 100 = Approved, 90 = Pending, 80 = Cancelled, 70 = Denied, etc.
        if ($status == 100) {
            session()->forget('paynl_order_id');
            return redirect()->route('payment.create')->with('success', 'Payment successful! Order ID: ' . $orderId);
        } elseif ($status == 90) {
            return redirect()->route('payment.create')->with('warning', 'Payment pending');
        } else {
            return redirect()->route('payment.create')->with('error', 'Payment was not completed');
        }
```
**Handles different statuses**:
- **100**: Payment successful ✅
- **90**: Payment still processing ⏳
- **Other**: Payment failed ❌

---

### Method 5: `handleWebhook()` - Server-to-Server Update

```php
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
    }
```
**What it does**: 
- Pay.nl sends payment updates directly to this endpoint
- You can save the status to your database
- Runs automatically (user doesn't see it)
- Better than `handleReturn()` because it's more reliable

---

## 🎨 View (Form) - `resources/views/payment/create.blade.php`

### Display Messages
```blade
@if (session('error'))
    <div class="alert alert-error">
        {{ session('error') }}
    </div>
@endif
```
**Shows**: Red error messages if something went wrong

```blade
@if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif
```
**Shows**: Green success message if payment succeeded

### Payment Form
```blade
<form action="{{ route('payment.store') }}" method="POST">
    @csrf
```
- **action**: Sends data to `store()` method
- **@csrf**: Security token (prevents hacking)

```blade
    <label for="amount">Amount (€)</label>
    <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
```
- **Input for amount**: User enters how much they want to pay
- **step="0.01"**: Allows 2 decimals (euros and cents)
- **required**: Field can't be empty

```blade
    <label for="description">Description</label>
    <input type="text" id="description" name="description" placeholder="Order description" required>
```
- **Input for description**: What the payment is for (order ID, product name, etc.)

---

## ⚙️ Configuration - `.env` File

```env
PAYNL_SERVICE_ID=SL-2341-5723
```
- Your sales location code from Pay.nl

```env
PAYNL_API_TOKEN=AT-0111-4857
```
- Your API token (username)

```env
PAYNL_TOKEN_CODE=dd78b4445e6c3a6ebddd775549ed69991a34a92a
```
- Your token code (password)

```env
PAYNL_TEST_MODE=true
```
- `true` = Use test payments (no real money)
- `false` = Use real payments

---

## 🔐 Security Notes

1. **Never commit credentials** - Keep `.env` file out of Git
2. **Test mode** - Always test with `PAYNL_TEST_MODE=true` before going live
3. **CSRF token** - `@csrf` prevents unauthorized form submissions
4. **Error logging** - Errors are logged but not shown to users

---

## 🧪 How to Test

1. **Visit payment page**: `http://localhost:8000/payment`
2. **Fill the form**:
   - Amount: `10.00`
   - Description: `Test Order`
3. **Click "Pay with Pay.nl"**
4. **You'll be redirected to Pay.nl test page**
5. **Complete test payment**
6. **Return to your app with success/error message**

---

## 📊 Status Codes Explained

| Code | Meaning | Action |
|------|---------|--------|
| 100 | ✅ Approved | Payment successful |
| 90 | ⏳ Pending | Still processing |
| 80 | ❌ Cancelled | User cancelled |
| 70 | ❌ Denied | Rejected by bank |

---

## 🐛 Common Errors & Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| "Unauthorized" | Wrong credentials | Check `.env` file values |
| "No order found" | Session expired | Start fresh payment |
| "ArgumentCountError" | Wrong method call | Use correct SDK methods |
| "getState() not found" | Wrong method name | Use `getStatusCode()` instead |

---

## 📚 Quick Reference

| Component | Purpose |
|-----------|---------|
| `PaymentController` | Handles all payment logic |
| `create()` | Show form |
| `store()` | Create order & send to Pay.nl |
| `handleReturn()` | Check status when user returns |
| `handleWebhook()` | Receive status updates from Pay.nl |
| `getConfig()` | Setup credentials |
| Routes | Define payment URLs |
| View | Show form to user |

---

## 🚀 Next Steps

1. **Save payment to database**: Create a `Payment` model to store payment records
2. **Add order fulfillment**: Deliver product/service after payment confirmed
3. **Add webhook verification**: Verify webhook requests are from Pay.nl
4. **Go live**: Set `PAYNL_TEST_MODE=false` and use real credentials

---

**Questions?** Check the official [Pay.nl PHP SDK documentation](https://github.com/paynl/php-sdk)
