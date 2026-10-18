<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\DeviceInfoService;
use App\Services\EmailService;
use App\Services\SubscriptionService;
use App\Services\AuditLogger;
use App\Services\FraudDetectionService;
use App\Services\RealTimeEventLogger;
use App\Services\MetaPixelService;
use App\Services\PhoneNormalizer;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

use App\Models\OtpCode;
use App\Services\SystemWhatsAppService;
use Carbon\Carbon;

class AuthController extends Controller
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Register a new user
     */
    public function register(RegisterRequest $request)
    {
        try {
            // Fail before creating anything: an account whose OTP never arrives is
            // stranded, and the email is then taken so the visitor cannot retry.
            if ($request->phone_number && !SystemWhatsAppService::isAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خدمة إرسال كود التحقق غير متاحة حالياً، يرجى المحاولة بعد قليل',
                ], 503);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number ? $this->normalizePhone($request->phone_number) : null,
                'password' => Hash::make($request->password),
                'subscription_plan' => 'free',
                'subscription_status' => 'active',
                'email_verified_at' => null, // Requirement: Must verify via OTP
            ]);

            // Assign free plan subscription
            $this->subscriptionService->assignFreePlan($user);

            (new EmailService())->sendWelcomeEmail($user);

            AuditLogger::log('registered', 'user', $user->id, 'User registered', ['email' => $user->email]);

            MetaPixelService::track(
                'CompleteRegistration',
                $user,
                [
                    'content_name' => 'User Registration',
                    'content_type' => 'account',
                ],
                "registration-{$user->id}-" . time(),
                'https://wavixa.net'
            );

            // Generate and send OTP if phone number is provided
            if ($user->phone_number) {
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                OtpCode::create([
                    'user_id' => $user->id,
                    'phone_number' => $user->phone_number,
                    'code' => $code,
                    'purpose' => 'registration',
                    'expires_at' => now()->addMinutes(15),
                ]);

                if (!SystemWhatsAppService::sendOTP($user->phone_number, $code)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'فشل إرسال كود التحقق عبر واتساب، يرجى إعادة المحاولة',
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your phone number via OTP.',
                'data' => [
                    'user' => $user,
                    'otp_required' => true,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => 'An error occurred',
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                $this->recordLogin($user, $request, null, false, 'invalid_credentials', $request->email);
                FraudDetectionService::report('login_failed', 'medium', 'Failed login attempt with invalid email or password', [
                    'email' => $request->email,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Create token
            $newToken = $user->createToken('auth_token');
            $token = $newToken->plainTextToken;
            $tokenModel = $newToken->accessToken;

            // Record login history and device
            $deviceInfo = $this->recordLogin($user, $request, $tokenModel->id, true);
            $loginInfo = DeviceInfoService::parse($request->userAgent());

            AuditLogger::log('login', 'user', $user->id, 'User logged in', ['device_id' => $deviceInfo['device']?->id]);
            RealTimeEventLogger::log('login', "User {$user->name} logged in", $user->id, 'user', $user->id, "Login from {$loginInfo['browser']} on {$loginInfo['os']}", ['ip' => $request->ip()]);

            (new EmailService())->sendLoginAlert($user, $request->ip(), $loginInfo['browser'], $loginInfo['os']);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'device' => $deviceInfo,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => 'An error occurred',
            ], 500);
        }
    }

    /**
     * Record login history and device.
     */
    private function recordLogin(?User $user, Request $request, ?int $tokenId = null, bool $success = true, ?string $failureReason = null, ?string $attemptedEmail = null): array
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $info = DeviceInfoService::parse($userAgent);

        $loginHistory = LoginHistory::create([
            'user_id' => $user?->id,
            'attempted_email' => $attemptedEmail,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device_name' => $info['name'],
            'device_type' => $info['device_type'],
            'browser' => $info['browser'],
            'os' => $info['os'],
            'location' => null,
            'success' => $success,
            'failure_reason' => $failureReason,
            'login_at' => now(),
        ]);

        $device = null;
        if ($success && $tokenId) {
            // Mark other devices as not current
            UserDevice::forUser($user->id)->update(['is_current' => false]);

            $device = UserDevice::create([
                'user_id' => $user->id,
                'name' => $info['name'],
                'device_type' => $info['device_type'],
                'browser' => $info['browser'],
                'os' => $info['os'],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'personal_access_token_id' => $tokenId,
                'is_current' => true,
                'last_active_at' => now(),
            ]);
        }

        return [
            'login_history' => $loginHistory,
            'device' => $device,
        ];
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ], 200);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone_number' => 'sometimes|string|max:20',
            ]);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => 'An error occurred',
            ], 500);
        }
    }

    /**
     * Verify OTP for registration
     */
    public function verifyOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
        }

        $attemptKey = "otp_attempts:registration:{$user->id}";
        $attempts = Cache::get($attemptKey, 0);
        if ($attempts >= 5) {
            FraudDetectionService::report('otp_brute_force', 'critical', 'OTP brute-force limit reached for registration verification', [
                'email' => $user->email,
                'phone' => $user->phone_number,
                'user_id' => $user->id,
                'attempts' => $attempts,
                'purpose' => 'registration',
            ]);
            return response()->json(['success' => false, 'message' => 'تم تجاوز عدد المحاولات. يرجى طلب كود جديد.'], 429);
        }

        $otp = OtpCode::verify($user->id, $user->phone_number, $request->code, 'registration');

        if (!$otp) {
            Cache::put($attemptKey, $attempts + 1, now()->addMinutes(15));
            FraudDetectionService::report('otp_failed', 'high', 'Invalid OTP code entered during registration', [
                'email' => $user->email,
                'phone' => $user->phone_number,
                'attempts' => $attempts + 1,
                'purpose' => 'registration',
            ]);
            return response()->json(['success' => false, 'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته'], 400);
        }

        Cache::forget($attemptKey);
        $otp->update(['verified_at' => now()]);
        $user->update(['email_verified_at' => now()]);

        // Create token for auto-login
        $newToken = $user->createToken('auth_token');
        $token = $newToken->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل الحساب بنجاح',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Resend OTP
     */
    public function resendOTP(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user || !$user->phone_number) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود أو لا يملك رقم هاتف'], 404);
        }

        // Check for rate limiting
        $lastOtp = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'registration')
            ->latest()
            ->first();

        if ($lastOtp && $lastOtp->created_at->addMinutes(1)->isFuture()) {
            return response()->json(['success' => false, 'message' => 'يرجى الانتظار دقيقة قبل طلب كود جديد'], 429);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'user_id' => $user->id,
            'phone_number' => $user->phone_number,
            'code' => $code,
            'purpose' => 'registration',
            'expires_at' => now()->addMinutes(15),
        ]);

        if (!SystemWhatsAppService::sendOTP($user->phone_number, $code)) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال كود التحقق عبر واتساب، يرجى إعادة المحاولة',
            ], 500);
        }

        return response()->json(['success' => true, 'message' => 'تم إرسال كود جديد']);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $token = $user->currentAccessToken();
            $tokenId = $token?->id;

            // Mark device as logged out
            UserDevice::forUser($user->id)
                ->where('personal_access_token_id', $tokenId)
                ->update(['is_current' => false, 'last_active_at' => now()]);

            // Mark login history as logged out
            LoginHistory::forUser($user->id)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first()
                ?->update(['logout_at' => now()]);

            $token?->delete();

            AuditLogger::log('logout', 'user', $user->id, 'User logged out', ['token_id' => $tokenId]);
            RealTimeEventLogger::log('logout', "User {$user->name} logged out", $user->id, 'user', $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Logout successful',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
            ], 500);
        }
    }

    /**
     * Send forgot password email
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to send reset link',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Forgot password failed',
                'error' => 'An error occurred',
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                    ])->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset successfully',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => 'An error occurred',
            ], 500);
        }
    }

    /**
     * Send forgot password OTP via WhatsApp
     */
    public function forgotPasswordPhone(Request $request)
    {
        try {
            $request->validate([
                'phone_number' => 'required|string|min:8|max:20',
            ]);

            $phone = $this->normalizePhone($request->phone_number);
            $user = User::whereRaw("REGEXP_REPLACE(phone_number, '[^0-9]', '') = ?", [$phone])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهاتف غير مسجل',
                ], 404);
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
                'message' => 'تم إرسال كود التحقق إلى واتساب',
                'data' => [
                    'phone_number' => $phone,
                ],
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
    public function resetPasswordPhone(Request $request)
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
                    'message' => 'رقم الهاتف غير مسجل',
                ], 404);
            }

            $attemptKey = "otp_attempts:password_reset:{$phone}";
            $attempts = Cache::get($attemptKey, 0);
            if ($attempts >= 5) {
                FraudDetectionService::report('otp_brute_force', 'critical', 'OTP brute-force limit reached for password reset', [
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
                FraudDetectionService::report('otp_failed', 'high', 'Invalid OTP code entered during password reset', [
                    'phone' => $phone,
                    'user_id' => $user->id,
                    'attempts' => $attempts + 1,
                    'purpose' => 'password_reset',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته',
                ], 400);
            }

            Cache::forget($attemptKey);
            $otp->update(['verified_at' => now()]);

            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // Revoke existing tokens after a password reset.
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
     * Get current user's onboarding status.
     */
    public function getOnboarding(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'completed' => (bool) $user->onboarding_completed,
                'step' => $user->onboarding_step,
                'completed_at' => $user->onboarding_completed_at,
            ],
        ], 200);
    }

    /**
     * Update current user's onboarding status.
     */
    public function updateOnboarding(Request $request)
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'completed' => 'sometimes|boolean',
                'step' => 'sometimes|string|nullable',
                'skipped' => 'sometimes|boolean',
            ]);

            $update = [];

            if (array_key_exists('completed', $validated)) {
                $update['onboarding_completed'] = $validated['completed'];
            }

            if (array_key_exists('step', $validated)) {
                $update['onboarding_step'] = $validated['step'];
            }

            if (!empty($validated['completed']) || !empty($validated['skipped'])) {
                $update['onboarding_completed_at'] = now();
                $update['onboarding_step'] = $validated['step'] ?? ($validated['skipped'] ? 'skipped' : 'finished');
                $update['onboarding_completed'] = true;
            }

            $user->update($update);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding status updated',
                'data' => [
                    'completed' => (bool) $user->onboarding_completed,
                    'step' => $user->onboarding_step,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update onboarding status',
                'error' => 'An error occurred',
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

    /**
     * Verify email
     */
    public function verifyEmail(Request $request)
    {
        try {
            $user = User::findOrFail($request->route('id'));

            if ($user->email_verified_at) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email already verified',
                ], 200);
            }

            if (!hash_equals((string) $request->route('hash'), base64_encode(hash('sha256', $user->getEmailForVerification(), false)))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification link',
                ], 400);
            }

            $user->markEmailAsVerified();
            event(new Verified($user));

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification failed',
                'error' => 'An error occurred',
            ], 500);
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email|exists:users']);

            $user = User::where('email', $request->email)->first();

            if ($user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified',
                ], 400);
            }

            // Send verification email using Brevo
            $emailService = new EmailService();
            $emailService->sendVerificationEmail($user);

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resend verification failed',
                'error' => 'An error occurred',
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();
            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => 'An error occurred',
            ], 500);
        }
    }
}
