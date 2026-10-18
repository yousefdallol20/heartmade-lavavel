<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmedEmail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvoiceAndOrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(string $status = 'pending'): Order
    {
        $product = Product::create([
            'name' => 'Invoice Gift',
            'price' => '15.00',
            'stock' => 50,
            'category' => 'Gifts',
        ]);

        $order = Order::create([
            'customer_name' => 'Invoice Customer',
            'customer_phone' => '0790000000',
            'customer_email' => 'invoice@example.com',
            'shipping_address' => 'Invoice Street, Amman',
            'payment_method' => 'Cash on Delivery',
            'payment_status' => 'unpaid',
            'status' => $status,
            'subtotal' => '30.00',
            'total' => '30.00',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => '15.00',
            'qty' => 2,
        ]);

        return $order->load('items');
    }

    public function test_unsigned_invoice_links_are_rejected(): void
    {
        Bus::fake();
        $order = $this->createOrder();

        $this->get("/api/orders/{$order->id}/invoice/customer")
            ->assertForbidden();
    }

    public function test_signed_customer_invoice_downloads_valid_pdf(): void
    {
        Bus::fake();
        $order = $this->createOrder('confirmed');
        $url = URL::temporarySignedRoute(
            'orders.invoice.customer',
            now()->addMinutes(10),
            ['order' => $order->id]
        );

        $response = $this->get($url);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString(
            "heart-made-invoice-{$order->id}.pdf",
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_signed_admin_invoice_downloads_valid_pdf(): void
    {
        Bus::fake();
        $order = $this->createOrder();
        $url = URL::temporarySignedRoute(
            'orders.invoice.admin',
            now()->addMinutes(10),
            ['order' => $order->id]
        );

        $response = $this->get($url);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_expired_invoice_link_is_rejected(): void
    {
        Bus::fake();
        $order = $this->createOrder();
        $url = URL::temporarySignedRoute(
            'orders.invoice.customer',
            now()->subMinute(),
            ['order' => $order->id]
        );

        $this->get($url)->assertForbidden();
    }

    public function test_confirming_order_queues_customer_email_only_once(): void
    {
        Bus::fake();
        $order = $this->createOrder();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin, 'sanctum');

        $payload = [
            'status' => 'confirmed',
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'qty' => $item->qty,
                'price' => $item->price,
            ])->all(),
        ];

        $this->patchJson("/api/admin/orders/{$order->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        Bus::assertDispatchedTimes(SendOrderConfirmedEmail::class, 1);
        $this->assertNotNull($order->fresh()->customer_confirmation_queued_at);

        $this->patchJson("/api/admin/orders/{$order->id}", $payload)
            ->assertOk();

        Bus::assertDispatchedTimes(SendOrderConfirmedEmail::class, 1);
    }
}
