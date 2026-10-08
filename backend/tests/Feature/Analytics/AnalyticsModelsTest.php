<?php
namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_whitelist_and_factory_states(): void
    {
        $this->assertSame(['page_view', 'demo_enter', 'demo_feedback_click', 'waitlist_signup', 'register'], AnalyticsEvent::EVENTS);
        $e = AnalyticsEvent::factory()->pageView()->forVisitor('11111111-1111-4111-8111-111111111111')->at(now()->subDay())->create(['path' => '/demo']);
        $this->assertSame('page_view', $e->event);
        $this->assertSame('/demo', $e->path);
        $this->assertTrue($e->created_at->isYesterday());
        $this->assertNull($e->updated_at ?? null);
    }

    public function test_lead_converts_to_user(): void
    {
        $owner = User::factory()->owner()->create();
        $lead = Lead::factory()->converted($owner)->create(['email' => $owner->email]);
        $this->assertSame($owner->id, $lead->convertedUser->id);
        $this->assertContains($lead->source, Lead::SOURCES);
    }
}
