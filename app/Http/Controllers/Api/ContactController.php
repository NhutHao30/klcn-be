<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;

class ContactController extends Controller
{
    public function sendContactEmail(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'required|string',
        ]);

        try {
            $contactData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'message' => $request->message,
            ];

            // Send email to store owner/support via RabbitMQ Job to avoid blocking UI
            $adminEmail = env('MAIL_USERNAME', 'haotruong01007@gmail.com');
            \App\Jobs\SendContactEmailJob::dispatch($adminEmail, $contactData);

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu của bạn đã được gửi thành công. Chúng tôi sẽ liên hệ lại sớm nhất có thể!'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi gửi email: ' . $e->getMessage()
            ], 500);
        }
    }
}
