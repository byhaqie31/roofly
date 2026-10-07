<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicesRollCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_roll_generates_for_active_agreements_and_marks_overdue(): void
    {
        $active = Agreement::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'rent_due_day' => 1, 'late_fee_cents' => 5000]);
        $draft  = Agreement::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'rent_due_day' => 1, 'status' => 'draft']);
        Invoice::factory()->create(['agreement_id' => $active->id, 'due_date' => '2026-10-01', 'status' => 'pending']);

        $this->artisan('invoices:roll', ['--date' => '2026-10-07'])->assertSuccessful();

        $dates = $active->invoices()->orderBy('due_date')->get()->map(fn (Invoice $i) => $i->due_date->toDateString())->all();
        $this->assertSame(['2026-10-01', '2026-11-01'], $dates);

        $october = $active->invoices()->whereDate('due_date', '2026-10-01')->first();
        $this->assertSame('overdue', $october->status->value);
        $this->assertSame(5000, $october->late_fee_cents);

        $this->assertCount(0, $draft->invoices);
    }

    public function test_roll_is_on_the_daily_schedule(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('invoices:roll');
    }
}
