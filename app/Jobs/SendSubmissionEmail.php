<?php

namespace App\Jobs;

use App\Mail\AdminSubmissionMail;
use App\Models\AdminNotification;
use App\Models\Inquiry;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendSubmissionEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $submissionType,
        public int $submissionId
    ) {}

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(): void
    {
        $recipient = config('submissions.admin_email');

        if (
            ! is_string($recipient)
            || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                'ADMIN_NOTIFICATION_EMAIL must contain a valid email address.'
            );
        }

        if ($this->submissionType === 'order') {
            $submission = Order::with([
                'items',
                'checkoutPayment',
            ])->find(
                $this->submissionId
            );
        } elseif ($this->submissionType === 'inquiry') {
            $submission = Inquiry::find($this->submissionId);
        } else {
            throw new RuntimeException(
                'Unsupported submission type.'
            );
        }

        if (! $submission) {
            return;
        }

        if (
            $submission instanceof Order
            && $submission->payment_method === 'PayPal'
            && $submission->payment_status !== 'paid'
        ) {
            return;
        }

        Mail::to($recipient)->send(
            new AdminSubmissionMail(
                $this->submissionType,
                $submission->toArray()
            )
        );
    }

    public function failed(?Throwable $exception): void
    {
        $isOrder = $this->submissionType === 'order';

        $submissionExists = $isOrder
            ? Order::whereKey($this->submissionId)->exists()
            : Inquiry::whereKey($this->submissionId)->exists();

        if (! $submissionExists) {
            return;
        }

        AdminNotification::create([
            'title' => 'Email delivery failed',

            'message' => sprintf(
                'The notification email for %s #%d could not be sent after all attempts. The submission is still saved.',
                $this->submissionType,
                $this->submissionId
            ),

            'type' => 'email_failed',

            'order_id' => $isOrder
                ? $this->submissionId
                : null,

            'inquiry_id' => $isOrder
                ? null
                : $this->submissionId,

            'read_at' => null,
        ]);
    }
}
