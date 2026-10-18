<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(
            except: ['api/paypal/webhook']
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // إرجاع أخطاء طلبات الواجهة بصيغة JSON.
        $exceptions->shouldRenderJsonWhen(
            function (
                \Illuminate\Http\Request $request,
                \Throwable $exception
            ): bool {
                return $request->is('api/*')
                    || $request->expectsJson();
            }
        );

        // إخفاء التفاصيل التقنية مع الحفاظ على رمز الخطأ وترويسات الاستجابة.
        $exceptions->respond(function (
            \Symfony\Component\HttpFoundation\Response $response,
            \Throwable $exception,
            \Illuminate\Http\Request $request
        ) {
            $isJsonRequest = $request->is('api/*')
                || $request->expectsJson();
            $status = $response->getStatusCode();
            if (! $isJsonRequest || $status < 400) {
                return $response;
            }
            $isPayPal = $request->is('api/paypal/*');

            $message = match ($status) {
                400 => 'The request could not be processed. Please check your information.',
                401 => $request->is('admin/login')
                    ? 'The email or password is incorrect.'
                    : 'Please sign in to continue.',
                403 => 'You do not have permission to perform this action.',
                404 => 'The requested item or page is no longer available.',
                405 => 'This action is unavailable. Please reload the page.',
                408 => 'The request took too long. Please check its status before trying again.',
                409, 410 => $isPayPal
                    ? 'Your checkout needs to be refreshed. Check your payment status before starting another payment.'
                    : 'This information has changed or expired. Please refresh the page.',
                413 => 'The uploaded files are too large. Please choose smaller files.',
                419 => 'Your session has expired. Please reload the page and try again.',
                422 => 'Please check the information you entered and try again.',
                429 => 'Too many attempts. Please wait a moment and try again.',
                default => $isPayPal
                    ? 'We could not confirm your payment status. Please check your order or contact us before trying another payment.'
                    : 'Something went wrong. Please try again later.',
            };
            $payload = [
                'success' => false,
                'message' => $message,
            ];
            // الاحتفاظ بأخطاء الحقول لعرضها بجانب المدخلات.
            if (
                $status === 422
                && $exception instanceof
                \Illuminate\Validation\ValidationException
            ) {
                $payload['errors'] = $exception->errors();
            }
            if (
                $response instanceof
                \Symfony\Component\HttpFoundation\JsonResponse
            ) {
                $response->setData($payload);
            } else {
                $response->setContent(
                    json_encode(
                        $payload,
                        JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                            | JSON_THROW_ON_ERROR
                    )
                );
            }
            $response->headers->set(
                'Content-Type',
                'application/json'
            );
            $response->headers->set(
                'Cache-Control',
                'no-store'
            );
            $response->headers->remove('Content-Length');
            return $response;
        });
    })->create();
