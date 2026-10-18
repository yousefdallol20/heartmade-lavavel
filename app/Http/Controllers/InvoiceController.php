<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function downloadAdmin(
        Order $order
    ): Response {
        return $this->createPdf(
            $order,
            'pdf.order-invoice',
            'heart-made-admin-invoice-'
                .$order->id
                .'.pdf'
        );
    }

    public function downloadCustomer(
        Order $order
    ): Response {
        return $this->createPdf(
            $order,
            'pdf.customer-invoice',
            'heart-made-invoice-'
                .$order->id
                .'.pdf'
        );
    }

    private function createPdf(
        Order $order,
        string $view,
        string $filename
    ): Response {
        $order->load('items');

        $reference = 'ORDER-'.str_pad(
            (string) $order->id,
            6,
            '0',
            STR_PAD_LEFT
        );

        $html = view($view, [
            'order' => $order,
            'reference' => $reference,
            'currency' => config(
                'submissions.currency',
                'JD'
            ),
        ])->render();

        $options = new Options;

        $options->set(
            'defaultFont',
            'DejaVu Sans'
        );

        $options->set(
            'isRemoteEnabled',
            false
        );

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml(
            $html,
            'UTF-8'
        );

        $dompdf->setPaper(
            'A4',
            'portrait'
        );

        $dompdf->render();

        return response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',

                'Content-Disposition' => 'attachment; filename="'
                    .$filename
                    .'"',

                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }
}
