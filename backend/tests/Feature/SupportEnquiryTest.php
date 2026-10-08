<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\AdminNewSupportEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportEnquiryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return ['type' => 'issue', 'message' => 'The payments page shows the wrong month.', 'pageUrl' => '/owner/payments', 'pageLabel' => 'Owner app · Payments'] + $over;
    }

    public function test_owner_sends_enquiry_with_identity_copied_and_super_admins_alerted(): void
    {
        Notification::fake();
        $super = User::factory()->superAdmin()->create();
        $owner = User::factory()->owner()->create(['name' => 'Aminah Yusof', 'email' => 'aminah@x.my']);
        Sanctum::actingAs($owner);

        $res = $this->postJson('/api/support/enquiries', $this->payload())->assertCreated();

        $e = Enquiry::findOrFail($res->json('id'));
        $this->assertSame([$owner->id, 'Aminah Yusof', 'aminah@x.my', 'owner', 'issue', 'new', '/owner/payments', 'Owner app · Payments'],
            [$e->user_id, $e->name, $e->email, $e->role, $e->type, $e->status, $e->page_url, $e->page_label]);
        Notification::assertSentTo($super, AdminNewSupportEnquiry::class, fn ($n) => $n->email === 'aminah@x.my');
    }

    public function test_tenant_and_suspended_owner_can_send(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->postJson('/api/support/enquiries', $this->payload(['type' => 'feedback']))->assertCreated();

        Sanctum::actingAs(User::factory()->owner()->create(['suspended_at' => now()]));
        $this->postJson('/api/support/enquiries', $this->payload(['type' => 'question']))->assertCreated();

        $this->assertSame(['tenant', 'owner'], Enquiry::orderBy('created_at')->pluck('role')->all());
    }

    public function test_admins_and_guests_cannot_send(): void
    {
        $this->postJson('/api/support/enquiries', $this->payload())->assertUnauthorized();

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->postJson('/api/support/enquiries', $this->payload())->assertForbidden();

        $this->assertSame(0, Enquiry::count());
    }

    public function test_validates_type_and_message(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson('/api/support/enquiries', ['type' => 'complaint', 'message' => 'hi'])
            ->assertUnprocessable()->assertJsonValidationErrors(['type', 'message']);
    }

    public function test_alert_email_replies_to_sender(): void
    {
        $e = Enquiry::factory()->create(['name' => 'Arif Hakim', 'email' => 'arif@x.my', 'type' => 'feedback', 'message' => 'Love the WhatsApp reminders!', 'page_url' => '/tenant', 'page_label' => 'Tenant app · Home']);
        $mail = AdminNewSupportEnquiry::fromEnquiry($e)->toMail(User::factory()->superAdmin()->make());

        $this->assertSame('Feedback from Arif Hakim: Love the WhatsApp reminders!', $mail->subject);
        $this->assertSame([['arif@x.my', 'Arif Hakim']], $mail->replyTo);
        $html = $mail->render();
        $this->assertStringContainsString('NEW FEEDBACK', $html);
        $this->assertStringContainsString('Tenant app · Home (/tenant)', $html);
    }
}
