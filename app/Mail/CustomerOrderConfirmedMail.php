<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class CustomerOrderConfirmedMail extends Mailable
{
    public function __construct(
        public array $order
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Heart Made | Order #'
                .$this->order['id']
                .' confirmed'
        );
    }

    public function content(): Content
    {
        $invoiceUrl = URL::temporarySignedRoute(
            'orders.invoice.customer',
            now()->addDays(7),
            [
                'order' => $this->order['id'],
            ]
        );

        return new Content(
            view: 'emails.customer-order-confirmed',

            with: [
                'order' => $this->order,

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
