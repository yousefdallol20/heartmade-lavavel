<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class AdminSubmissionMail extends Mailable
{
    public function __construct(
        public string $submissionType,
        public array $submission
    ) {}

    public function envelope(): Envelope
    {
        $isOrder = $this->submissionType === 'order';

        $email = $isOrder
            ? ($this->submission['customer_email'] ?? null)
            : ($this->submission['email'] ?? null);

        $name = $isOrder
            ? ($this->submission['customer_name'] ?? '')
            : ($this->submission['contact'] ?? '');

        $replyTo = [];

        if (
            is_string($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $replyTo[] = new Address($email, $name);
        }

        return new Envelope(
            replyTo: $replyTo,

            subject: $isOrder
                ? 'Heart Made | New order #'
                .$this->submission['id']
                : 'Heart Made | Corporate inquiry #'
                .$this->submission['id']
        );
    }

    public function content(): Content
    {
        $invoiceUrl = null;

        if ($this->submissionType === 'order') {
            $invoiceUrl = URL::temporarySignedRoute(
                'orders.invoice.admin',
                now()->addDays(7),
                [
                    'order' => $this->submission['id'],
                ]
            );
        }

        return new Content(
            view: 'emails.admin-submission',

            with: [
                'submissionType' => $this->submissionType,

                'submission' => $this->submission,

                'adminUrl' => config(
                    'submissions.admin_url'
                ),

                'currency' => config(
                    'submissions.currency',
                    'JD'
                ),

                'invoiceUrl' => $invoiceUrl,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
