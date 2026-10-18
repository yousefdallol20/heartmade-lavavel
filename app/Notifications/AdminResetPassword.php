<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminResetPassword extends Notification
{
    use Queueable;

    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $frontendUrl = rtrim(
            config('app.frontend_url'),
            '/'
        );

        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $url = $frontendUrl
            .'/#/admin/reset-password?'
            .$query;

        $minutes = config(
            'auth.passwords.'
            .config('auth.defaults.passwords')
            .'.expire'
        );

        return (new MailMessage)
            ->subject('استعادة كلمة المرور — Heart Made')
            ->greeting('مرحبًا!')
            ->line('وصلنا طلب لاستعادة كلمة مرور حساب الأدمن.')
            ->action('تعيين كلمة مرور جديدة', $url)
            ->line("صلاحية هذا الرابط {$minutes} دقيقة.")
            ->line(
                'إذا لم تطلبي تغيير كلمة المرور، يمكنك تجاهل هذه الرسالة.'
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
