<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
        }
        form {
            background: #f5f5f5;
            padding: 30px;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0056b3;
        }
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <h1>Pay.nl Payment</h1>

    @if ($errors->any())
        <div class="alert alert-error">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-error">
            {{ session('warning') }}
        </div>
    @endif

    <form action="{{ route('payment.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="amount">Amount (€)</label>
            <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" placeholder="Order description" required>
        </div>

        <button type="submit">Pay with Pay.nl</button>
    </form>
</body>
</html>
