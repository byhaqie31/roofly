<?php

namespace Database\Seeders;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayoutAccount;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * A small, varied sample world for LOCAL development — not the demo persona
 * (that's DemoSeeder, mirrored by the frontend demo adapter). One of each
 * state the app has, with dates relative to today so it never goes stale:
 *
 * - Farah (farah@example.com) — seasoned landlord, 3 properties, 4 units, two
 *   payout accounts (one agreement overrides the default). Tenants: a good
 *   payer, an overdue one who gave notice, one with an "I've paid" claim
 *   waiting on her, an invited tenant reviewing an agreement, a moved-out one.
 * - Daniel (daniel@example.com) — rental + own-stay, NO payout account (the
 *   "landlord hasn't added payment details" path), an accepted agreement
 *   waiting for activation.
 * - Aisyah (aisyah@example.com) — brand new, not onboarded yet.
 *
 * Every password is `password`. Admins, leads and analytics are untouched.
 * Idempotent (deterministic ids), and refuses to run outside local/testing.
 *
 *   docker exec roofly-backend php artisan db:seed --class=LocalSampleSeeder
 */
class LocalSampleSeeder extends Seeder
{
    private CarbonImmutable $today;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('LocalSampleSeeder only runs in local/testing — never on UAT or production.');
        }

        $this->today = CarbonImmutable::now()->startOfDay();

        DB::transaction(function (): void {
            $this->seedFarah();
            $this->seedDaniel();
            $this->seedAisyah();
        });
    }

    // ── Farah: the full picture ──────────────────────────────────────────────

    private function seedFarah(): void
    {
        $farah = $this->owner('farah', 'Farah Hassan', 'farah@example.com', '+60 12-410 7788', [
            'business_name' => 'FH Homes',
            'plan_tier' => 'starter',
            'purposes' => ['rental'],
            'onboarded_at' => $this->today->subMonths(14),
            'checklist_dismissed_at' => $this->today->subMonths(13),
        ]);

        $maybank = $this->payout($farah, 'maybank', 'Personal Maybank', [
            'bank' => 'maybank', 'account_holder_name' => 'Farah binti Hassan',
            'account_number' => '164012558821', 'duitnow_id_type' => 'phone', 'duitnow_id' => '+60124107788',
            'is_default' => true,
        ]);
        $public = $this->payout($farah, 'public', 'FH Homes Public Bank', [
            'bank' => 'public_bank', 'account_holder_name' => 'FH Homes',
            'account_number' => '3201456789', 'duitnow_id_type' => 'brn', 'duitnow_id' => '202201034567',
            'is_default' => false,
        ]);

        $vista = $this->property($farah, 'vista', [
            'name' => 'Residensi Vista Mutiara B-15-07', 'type' => 'condo', 'purpose' => 'rental',
            'address' => 'Jalan Vista Mutiara, Kepong', 'city' => 'Kuala Lumpur', 'state' => 'W.P. Kuala Lumpur', 'postcode' => '52000',
            'year_built' => 2016, 'built_up_sqft' => 950, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_lots' => 1, 'furnishing' => 'partial',
            'ownership' => [
                'titleType' => 'leasehold', 'tenureExpiry' => '2113-06-30', 'strataTitle' => true,
                'purchaseDate' => '2019-05-20', 'purchasePrice' => 52_000_000, 'currentMarketValue' => 58_000_000,
                'mortgage' => ['bank' => 'Maybank', 'loanAmount' => 46_800_000, 'outstandingBalance' => 39_100_000, 'monthlyInstalment' => 198_000, 'tenureYears' => 35],
            ],
            'utilities' => ['monthlyMaintenanceFee' => 28_500, 'sinkingFund' => 2_850, 'tnbAccountNo' => '2201889934'],
        ], [['Farah Hassan', 100, true]]);
        $vistaUnit = $this->unit($vista, 'vista-whole', 'Whole unit', 3, 2, 950, 'occupied');

        $jelutong = $this->property($farah, 'jelutong', [
            'name' => 'Bukit Jelutong terrace', 'type' => 'landed', 'purpose' => 'rental',
            'address' => '21, Jalan Fiesta U8/12', 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40150',
            'year_built' => 2004, 'built_up_sqft' => 2100, 'land_sqft' => 1650, 'bedrooms' => 4, 'bathrooms' => 3, 'parking_lots' => 2, 'furnishing' => 'fully',
            'notes' => 'Rented by the room. Shared kitchen, cleaner every Saturday.',
            'ownership' => ['titleType' => 'freehold', 'strataTitle' => false, 'purchaseDate' => '2017-11-02', 'purchasePrice' => 68_000_000],
        ], [['Farah Hassan', 100, true]]);
        $masterRoom = $this->unit($jelutong, 'jelutong-master', 'Master room', 1, 1, 260, 'occupied');
        $middleRoom = $this->unit($jelutong, 'jelutong-middle', 'Middle room', 1, 0, 150, 'occupied');

        $gurney = $this->property($farah, 'gurney', [
            'name' => 'Gurney studio 22-11', 'type' => 'condo', 'purpose' => 'rental',
            'address' => 'Persiaran Gurney', 'city' => 'George Town', 'state' => 'Pulau Pinang', 'postcode' => '10250',
            'year_built' => 2020, 'built_up_sqft' => 520, 'bedrooms' => 1, 'bathrooms' => 1, 'furnishing' => 'fully',
            'ownership' => ['titleType' => 'freehold', 'strataTitle' => true, 'purchaseDate' => '2021-08-15', 'purchasePrice' => 61_000_000],
        ], [['Farah Hassan', 70, true], ['Hassan Ismail', 30, false]]);
        $studio = $this->unit($gurney, 'gurney-studio', 'Studio', 1, 1, 520, 'vacant');

        // Tenants
        $izzah = $this->tenant($farah, 'izzah', 'Nurul Izzah Kamal', 'izzah@example.com', '+60 13-220 4512', 'active', $this->today->subMonths(9), [
            'personal_info' => ['icNumber' => '950212-10-5562', 'occupation' => 'Pharmacist', 'employer' => 'Hospital Selayang', 'nationality' => 'Malaysian'],
            'emergency_contact' => ['name' => 'Kamal Ariffin', 'phone' => '+60 19-332 1100', 'relationship' => 'Father'],
        ]);
        $kumar = $this->tenant($farah, 'kumar', 'Kumar Selvam', 'kumar@example.com', '+60 16-778 9021', 'notice_given', $this->today->subMonths(6), [
            'personal_info' => ['occupation' => 'Technician', 'nationality' => 'Malaysian'],
        ]);
        $meiling = $this->tenant($farah, 'meiling', 'Tan Mei Ling', 'meiling@example.com', '+60 12-889 3301', 'active', $this->today->subMonths(4), [
            'personal_info' => ['icNumber' => '990805-07-6624', 'occupation' => 'UX designer', 'nationality' => 'Malaysian'],
            'emergency_contact' => ['name' => 'Tan Wei Jie', 'phone' => '+60 12-889 3302', 'relationship' => 'Brother'],
        ]);
        $hafiz = $this->tenant($farah, 'hafiz', 'Hafiz Rahman', 'hafiz@example.com', '+60 11-5566 7788', 'invited', $this->today->subDays(5));
        $jason = $this->tenant($farah, 'jason', 'Jason Wong', 'jason@example.com', '+60 17-301 4455', 'moved_out', $this->today->subMonths(30), [
            'personal_info' => ['occupation' => 'Accountant', 'nationality' => 'Malaysian'],
        ]);

        // Agreements + rent history
        $start = $this->today->startOfMonth()->subMonths(8);
        $izzahAgreement = $this->agreement('izzah', $vistaUnit, $izzah, $start, $start->addYear()->subDay(), 260_000, 520_000, 5_000, 1, 'active');
        $this->rentHistory($izzahAgreement, fn (array $inv) => $inv['status'] === 'overdue' ? 'paid' : $inv['status'], $maybank);

        $start = $this->today->startOfMonth()->subMonths(5);
        $kumarAgreement = $this->agreement('kumar', $masterRoom, $kumar, $start, $start->addYear()->subDay(), 90_000, 180_000, 3_000, 7, 'active', $public);
        $this->rentHistory($kumarAgreement, fn (array $inv) => $inv['status'], $public);

        $start = $this->today->startOfMonth()->subMonths(3);
        $meilingAgreement = $this->agreement('meiling', $middleRoom, $meiling, $start, $start->addYear()->subDay(), 75_000, 150_000, 2_500, 15, 'active');
        // Her latest past-due rent was transferred on the due date and claimed —
        // so it stays "pending" (the roll skips on-time claims) until Farah confirms.
        $claimed = $this->rentHistory($meilingAgreement, fn (array $inv) => $inv['status'] === 'overdue' ? 'pending' : $inv['status'], $maybank);
        $this->pendingClaim($claimed, $maybank, 'MB2U-' . $this->today->format('ymd') . '-0815', 'Transferred from Maybank2u, ref in the screenshot I sent on WhatsApp.');

        $this->agreement('hafiz', $studio, $hafiz, $this->today->startOfMonth()->addMonth(), $this->today->startOfMonth()->addMonths(13)->subDay(), 180_000, 360_000, 5_000, 1, 'pending_review', null, [
            'sent_at' => $this->today->subDays(2),
        ]);

        $start = $this->today->startOfMonth()->subMonths(30);
        $jasonAgreement = $this->agreement('jason', $vistaUnit, $jason, $start, $start->addYear()->subDay(), 240_000, 480_000, 5_000, 1, 'expired');
        $this->rentHistory($jasonAgreement, fn () => 'paid', null);

        // Tickets
        $this->ticket('vista-aircon', $vistaUnit, $izzah, 'tenant', 'appliance', 'high', 'Living room aircon leaking water',
            'Water drips from the indoor unit onto the sofa when it runs for more than an hour.', 'in_progress', $this->today->subDays(6), [
                [$farah, 'owner', 'Technician coming Thursday 10am to service and check the drain pipe.', $this->today->subDays(5)],
                [$izzah, 'tenant', 'Noted, I will be home. Thank you!', $this->today->subDays(5)->addHours(2)],
            ]);
        $this->ticket('vista-washer', $vistaUnit, $izzah, 'tenant', 'appliance', 'low', 'Washing machine door seal torn',
            'Small tear on the rubber seal, leaks a little on spin.', 'resolved', $this->today->subMonths(2), [], $this->today->subMonths(2)->addDays(4));
        $this->ticket('jelutong-ceiling', $middleRoom, $meiling, 'tenant', 'structural', 'urgent', 'Ceiling stain spreading after heavy rain',
            'Brown patch above the window is bigger after last night. Ceiling feels soft near the corner.', 'new', $this->today->subDay());
        $this->ticket('jelutong-bell', $masterRoom, $kumar, 'tenant', 'electrical', 'low', 'Doorbell not working',
            'Front gate doorbell stopped ringing. Probably the battery or the wiring.', 'new', $this->today->subDays(3));
    }

    // ── Daniel: mixed purposes, no payout account yet ────────────────────────

    private function seedDaniel(): void
    {
        $daniel = $this->owner('daniel', 'Daniel Lim', 'daniel@example.com', '+60 17-655 2290', [
            'plan_tier' => 'free',
            'purposes' => ['rental', 'own_stay'],
            'onboarded_at' => $this->today->subMonths(3),
        ]);

        $shoplot = $this->property($daniel, 'jalan-ipoh', [
            'name' => 'Jalan Ipoh shoplot', 'type' => 'shoplot', 'purpose' => 'rental',
            'address' => '118, Jalan Ipoh', 'city' => 'Kuala Lumpur', 'state' => 'W.P. Kuala Lumpur', 'postcode' => '51200',
            'year_built' => 1998, 'built_up_sqft' => 2400, 'parking_lots' => 0, 'furnishing' => 'unfurnished',
        ], [['Daniel Lim', 100, true]]);
        $office = $this->unit($shoplot, 'ipoh-first', 'First floor office', null, 1, 1200, 'occupied');
        $shop = $this->unit($shoplot, 'ipoh-ground', 'Ground floor shop', null, 1, 1200, 'vacant');

        $this->property($daniel, 'cheras-home', [
            'name' => 'Cheras family home', 'type' => 'landed', 'purpose' => 'own_stay',
            'address' => '7, Jalan Suarasa 8/2', 'city' => 'Cheras', 'state' => 'Selangor', 'postcode' => '43200',
            'bedrooms' => 4, 'bathrooms' => 3,
            'ownership' => ['titleType' => 'freehold', 'strataTitle' => false, 'purchaseDate' => '2015-02-10', 'purchasePrice' => 72_000_000, 'currentMarketValue' => 98_000_000],
        ], [['Daniel Lim', 50, true], ['Grace Lim', 50, false]]);

        $amir = $this->tenant($daniel, 'amir', 'Amir Faiz', 'amir@example.com', '+60 19-447 0012', 'active', $this->today->subMonths(3), [
            'personal_info' => ['occupation' => 'Tuition centre owner', 'nationality' => 'Malaysian'],
        ]);
        $priya = $this->tenant($daniel, 'priya', 'Priya Nair', 'priya@example.com', '+60 12-301 9987', 'active', $this->today->subDays(12), [
            'personal_info' => ['occupation' => 'Florist', 'nationality' => 'Malaysian'],
        ]);

        // No payout account → Amir can't claim; his October rent sits overdue.
        $start = $this->today->startOfMonth()->subMonths(2);
        $amirAgreement = $this->agreement('amir', $office, $amir, $start, $start->addYears(2)->subDay(), 220_000, 660_000, 10_000, 1, 'active');
        $this->rentHistory($amirAgreement, fn (array $inv) => $inv['status'], null, 'cash');

        // Agreed by Priya, waiting for Daniel to activate.
        $this->agreement('priya', $shop, $priya, $this->today->startOfMonth()->addMonth(), $this->today->startOfMonth()->addMonths(25)->subDay(), 280_000, 840_000, 10_000, 1, 'accepted', null, [
            'sent_at' => $this->today->subDays(8),
            'accepted_at' => $this->today->subDays(6),
        ]);

        $this->ticket('ipoh-lights', $office, $amir, 'tenant', 'electrical', 'medium', 'Staircase lights flickering',
            'Two of the staircase lights flicker at night. Students use the stairs until 10pm.', 'reopened', $this->today->subWeeks(3), [
                [$daniel, 'owner', 'Replaced both tubes on Saturday.', $this->today->subWeeks(2)],
                [$amir, 'tenant', 'Flickering again since Tuesday — maybe the starter?', $this->today->subDays(2)],
            ]);
    }

    // ── Aisyah: brand new owner, lands on onboarding ─────────────────────────

    private function seedAisyah(): void
    {
        $this->owner('aisyah', 'Siti Nur Aisyah', 'aisyah@example.com', '+60 11-2345 6677', [
            'plan_tier' => 'free',
            'purposes' => null,
            'onboarded_at' => null,
        ]);
    }

    // ── Builders ──────────────────────────────────────────────────────────────

    private function id(string $key): string
    {
        return (string) Uuid::uuid5('6f1c7a52-3c0e-4f7e-9d0a-5a4c1e2b7d10', "local-sample:{$key}");
    }

    private function owner(string $key, string $name, string $email, string $phone, array $attrs): User
    {
        return User::updateOrCreate(['id' => $this->id("owner:{$key}")], [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => 'owner',
            'password' => Hash::make('password'),
        ] + $attrs);
    }

    private function tenant(User $owner, string $key, string $name, string $email, string $phone, string $status, CarbonImmutable $invitedAt, array $attrs = []): User
    {
        $tenant = User::updateOrCreate(['id' => $this->id("tenant:{$key}")], [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => 'tenant',
            'status' => $status,
            'invited_by' => $owner->id,
            'invited_at' => $invitedAt,
            // Local convenience: a known password so every tenant can sign in.
            'password' => Hash::make('password'),
            // Only a pending invite meets the tenant onboarding screen.
            'onboarded_at' => $status === 'invited' ? null : $invitedAt->addDay(),
        ] + $attrs);
        $this->pinCreatedAt('users', $tenant->id, $invitedAt);

        return $tenant;
    }

    private function payout(User $owner, string $key, string $label, array $attrs): PayoutAccount
    {
        return PayoutAccount::updateOrCreate(['id' => $this->id("payout:{$key}")], ['owner_id' => $owner->id, 'label' => $label] + $attrs);
    }

    /** @param list<array{0: string, 1: int, 2: bool}> $coOwners name, share %, primary */
    private function property(User $owner, string $key, array $attrs, array $coOwners): Property
    {
        $property = Property::updateOrCreate(['id' => $this->id("property:{$key}")], ['owner_id' => $owner->id] + $attrs);

        foreach ($coOwners as $i => [$name, $share, $primary]) {
            PropertyCoOwner::updateOrCreate(['id' => $this->id("coowner:{$key}:{$i}")], [
                'property_id' => $property->id,
                'user_id' => $primary ? $owner->id : null,
                'name' => $name,
                'share_pct' => $share,
                'is_primary' => $primary,
            ]);
        }

        return $property;
    }

    private function unit(Property $property, string $key, string $label, ?int $bedrooms, ?int $bathrooms, ?int $sqft, string $status): Unit
    {
        return Unit::updateOrCreate(['id' => $this->id("unit:{$key}")], [
            'property_id' => $property->id,
            'label' => $label,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'sqft' => $sqft,
            'status' => $status,
        ]);
    }

    private function agreement(string $key, Unit $unit, User $tenant, CarbonImmutable $start, CarbonImmutable $end, int $rent, int $deposit, int $lateFee, int $dueDay, string $status, ?PayoutAccount $payout = null, array $extra = []): Agreement
    {
        $agreement = Agreement::updateOrCreate(['id' => $this->id("agreement:{$key}")], [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'rent_amount_cents' => $rent,
            'deposit_amount_cents' => $deposit,
            'late_fee_cents' => $lateFee,
            'rent_due_day' => $dueDay,
            'status' => $status,
            'payout_account_id' => $payout?->id,
        ] + $extra);
        $this->pinCreatedAt('agreements', $agreement->id, $start->subDays(10));

        return $agreement;
    }

    /**
     * One invoice per month from start to min(end, today + 30 days) — the same
     * horizon InvoiceGenerator keeps. Default status by age (paid if due over
     * 30 days ago, overdue if due in the last 30, else pending); `$statusFor`
     * can override per tenant. Paid invoices get a successful payment.
     *
     * @param callable(array{status: string, due: CarbonImmutable}): string $statusFor
     * @return Invoice|null the latest invoice that is due on or before today
     */
    private function rentHistory(Agreement $agreement, callable $statusFor, ?PayoutAccount $payout, string $method = 'transfer'): ?Invoice
    {
        $end = CarbonImmutable::parse($agreement->end_date);
        $horizon = $this->today->addDays(30);
        $cutoff = $end->lessThan($horizon) ? $end : $horizon;
        $numbers = app(InvoiceGenerator::class);
        $latestDue = null;

        $due = CarbonImmutable::parse($agreement->start_date)->setDay($agreement->rent_due_day);
        for ($i = 1; $due->lessThanOrEqualTo($cutoff); $i++, $due = $due->addMonthNoOverflow()) {
            $default = match (true) {
                $agreement->status->value === 'expired' => 'paid',
                $due->addDays(30)->lessThan($this->today) => 'paid',
                $due->lessThan($this->today) => 'overdue',
                default => 'pending',
            };
            $status = $statusFor(['status' => $default, 'due' => $due]);

            $id = $this->id("invoice:{$agreement->id}:{$i}");
            $invoice = Invoice::find($id);
            $invoice = Invoice::updateOrCreate(['id' => $id], [
                'agreement_id' => $agreement->id,
                'invoice_number' => $invoice?->invoice_number ?? $numbers->nextNumber(),
                'amount_cents' => $agreement->rent_amount_cents,
                'late_fee_cents' => $status === 'overdue' ? $agreement->late_fee_cents : 0,
                'due_date' => $due->toDateString(),
                'status' => $status,
            ]);
            $this->pinCreatedAt('invoices', $invoice->id, $due->subDays(7));

            if ($status === 'paid') {
                $paidAt = $due->subDays($i % 3);   // most pay a day or two early
                Payment::updateOrCreate(['id' => $this->id("payment:{$invoice->id}")], [
                    'invoice_id' => $invoice->id,
                    'amount_cents' => $invoice->totalDueCents(),
                    'method' => $method,
                    'status' => 'successful',
                    'reference' => $method === 'cash' ? null : 'DN' . $paidAt->format('ymd') . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'payout_account_id' => $method === 'transfer' ? $payout?->id : null,
                    'paid_at' => $paidAt,
                    'confirmed_at' => $method === 'transfer' ? $paidAt->addDay() : null,
                ]);
            }

            if ($due->lessThanOrEqualTo($this->today)) {
                $latestDue = $invoice;
            }
        }

        return $latestDue;
    }

    private function pendingClaim(?Invoice $invoice, PayoutAccount $payout, string $reference, string $note): void
    {
        if ($invoice === null || $invoice->status->value === 'paid') {
            return;
        }

        Payment::updateOrCreate(['id' => $this->id("claim:{$invoice->id}")], [
            'invoice_id' => $invoice->id,
            'amount_cents' => $invoice->totalDueCents(),
            'method' => 'transfer',
            'status' => 'pending',
            'reference' => $reference,
            'note' => $note,
            'payout_account_id' => $payout->id,
            'paid_at' => $invoice->due_date,
        ]);
    }

    /** @param list<array{0: User, 1: string, 2: string, 3: CarbonImmutable}> $comments author, role, body, at */
    private function ticket(string $key, Unit $unit, User $reporter, string $role, string $category, string $priority, string $title, string $description, string $status, CarbonImmutable $createdAt, array $comments = [], ?CarbonImmutable $resolvedAt = null): void
    {
        $ticket = Ticket::updateOrCreate(['id' => $this->id("ticket:{$key}")], [
            'unit_id' => $unit->id,
            'reporter_id' => $reporter->id,
            'reporter_role' => $role,
            'category' => $category,
            'priority' => $priority,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'resolved_at' => $resolvedAt,
        ]);
        $updatedAt = $resolvedAt ?? ($comments === [] ? $createdAt : $comments[array_key_last($comments)][3]);
        DB::table('tickets')->where('id', $ticket->id)->update(['created_at' => $createdAt, 'updated_at' => $updatedAt]);

        foreach ($comments as $i => [$author, $authorRole, $body, $at]) {
            $comment = TicketComment::updateOrCreate(['id' => $this->id("comment:{$key}:{$i}")], [
                'ticket_id' => $ticket->id,
                'author_id' => $author->id,
                'author_role' => $authorRole,
                'body' => $body,
            ]);
            $this->pinCreatedAt('ticket_comments', $comment->id, $at);
        }
    }

    private function pinCreatedAt(string $table, string $id, CarbonImmutable $at): void
    {
        DB::table($table)->where('id', $id)->update(['created_at' => $at, 'updated_at' => $at]);
    }
}
