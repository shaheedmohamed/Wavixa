<?php

namespace App\Modules\Auth\Controllers;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\FraudDetectionService;
use App\Services\PhoneNormalizer;
use App\Services\SystemWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController
{
    /**
     * Send forgot password OTP via WhatsApp
     */
    public function sendOtp(Request $request)
    {
        try {
            $request->validate([
                'phone_number' => 'required|string|min:8|max:20',
            ]);

            $phone = $this->normalizePhone($request->phone_number);
            $user = User::whereRaw("REGEXP_REPLACE(phone_number, '[^0-9]', '') = ?", [$phone])->first();

            // Always return the same generic response to avoid account enumeration.
            if (!$user) {
                return response()->json([
                    'success' => true,
                    'message' => 'إذا كان الرقم مسجلاً، سيتم إرسال كود التحقق إليه.',
                ]);
            }

            $lastOtp = OtpCode::where('user_id', $user->id)
                ->where('purpose', 'password_reset')
                ->latest()
                ->first();

            if ($lastOtp && $lastOtp->created_at->addMinutes(1)->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى الانتظار دقيقة قبل طلب كود جديد',
                ], 429);
            }

            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            OtpCode::create([
                'user_id' => $user->id,
                'phone_number' => $phone,
                'code' => $code,
                'purpose' => 'password_reset',
                'expires_at' => now()->addMinutes(15),
            ]);

            SystemWhatsAppService::sendOTP($phone, $code);

            return response()->json([
                'success' => true,
                'message' => 'إذا كان الرقم مسجلاً، سيتم إرسال كود التحقق إليه.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال كود التحقق',
            ], 500);
        }
    }

    /**
     * Reset password using WhatsApp OTP and auto-login
     */
    public function reset(Request $request)
    {
        try {
            $request->validate([
                'phone_number' => 'required|string|min:8|max:20',
                'code' => 'required|string|size:6',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $phone = $this->normalizePhone($request->phone_number);
            $user = User::whereRaw("REGEXP_REPLACE(phone_number, '[^0-9]', '') = ?", [$phone])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته.',
                ], 400);
            }

            $attemptKey = "otp_attempts:{$phone}";
            $attempts = Cache::get($attemptKey, 0);
            if ($attempts >= 5) {
                FraudDetectionService::report('otp_brute_force', 'critical', 'OTP brute-force limit reached for password reset via forgot page', [
                    'phone' => $phone,
                    'user_id' => $user->id,
                    'attempts' => $attempts,
                    'purpose' => 'password_reset',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'تم تجاوز عدد المحاولات. يرجى طلب كود جديد.',
                ], 429);
            }

            $otp = OtpCode::verify($user->id, $phone, $request->code, 'password_reset');

            if (!$otp) {
                Cache::put($attemptKey, $attempts + 1, now()->addMinutes(15));
                FraudDetectionService::report('otp_failed', 'high', 'Invalid OTP code entered during forgot password reset', [
                    'phone' => $phone,
                    'user_id' => $user->id,
                    'attempts' => $attempts + 1,
                    'purpose' => 'password_reset',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته.',
                ], 400);
            }

            Cache::forget($attemptKey);
            $otp->update(['verified_at' => now()]);

            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // Revoke all existing tokens after a password reset.
            $user->tokens()->delete();

            $newToken = $user->createToken('auth_token');
            $token = $newToken->plainTextToken;

            $user->update(['last_login_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير كلمة المرور وتسجيل الدخول بنجاح',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تغيير كلمة المرور',
            ], 500);
        }
    }

    /**
     * Normalize a phone number to bare digits in international form.
     */
    private function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::digits($phone);
    }
}
