<?php

namespace Tests\Feature;

use App\Jobs\SendSubmissionEmail;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class StorefrontOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_place_order_and_stock_notification_and_job_are_created(): void
    {
        Bus::fake();

        $product = Product::create([
            'name' => 'Test Gift',
            'price' => '12.50',
            'stock' => 20,
            'category' => 'Gifts',
        ]);

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '0790000000',
            'customer_email' => 'customer@example.com',
            'shipping_address' => 'Test Street',
            'city' => 'amman',
            'payment_method' => 'Cash on Delivery',
            'expected_total' => '25.00',
            'items' => [[
                'product_id' => $product->id,
                'qty' => 2,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', '25.00')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', [
            'customer_email' => 'customer@example.com',
            'total' => '25.00',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'order',
        ]);
        $this->assertSame(18, $product->fresh()->stock);

        Bus::assertDispatched(SendSubmissionEmail::class);
    }

    public function test_order_is_rejected_when_requested_quantity_exceeds_stock(): void
    {
        Bus::fake();

        $product = Product::create([
            'name' => 'Limited Gift',
            'price' => '10.00',
            'stock' => 1,
            'category' => 'Gifts',
        ]);

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '0790000000',
            'customer_email' => 'customer@example.com',
            'shipping_address' => 'Test Street',
            'city' => 'amman',
            'payment_method' => 'Cash on Delivery',
            'expected_total' => '20.00',
            'items' => [[
                'product_id' => $product->id,
                'qty' => 2,
            ]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.qty']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock);
        Bus::assertNothingDispatched();
    }

    public function test_client_cannot_change_server_price(): void
    {
        Bus::fake();

        $product = Product::create([
            'name' => 'Secure Price Gift',
            'price' => '20.00',
            'stock' => 10,
            'category' => 'Gifts',
        ]);

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '0790000000',
            'customer_email' => 'customer@example.com',
            'shipping_address' => 'Test Street',
            'city' => 'amman',
            'payment_method' => 'Cash on Delivery',
            'expected_total' => '1.00',
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
            ]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expected_total']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }
}
