<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalGateway
{
    public function mode(): string
    {
        $mode = (string) config('paypal.mode');

        if (! in_array($mode, ['sandbox', 'live'], true)) {
            throw new RuntimeException(
                'Invalid PayPal environment.'
            );
        }

        return $mode;
    }

    public function ready(): bool
    {
        $mode = $this->mode();

        return trim((string) config("paypal.$mode.client_id")) !== ''
            && trim((string) config("paypal.$mode.client_secret")) !== '';
    }

    private function baseUrl(): string
    {
        return $this->mode() === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function accessToken(): string
    {
        if (! $this->ready()) {
            throw new RuntimeException(
                'PayPal credentials are missing.'
            );
        }

        $mode = $this->mode();

        $response = Http::asForm()
            ->connectTimeout(10)
            ->timeout(30)
            ->withBasicAuth(
                trim((string) config("paypal.$mode.client_id")),
                trim((string) config("paypal.$mode.client_secret"))
            )
            ->post(
                $this->baseUrl().'/v1/oauth2/token',
                ['grant_type' => 'client_credentials']
            );

        if (
            ! $response->successful()
            || ! is_string($response->json('access_token'))
        ) {
            throw new RuntimeException(
                'PayPal authentication failed (HTTP '
                .$response->status()
                .').'
            );
        }

        return $response->json('access_token');
    }

    public function request(
        string $method,
        string $path,
        array $body = [],
        ?string $requestId = null
    ): array {
        $request = Http::withToken($this->accessToken())
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);

        if ($requestId !== null) {
            $request = $request->withHeaders([
                'PayPal-Request-Id' => $requestId,
                'Prefer' => 'return=representation',
            ]);
        }

        // تثبيت رقم الطلب يمنع تكرار العملية عند إعادة المحاولة.
        $response = $method === 'GET'
            ? $request->get($this->baseUrl().$path)
            : $request
                ->withBody(
                    $body === []
                        ? '{}'
                        : json_encode($body, JSON_THROW_ON_ERROR),
                    'application/json'
                )
                ->send($method, $this->baseUrl().$path);

        if (
            ! $response->successful()
            || ! is_array($response->json())
        ) {
            $code = (string) $response->json(
                'name',
                'API_ERROR'
            );

            throw new RuntimeException(
                'PayPal '.$code
                .' (HTTP '.$response->status().').'
            );
        }

        return $response->json();
    }

    public function create(
        array $payload,
        string $requestId
    ): array {
        return $this->request(
            'POST',
            '/v2/checkout/orders',
            $payload,
            $requestId
        );
    }

    public function show(string $id): array
    {
        return $this->request(
            'GET',
            '/v2/checkout/orders/'.rawurlencode($id)
        );
    }

    public function capture(
        string $id,
        string $requestId
    ): array {
        return $this->request(
            'POST',
            '/v2/checkout/orders/'
                .rawurlencode($id)
                .'/capture',
            [],
            $requestId
        );
    }

    public function verifyWebhook(
        array $headers,
        array $event
    ): bool {
        $id = trim((string) config('checkout.webhook_id'));

        if ($id === '') {
            return false;
        }

        $payload = [
            'transmission_id' => $headers['paypal-transmission-id'] ?? '',

            'transmission_time' => $headers['paypal-transmission-time'] ?? '',

            'cert_url' => $headers['paypal-cert-url'] ?? '',

            'auth_algo' => $headers['paypal-auth-algo'] ?? '',

            'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',

            'webhook_id' => $id,

            'webhook_event' => $event,
        ];

        $result = $this->request(
            'POST',
            '/v1/notifications/verify-webhook-signature',
            $payload
        );

        return ($result['verification_status'] ?? '')
            === 'SUCCESS';
    }
}
