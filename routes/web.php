<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentWebhookController;

Route::get('/', function () {
    return view('welcome');
});

// Fallback webhook routes in case webhook is configured at root or without /api prefix
Route::post('/', function (\Illuminate\Http\Request $request) {
    if ($request->has('content') || $request->has('gateway') || $request->has('transferType') || $request->has('transactionDate') || $request->has('code')) {
        return app(PaymentWebhookController::class)->webhook($request);
    }
    return response()->json(['status' => 'Dola Bakery Webhook Root Active']);
});

Route::any('/payment/webhook', [PaymentWebhookController::class, 'webhook']);

