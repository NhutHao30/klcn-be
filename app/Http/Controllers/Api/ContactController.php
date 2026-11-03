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

            // Send email directly to store owner email: haotruong01007@gmail.com
            $adminEmail = 'haotruong01007@gmail.com';
            try {
                \Illuminate\Support\Facades\Mail::to($adminEmail)->send(new \App\Mail\ContactMessageMail($contactData));
                \Illuminate\Support\Facades\Log::info("Đã gửi email liên hệ thành công tới {$adminEmail}");
            } catch (\Throwable $mailError) {
                \Illuminate\Support\Facades\Log::warning('Lỗi gửi email liên hệ trực tiếp: ' . $mailError->getMessage());
                try {
                    \App\Jobs\SendContactEmailJob::dispatch($adminEmail, $contactData);
                } catch (\Throwable $qErr) {}
            }

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu của bạn đã được gửi thành công. Chúng tôi sẽ liên hệ lại sớm nhất có thể!'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu của bạn đã được tiếp nhận thành công. Chúng tôi sẽ liên hệ lại sớm nhất có thể!'
            ]);
        }
    }
}
