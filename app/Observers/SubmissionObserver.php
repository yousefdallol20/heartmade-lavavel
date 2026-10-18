<?php

namespace App\Observers;

use App\Jobs\SendSubmissionEmail;
use App\Models\AdminNotification;
use App\Models\Inquiry;
use App\Models\Order;

class SubmissionObserver
{
    public function created(Order|Inquiry $submission): void
    {
        $isOrder = $submission instanceof Order;

        // إشعار طلب باي بال يُنشأ بعد تأكيد التحصيل فقط.
        if ($isOrder && $submission->payment_method === 'PayPal') {
            return;
        }

        AdminNotification::create([
            'title' => $isOrder
                ? 'New order received'
                : 'New corporate inquiry received',

            'message' => $isOrder
                ? sprintf(
                    'Order #%d was received from %s.',
                    $submission->id,
                    $submission->customer_name
                )
                : sprintf(
                    '%s submitted a corporate inquiry. Contact: %s.',
                    $submission->company,
                    $submission->contact
                ),

            'type' => $isOrder ? 'order' : 'inquiry',

            'order_id' => $isOrder
                ? $submission->id
                : null,

            'inquiry_id' => $isOrder
                ? null
                : $submission->id,

            'read_at' => null,
        ]);

        SendSubmissionEmail::dispatch(
            $isOrder ? 'order' : 'inquiry',
            $submission->id
        )
            ->onConnection('database')
            ->onQueue('default')
            ->beforeCommit();
    }
}
