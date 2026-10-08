<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $this->owner->id]);
        $this->unit = Unit::factory()->create(['property_id' => $property->id]);
        Sanctum::actingAs($this->owner);
    }

    public function test_expand_returns_ticket_envelopes_with_sorted_comments(): void
    {
        $ticket = Ticket::factory()->create(['unit_id' => $this->unit->id]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'created_at' => '2026-07-02']);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'created_at' => '2026-07-01']);

        $res = $this->getJson('/api/tickets?expand=unit,property,reporter,comments')->assertOk();
        $row = $res->json()[0];
        $this->assertSame(['ticket', 'unit', 'property', 'reporter', 'comments'], array_keys($row));
        $this->assertNotNull($row['reporter']); // factory reporterRole=tenant
        $this->assertTrue($row['comments'][0]['createdAt'] < $row['comments'][1]['createdAt']);
        $this->assertSame(['id', 'ticketId', 'authorId', 'authorRole', 'body', 'createdAt'], array_keys($row['comments'][0]));
    }

    public function test_owner_reported_ticket_has_null_reporter_in_envelope(): void
    {
        Ticket::factory()->create([
            'unit_id' => $this->unit->id,
            'reporter_id' => $this->owner->id, 'reporter_role' => 'owner',
        ]);
        $res = $this->getJson('/api/tickets?expand=unit,property,reporter,comments')->assertOk();
        $this->assertNull($res->json('0.reporter'));
    }

    public function test_store_accepts_camel_case_input(): void
    {
        $res = $this->postJson('/api/tickets', [
            'unitId' => $this->unit->id, 'category' => 'plumbing', 'priority' => 'high',
            'title' => 'Leaking sink', 'description' => 'Kitchen sink leaks.',
            'reporterId' => 'ignored', 'reporterRole' => 'owner',
        ])->assertCreated();
        $this->assertSame('owner', $res->json('reporterRole'));
        $this->assertSame($this->owner->id, $res->json('reporterId')); // server-derived
    }

    public function test_status_transition_validation(): void
    {
        $ticket = Ticket::factory()->create(['unit_id' => $this->unit->id, 'status' => 'new']);
        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'reopened'])->assertStatus(422);
        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk()->assertJsonPath('status', 'resolved');
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_owner_comment(): void
    {
        $ticket = Ticket::factory()->create(['unit_id' => $this->unit->id]);
        $res = $this->postJson("/api/tickets/{$ticket->id}/comments", ['body' => 'On it.'])->assertCreated();
        $this->assertSame('owner', $res->json('authorRole'));
    }
}
