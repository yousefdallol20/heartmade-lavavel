<?php

namespace Tests\Feature;

use App\Jobs\SendSubmissionEmail;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class InquiryAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_corporate_inquiry_creates_admin_notification_and_email_job(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/inquiries', [
            'company' => 'Test Company',
            'contact' => 'Test Person',
            'email' => 'company@example.com',
            'phone' => '0790000000',
            'quantity' => '20-50 items',
            'details' => 'Test corporate inquiry.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('inquiries', [
            'email' => 'company@example.com',
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'inquiry',
        ]);
        Bus::assertDispatched(SendSubmissionEmail::class);
    }

    public function test_guest_cannot_read_admin_notifications(): void
    {
        $this->getJson('/api/admin/notifications')->assertUnauthorized();
    }

    public function test_non_admin_cannot_read_admin_notifications(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/admin/notifications')->assertForbidden();
    }

    public function test_admin_can_read_and_mark_notifications_as_read(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin, 'sanctum');

        $notification = AdminNotification::create([
            'title' => 'Test notification',
            'message' => 'Test body',
            'type' => 'system_test',
        ]);

        $this->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->patchJson("/api/admin/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notifications_older_than_retention_period_are_hidden(): void
    {
        config()->set('notifications.retention_days', 7);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin, 'sanctum');

        AdminNotification::create([
            'title' => 'Expired notification',
            'message' => 'Should not be visible',
            'type' => 'system_test',
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        AdminNotification::create([
            'title' => 'Current notification',
            'message' => 'Should be visible',
            'type' => 'system_test',
        ]);

        $this->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Current notification')
            ->assertJsonPath('unread_count', 1);
    }
}
