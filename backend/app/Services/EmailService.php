<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send a generic HTML email to a user.
     */
    private function sendTo(User $user, string $subject, string $html): array
    {
        try {
            Mail::html($html, function ($message) use ($user, $subject) {
                $message->to($user->email, $user->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return [
                'success' => true,
                'message' => 'Email sent',
            ];
        } catch (\Throwable $e) {
            \Log::error('Email send failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an HTML email to one or more arbitrary email addresses.
     */
    public function sendToAddresses(array $emails, string $subject, string $body): array
    {
        try {
            Mail::html($this->wrap($subject, $body), function ($message) use ($emails, $subject) {
                $message->to($emails)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return [
                'success' => true,
                'message' => 'Email sent',
            ];
        } catch (\Throwable $e) {
            \Log::error('Email send failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send email verification link.
     */
    public function sendVerificationEmail(User $user): array
    {
        $verificationUrl = $this->generateVerificationUrl($user);

        return $this->sendTo($user, 'Verify your Wavixa account', $this->getVerificationEmailTemplate($user->name, $verificationUrl));
    }

    /**
     * Send password reset email.
     */
    public function sendPasswordResetEmail(User $user, string $resetToken): array
    {
        $resetUrl = config('app.url') . '/reset-password?token=' . $resetToken . '&email=' . urlencode($user->email);

        return $this->sendTo($user, 'Reset Your Password', $this->getPasswordResetEmailTemplate($user->name, $resetUrl));
    }

    /**
     * Send welcome email after registration.
     */
    public function sendWelcomeEmail(User $user): array
    {
        $html = $this->wrap("مرحباً {$user->name}", '
            <p>شكراً لتسجيلك في Wavixa.</p>
            <p>حسابك جاهز لتجربة خدمات WhatsApp API.</p>
            <p>لو عندك أي استفسار، تواصل معنا على <a href="mailto:support@wavixa.com">support@wavixa.com</a>.</p>
        ');

        return $this->sendTo($user, 'Welcome to Wavixa', $html);
    }

    /**
     * Send login alert with device info.
     */
    public function sendLoginAlert(User $user, string $ip, string $browser, string $os): array
    {
        $time = now()->format('Y-m-d H:i:s');

        $html = $this->wrap('تنبيه تسجيل دخول', "
            <p>تم تسجيل الدخول إلى حسابك.</p>
            <ul>
                <li><strong>الوقت:</strong> {$time}</li>
                <li><strong>المتصفح:</strong> {$browser}</li>
                <li><strong>النظام:</strong> {$os}</li>
                <li><strong>IP:</strong> {$ip}</li>
            </ul>
            <p>لو مش أنت اللي سجّل، غيّر كلمة المرور فوراً وتواصل معنا على <a href=\"mailto:support@wavixa.com\">support@wavixa.com</a>.</p>
        ");

        return $this->sendTo($user, 'Wavixa - Login Alert', $html);
    }

    /**
     * Send API key creation confirmation.
     */
    public function sendApiKeyCreated(User $user, ApiKey $apiKey): array
    {
        $name = e($apiKey->name);
        $prefix = e($apiKey->prefix);
        $createdAt = $apiKey->created_at->format('Y-m-d H:i:s');

        $html = $this->wrap('تم إنشاء مفتاح API جديد', "
            <p>تم إنشاء مفتاح API جديد في حسابك.</p>
            <ul>
                <li><strong>الاسم:</strong> {$name}</li>
                <li><strong>البادئة:</strong> {$prefix}</li>
                <li><strong>وقت الإنشاء:</strong> {$createdAt}</li>
            </ul>
            <p>لو مش أنت اللي عملته، احذف المفتاح من لوحة التحكم فوراً.</p>
        ");

        return $this->sendTo($user, 'Wavixa - New API Key', $html);
    }

    /**
     * Send subscription confirmation.
     */
    public function sendSubscriptionCreated(User $user, Subscription $subscription): array
    {
        $planName = e($subscription->plan?->name ?? 'N/A');
        $status = e($subscription->status);
        $startsAt = $subscription->starts_at?->format('Y-m-d H:i:s') ?? '-'; // Fix method access on null
        $endsAt = $subscription->ends_at?->format('Y-m-d H:i:s') ?? '-';

        $html = $this->wrap('تأكيد الاشتراك', "
            <p>تم إنشاء أو تجديد اشتراكك.</p>
            <ul>
                <li><strong>الباقة:</strong> {$planName}</li>
                <li><strong>الحالة:</strong> {$status}</li>
                <li><strong>تاريخ البدء:</strong> {$startsAt}</li>
                <li><strong>تاريخ الانتهاء:</strong> {$endsAt}</li>
            </ul>
        ");

        return $this->sendTo($user, 'Wavixa - Subscription Confirmation', $html);
    }

    /**
     * Send device disconnected alert.
     */
    public function sendDeviceDisconnected(User $user, WhatsAppSession $session): array
    {
        $device = e($session->device_name ?? $session->phone_number ?? 'Unknown');
        $time = now()->format('Y-m-d H:i:s');

        $html = $this->wrap('جهاز WhatsApp غير متصل', "
            <p>فُقد الاتصال بأحد أجهزة WhatsApp المربوطة بحسابك.</p>
            <ul>
                <li><strong>الجهاز:</strong> {$device}</li>
                <li><strong>الحالة الحالية:</strong> {$session->status}</li>
                <li><strong>الوقت:</strong> {$time}</li>
            </ul>
            <p>يمكنك إعادة الربط من لوحة التحكم. لو احتجت مساعدة، كلمنا على <a href=\"mailto:support@wavixa.com\">support@wavixa.com</a>.</p>
        ");

        return $this->sendTo($user, 'Wavixa - Device Disconnected', $html);
    }

    /**
     * Send an important account/security notification.
     */
    public function sendImportantNotification(User $user, string $subject, string $body): array
    {
        return $this->sendTo($user, 'Wavixa - ' . $subject, $this->wrap($subject, $body));
    }

    /**
     * Generate verification URL.
     */
    private function generateVerificationUrl(User $user): string
    {
        $hash = hash('sha256', $user->email . $user->created_at);

        return config('app.frontend_url') . '/verify-email/' . $user->id . '/' . $hash;
    }

    /**
     * Wrap content in a professional, responsive email layout.
     */
    private function wrap(string $heading, string $body): string
    {
        $year = date('Y');
        $from = e(config('mail.from.address'));

        return "
            <!DOCTYPE html>
            <html dir='rtl' lang='ar'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Wavixa</title>
            </head>
            <body style='margin:0;padding:0;background-color:#f3f4f6;font-family:Tahoma,Arial,\"Helvetica Neue\",Helvetica,sans-serif;direction:rtl;text-align:right;'>
                <table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0'>
                    <tr>
                        <td align='center' style='padding:40px 20px;background-color:#f3f4f6;'>
                            <table width='600' cellspacing='0' cellpadding='0' border='0' style='width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(2,6,23,0.08);'>
                                <tr>
                                    <td style='background:#020617;padding:32px 40px;text-align:center;'>
                                        <h1 style='margin:0;color:#ffffff;font-size:26px;font-weight:900;letter-spacing:2px;'>WAVIXA</h1>
                                        <p style='margin:8px 0 0;color:#00C853;font-size:13px;font-weight:600;'>بوابة واتساب للأعمال</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='padding:40px 40px 32px;'>
                                        <h2 style='margin:0 0 24px;color:#020617;font-size:22px;font-weight:800;line-height:1.4;'>" . e($heading) . "</h2>
                                        <div style='color:#475569;font-size:16px;line-height:1.8;'>{$body}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='background:#f8fafc;padding:24px 40px;text-align:center;border-top:1px solid #e2e8f0;'>
                                        <p style='margin:0 0 10px;color:#64748b;font-size:13px;'>تحتاج مساعدة؟ <a href='mailto:{$from}' style='color:#00C853;text-decoration:none;font-weight:700;'>{$from}</a></p>
                                        <p style='margin:0;color:#94a3b8;font-size:12px;'>© {$year} Wavixa. جميع الحقوق محفوظة.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ";
    }

    /**
     * Get verification email template.
     */
    private function getVerificationEmailTemplate(string $name, string $verificationUrl): string
    {
        return $this->wrap('تفعيل حسابك', "
            <p>أهلاً {$name}،</p>
            <p>شكراً لتسجيلك. اضغط على الزر تحت لتفعيل بريدك:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$verificationUrl}' style='background-color: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                    تفعيل الحساب
                </a>
            </div>
            <p>أو انسخ الرابط ده:</p>
            <p style='word-break: break-all; color: #666;'>{$verificationUrl}</p>
            <p>الرابط ينتهي خلال 24 ساعة.</p>
        ");
    }

    /**
     * Get password reset email template.
     */
    private function getPasswordResetEmailTemplate(string $name, string $resetUrl): string
    {
        return $this->wrap('استعادة كلمة المرور', "
            <p>أهلاً {$name}،</p>
            <p>وصلنا طلب لإعادة تعيين كلمة المرور. اضغط الزر:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$resetUrl}' style='background-color: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                    إعادة تعيين
                </a>
            </div>
            <p>أو انسخ الرابط:</p>
            <p style='word-break: break-all; color: #666;'>{$resetUrl}</p>
            <p>الرابط ينتهي خلال ساعة.</p>
            <p>لو أنت ما طلبتش الكده، تجاهل الإيميل.</p>
        ");
    }
}
