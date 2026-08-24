<?php

namespace Database\Seeders;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Unit;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

/**
 * Ports frontend/app/mocks/*.ts into the database, record-for-record, so the
 * demo backend is content-identical to the frontend mock-first UI.
 *
 * Mock string ids ("p-1", "t-aminah", …) are not UUIDs — every mock id gets a
 * fixed deterministic UUID constant here so cross-references (property_id,
 * unit_id, tenant_id, agreement_id, …) stay stable across reseeds. Grouped by
 * entity: owner ...0001, tenants ...01xx, properties keep the mock's own
 * repeated-digit ids (11111111… … 55555555…) since those were already fixed
 * UUIDs in properties.ts, co-owners ...02xx, units ...03xx, agreements
 * ...04xx, tickets ...07xx, comments ...08xx. Invoices/payments are generated
 * dynamically (mirroring frontend/app/mocks/invoices.ts, which derives them
 * from `new Date()` at read time) so they use deterministic-but-unbounded
 * uuid5 ids instead of fixed constants.
 */
class DemoSeeder extends Seeder
{
    // ── Owner ───────────────────────────────────────────────────────────────
    private const OWNER_ID = '00000000-0000-4000-8000-000000000001';

    // ── Admins (spec § 9) ──────────────────────────────────────────────────
    private const ADMIN_SUPER = '00000000-0000-4000-8000-000000000901';
    private const ADMIN_OPS = '00000000-0000-4000-8000-000000000902';

    // ── Tenants (tenants.ts) ────────────────────────────────────────────────
    private const TENANT_AMINAH = '00000000-0000-4000-8000-000000000101';
    private const TENANT_ARIF = '00000000-0000-4000-8000-000000000102';
    private const TENANT_LI_WEI = '00000000-0000-4000-8000-000000000103';
    private const TENANT_RAVI = '00000000-0000-4000-8000-000000000104';
    private const TENANT_SITI = '00000000-0000-4000-8000-000000000105';

    // ── Properties (properties.ts) ──────────────────────────────────────────
    private const PROP_SURIA = '11111111-1111-1111-1111-111111111111';
    private const PROP_TTDI = '22222222-2222-2222-2222-222222222222';
    private const PROP_WANGSA = '33333333-3333-3333-3333-333333333333';
    private const PROP_USJ = '44444444-4444-4444-4444-444444444444';
    private const PROP_SUBANG = '55555555-5555-5555-5555-555555555555';

    // ── Property co-owners (properties.ts coOwners[]) ───────────────────────
    private const CO_SURIA_AHMAD = '00000000-0000-4000-8000-000000000201';
    private const CO_SURIA_FATIMAH = '00000000-0000-4000-8000-000000000202';
    private const CO_TTDI_PRIMARY = '00000000-0000-4000-8000-000000000203';
    private const CO_WANGSA_PRIMARY = '00000000-0000-4000-8000-000000000204';
    private const CO_USJ_PRIMARY = '00000000-0000-4000-8000-000000000205';
    private const CO_SUBANG_AHMAD = '00000000-0000-4000-8000-000000000206';
    private const CO_SUBANG_IMRAN = '00000000-0000-4000-8000-000000000207';

    // ── Units (units.ts) ─────────────────────────────────────────────────────
    private const UNIT_SURIA_1 = '00000000-0000-4000-8000-000000000301';
    private const UNIT_TTDI_1 = '00000000-0000-4000-8000-000000000302';
    private const UNIT_WANGSA_1 = '00000000-0000-4000-8000-000000000303';
    private const UNIT_WANGSA_2 = '00000000-0000-4000-8000-000000000304';
    private const UNIT_USJ_1 = '00000000-0000-4000-8000-000000000305';
    private const UNIT_SUBANG_MASTER = '00000000-0000-4000-8000-000000000306';
    private const UNIT_SUBANG_ROOM2 = '00000000-0000-4000-8000-000000000307';
    private const UNIT_SUBANG_ROOM3 = '00000000-0000-4000-8000-000000000308';

    // ── Agreements (agreements.ts) ───────────────────────────────────────────
    private const AGR_SURIA_AMINAH = '00000000-0000-4000-8000-000000000401';
    private const AGR_WANGSA_ARIF = '00000000-0000-4000-8000-000000000402';
    private const AGR_TTDI_LIWEI = '00000000-0000-4000-8000-000000000403';
    private const AGR_USJ_RAVI = '00000000-0000-4000-8000-000000000404';

    // ── Tickets (tickets.ts) ─────────────────────────────────────────────────
    private const TK_POWER_TRIP = '00000000-0000-4000-8000-000000000701';
    private const TK_REPAINT = '00000000-0000-4000-8000-000000000702';
    private const TK_BATH_DRAIN = '00000000-0000-4000-8000-000000000703';
    private const TK_TERMITES = '00000000-0000-4000-8000-000000000704';
    private const TK_TAP_DRIP = '00000000-0000-4000-8000-000000000705';
    private const TK_SHOP_LOCK = '00000000-0000-4000-8000-000000000706';
    private const TK_WATER_HEATER = '00000000-0000-4000-8000-000000000707';

    // ── Ticket comments (tickets.ts ticketCommentsMock) ─────────────────────
    private const TC_BATH_1 = '00000000-0000-4000-8000-000000000801';
    private const TC_BATH_2 = '00000000-0000-4000-8000-000000000802';
    private const TC_BATH_3 = '00000000-0000-4000-8000-000000000803';
    private const TC_TERM_1 = '00000000-0000-4000-8000-000000000804';
    private const TC_TERM_2 = '00000000-0000-4000-8000-000000000805';
    private const TC_HEAT_1 = '00000000-0000-4000-8000-000000000806';
    private const TC_HEAT_2 = '00000000-0000-4000-8000-000000000807';

    /** Namespace for deterministic uuid5 ids (generated invoices/payments). */
    private const UUID_NAMESPACE = '00000000-0000-4000-8000-000000000000';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(AdminPermissionSeeder::class);
            $this->seedAdmins();
            $this->seedOwner();
            $tenants = $this->seedTenants();
            $this->seedProperties();
            $this->seedUnits();
            $agreements = $this->seedAgreements($tenants);
            $this->seedInvoicesAndPayments($agreements);
            $this->seedTicketsAndComments();
            $this->call(AnalyticsDemoSeeder::class);
        });
    }

    // ── Owner (owner.ts ownerAccountMock) ────────────────────────────────────

    private function seedOwner(): void
    {
        User::updateOrCreate(['id' => self::OWNER_ID], [
            'name' => 'Cik Aminah',
            'email' => 'aminah@roofly.my',
            'phone' => '+60 12-345 6789',
            'role' => 'owner',
            'password' => Hash::make('password'),
            'business_name' => 'Aminah Properties',
            'bank_account_last4' => '4521',
            'plan_tier' => 'free',
            'owner_preferences' => [
                'locale' => 'en',
                'theme' => 'system',
                'moneyLocale' => 'en-MY',
            ],
            'notification_preferences' => [
                'events' => [
                    'rent_reminder' => true,
                    'agreement_expiry' => true,
                    'payment_received' => true,
                    'ticket_update' => true,
                    'invite_accepted' => true,
                ],
                'channels' => [
                    'email' => true,
                    'whatsapp' => false,
                    'in_app' => true,
                ],
            ],
            // Mirrors the demo persona (frontend/app/demo/auth.ts): a seasoned
            // owner, already onboarded, checklist dismissed over five
            // already-populated properties. Without this, a fresh
            // migrate:fresh --seed leaves onboarded_at NULL (the Task 1
            // migration only back-fills rows that exist when it runs) and the
            // API-mode owner gets ambushed by onboarding, disagreeing with the
            // demo adapter for the same persona.
            'purposes' => ['rental'],
            'onboarded_at' => now(),
            'checklist_dismissed_at' => now(),
        ]);
    }

    // ── Admins ──────────────────────────────────────────────────────────────

    private function seedAdmins(): void
    {
        $super = User::updateOrCreate(['id' => self::ADMIN_SUPER], [
            'name' => 'Baihaqie (super-admin)',
            'email' => 'admin@roofly.my',
            'phone' => null,
            'role' => 'admin',
            'is_super_admin' => true,
            'password' => Hash::make('password'),
            'first_login_at' => Carbon::parse('2026-08-01T01:00:00Z'),
        ]);
        $super->syncPermissions([]); // super-admin bypasses checks; no rows needed

        $ops = User::updateOrCreate(['id' => self::ADMIN_OPS], [
            'name' => 'Ops Admin',
            'email' => 'ops@roofly.my',
            'phone' => null,
            'role' => 'admin',
            'is_super_admin' => false,
            'password' => Hash::make('password'),
            'first_login_at' => Carbon::parse('2026-08-02T01:00:00Z'),
        ]);
        $ops->syncPermissions(AdminPermissions::operationsPreset());
    }

    // ── Tenants (tenants.ts tenantsMock) ─────────────────────────────────────

    /** @return array<string, string> mock id => uuid */
    private function seedTenants(): array
    {
        $rows = [
            self::TENANT_AMINAH => [
                'name' => 'Aminah Binti Yusof',
                'email' => 'aminah.yusof@example.com',
                'phone' => '+60 12-345 6789',
                'status' => 'active',
                'invited_at' => '2025-08-15T10:00:00Z',
                'created_at' => '2025-08-15T10:00:00Z',
                'personal_info' => [
                    'icNumber' => '880314-14-5687',
                    'dateOfBirth' => '1988-03-14',
                    'occupation' => 'Marketing manager',
                    'employer' => 'Petronas',
                    'monthlyIncome' => 1_200_000,
                    'nationality' => 'Malaysian',
                ],
                'emergency_contact' => [
                    'name' => 'Yusof Bin Hamid',
                    'phone' => '+60 19-555 0011',
                    'relationship' => 'Father',
                ],
            ],
            self::TENANT_ARIF => [
                'name' => 'Arif Hakim',
                'email' => 'arif.hakim@example.com',
                'phone' => '+60 17-888 1234',
                'status' => 'active',
                'invited_at' => '2025-11-02T09:00:00Z',
                'created_at' => '2025-11-02T09:00:00Z',
                'personal_info' => [
                    'icNumber' => '920701-08-1234',
                    'dateOfBirth' => '1992-07-01',
                    'occupation' => 'Café owner',
                    'nationality' => 'Malaysian',
                ],
                'emergency_contact' => null,
            ],
            self::TENANT_LI_WEI => [
                'name' => 'Lim Li Wei',
                'email' => 'limlw@example.com',
                'phone' => '+60 16-222 3344',
                'status' => 'invited',
                'invited_at' => '2026-04-30T14:30:00Z',
                'created_at' => '2026-04-30T14:30:00Z',
                'personal_info' => null,
                'emergency_contact' => null,
            ],
            self::TENANT_RAVI => [
                'name' => 'Ravi Kumar',
                'email' => 'ravik@example.com',
                'phone' => '+60 13-456 7890',
                'status' => 'moved_out',
                'invited_at' => '2024-03-10T08:00:00Z',
                'created_at' => '2024-03-10T08:00:00Z',
                'personal_info' => [
                    'occupation' => 'Software engineer',
                    'nationality' => 'Indian',
                ],
                'emergency_contact' => [
                    'name' => 'Priya Kumar',
                    'phone' => '+60 13-100 2222',
                    'relationship' => 'Spouse',
                ],
            ],
            self::TENANT_SITI => [
                'name' => 'Siti Khadijah Binti Rahim',
                'email' => 'siti.khadijah@example.com',
                'phone' => '+60 11-2233 4455',
                'status' => 'notice_given',
                'invited_at' => '2025-02-05T09:00:00Z',
                'created_at' => '2025-02-05T09:00:00Z',
                'personal_info' => [
                    'icNumber' => '910420-10-3344',
                    'dateOfBirth' => '1991-04-20',
                    'occupation' => 'Teacher',
                    'employer' => 'SMK Bukit Bintang',
                    'monthlyIncome' => 600_000,
                    'nationality' => 'Malaysian',
                ],
                'emergency_contact' => [
                    'name' => 'Rahim Bin Hassan',
                    'phone' => '+60 12-987 6543',
                    'relationship' => 'Father',
                ],
            ],
        ];

        foreach ($rows as $id => $attrs) {
            $createdAt = $attrs['created_at'];
            unset($attrs['created_at']);

            User::updateOrCreate(['id' => $id], array_merge($attrs, [
                'role' => 'tenant',
                'invited_by' => self::OWNER_ID,
                // Demo seed only: real tenants enter via invite / magic link and
                // have no password. A known password lets local + UAT testers
                // log in as a tenant through the normal form.
                'password' => Hash::make('password'),
            ]));

            $this->pinCreatedAt('users', $id, $createdAt);
        }

        return [
            't-aminah' => self::TENANT_AMINAH,
            't-arif' => self::TENANT_ARIF,
            't-li-wei' => self::TENANT_LI_WEI,
            't-ravi' => self::TENANT_RAVI,
            't-siti' => self::TENANT_SITI,
        ];
    }

    // ── Properties (properties.ts propertiesMock) ───────────────────────────

    private function seedProperties(): void
    {
        Property::updateOrCreate(['id' => self::PROP_SURIA], [
            'owner_id' => self::OWNER_ID,
            'name' => 'Suria KLCC #12-3A',
            'internal_label' => 'KLCC-A',
            'type' => 'condo',
            'purpose' => 'rental',
            'notes' => 'Master bedroom AC serviced 2025-11.',
            'address' => 'Jalan Ampang, Lot 241',
            'city' => 'Kuala Lumpur',
            'state' => 'W.P. Kuala Lumpur',
            'postcode' => '50088',
            'year_built' => 2008,
            'built_up_sqft' => 1100,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'parking_lots' => 1,
            'furnishing' => 'fully',
            'ownership' => [
                'titleType' => 'freehold',
                'titleNumber' => 'PN 12345',
                'lotNumber' => 'Lot 241',
                'strataTitle' => true,
                'masterTitle' => false,
                'purchaseDate' => '2018-03-14',
                'purchasePrice' => 85_000_000,
                'stampDuty' => 2_100_000,
                'legalFees' => 850_000,
                'currentMarketValue' => 115_000_000,
                'lastValuedAt' => '2026-01-15',
                'valuationSource' => 'bank',
                'mortgage' => [
                    'bank' => 'Maybank',
                    'loanAmount' => 68_000_000,
                    'outstandingBalance' => 41_250_000,
                    'monthlyInstalment' => 215_000,
                    'tenureYears' => 30,
                    'maturityDate' => '2048-03-31',
                    'interestRatePct' => 4.25,
                ],
            ],
            'utilities' => [
                'monthlyMaintenanceFee' => 32_000,
                'sinkingFund' => 5_000,
                'quitRentAnnual' => 9_500,
                'assessmentRateAnnual' => 48_000,
                'buildingInsuranceAnnual' => 72_000,
                'tnbAccountNo' => '9876543210',
                'waterAccountNo' => '1234567890',
                'indahWaterAccountNo' => 'IWK-789',
                'internetAccountNo' => 'TM-ABC123',
                'managementCorpName' => 'Suria KLCC Management',
                'managementCorpPhone' => '03-2382 2828',
            ],
        ]);
        $this->pinCreatedAt('properties', self::PROP_SURIA, '2026-01-12T09:00:00Z');

        Property::updateOrCreate(['id' => self::PROP_TTDI], [
            'owner_id' => self::OWNER_ID,
            'name' => 'TTDI Terrace',
            'type' => 'landed',
            'purpose' => 'rental',
            'address' => '12, Jalan Burhanuddin Helmi 2',
            'city' => 'Kuala Lumpur',
            'state' => 'W.P. Kuala Lumpur',
            'postcode' => '60000',
            'year_built' => 1995,
            'built_up_sqft' => 2400,
            'land_sqft' => 1800,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'parking_lots' => 2,
            'furnishing' => 'partial',
            'ownership' => [
                'titleType' => 'leasehold',
                'tenureExpiry' => '2090-12-31',
                'strataTitle' => false,
                'purchaseDate' => '2018-06-14',
                'purchasePrice' => 125_000_000,
                'stampDuty' => 3_750_000,
                'legalFees' => 1_200_000,
                'currentMarketValue' => 145_000_000,
                'lastValuedAt' => '2025-12-01',
                'valuationSource' => 'agent',
            ],
            'utilities' => [
                'quitRentAnnual' => 12_000,
                'tnbAccountNo' => '5544332211',
            ],
        ]);
        $this->pinCreatedAt('properties', self::PROP_TTDI, '2026-02-03T10:30:00Z');

        Property::updateOrCreate(['id' => self::PROP_WANGSA], [
            'owner_id' => self::OWNER_ID,
            'name' => 'Wangsa Walk Shoplot G-12',
            'type' => 'shoplot',
            'purpose' => 'rental',
            'address' => 'Lot 12, Jalan Wangsa Delima',
            'city' => 'Kuala Lumpur',
            'state' => 'W.P. Kuala Lumpur',
            'postcode' => '53300',
            'year_built' => 2005,
            'built_up_sqft' => 1200,
            'land_sqft' => 600,
            'parking_lots' => 1,
            'furnishing' => 'unfurnished',
            'ownership' => [
                'titleType' => 'freehold',
                'strataTitle' => false,
            ],
            'utilities' => [
                'assessmentRateAnnual' => 360_000,
            ],
        ]);
        $this->pinCreatedAt('properties', self::PROP_WANGSA, '2026-03-18T14:00:00Z');

        Property::updateOrCreate(['id' => self::PROP_USJ], [
            'owner_id' => self::OWNER_ID,
            'name' => 'USJ 9 Spare Room',
            'type' => 'room',
            'purpose' => 'rental',
            'address' => '32, Jalan USJ 9/2',
            'city' => 'Subang Jaya',
            'state' => 'Selangor',
            'postcode' => '47620',
        ]);
        $this->pinCreatedAt('properties', self::PROP_USJ, '2026-04-22T08:15:00Z');

        Property::updateOrCreate(['id' => self::PROP_SUBANG], [
            'owner_id' => self::OWNER_ID,
            'name' => 'Subang Terrace (multi-unit)',
            'internal_label' => 'USJ-MULTI',
            'type' => 'landed',
            'purpose' => 'rental',
            'notes' => 'Three rentable units under one terrace. Master + 2 rooms.',
            'address' => '8, Jalan USJ 18/3',
            'city' => 'Subang Jaya',
            'state' => 'Selangor',
            'postcode' => '47630',
            'year_built' => 2002,
            'built_up_sqft' => 2200,
            'land_sqft' => 1600,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'parking_lots' => 2,
            'furnishing' => 'partial',
            'ownership' => [
                'titleType' => 'freehold',
                'strataTitle' => false,
                'purchaseDate' => '2020-09-01',
                'purchasePrice' => 95_000_000,
                'stampDuty' => 2_400_000,
                'legalFees' => 950_000,
                'currentMarketValue' => 110_000_000,
                'lastValuedAt' => '2026-02-20',
                'valuationSource' => 'agent',
            ],
            'utilities' => [
                'quitRentAnnual' => 8_400,
                'assessmentRateAnnual' => 36_000,
                'tnbAccountNo' => '1122334455',
                'waterAccountNo' => '9988776655',
            ],
        ]);
        $this->pinCreatedAt('properties', self::PROP_SUBANG, '2026-04-28T11:00:00Z');

        // ── Co-owners (coOwners[] per property; off-platform individuals —
        // user_id null, per the migration's "allows off-platform co-owners" note).
        $coOwners = [
            [self::CO_SURIA_AHMAD, self::PROP_SURIA, 'Ahmad Baihaqie', 50, true],
            [self::CO_SURIA_FATIMAH, self::PROP_SURIA, 'Fatimah Yusof', 50, false],
            [self::CO_TTDI_PRIMARY, self::PROP_TTDI, 'Ahmad Baihaqie', 100, true],
            [self::CO_WANGSA_PRIMARY, self::PROP_WANGSA, 'Ahmad Baihaqie', 100, true],
            [self::CO_USJ_PRIMARY, self::PROP_USJ, 'Ahmad Baihaqie', 100, true],
            [self::CO_SUBANG_AHMAD, self::PROP_SUBANG, 'Ahmad Baihaqie', 60, true],
            [self::CO_SUBANG_IMRAN, self::PROP_SUBANG, 'Imran Baihaqie', 40, false],
        ];

        foreach ($coOwners as [$id, $propertyId, $name, $sharePct, $isPrimary]) {
            PropertyCoOwner::updateOrCreate(['id' => $id], [
                'property_id' => $propertyId,
                'user_id' => null,
                'name' => $name,
                'share_pct' => $sharePct,
                'is_primary' => $isPrimary,
            ]);
        }
    }

    // ── Units (units.ts unitsMock) ───────────────────────────────────────────

    private function seedUnits(): void
    {
        $units = [
            [self::UNIT_SURIA_1, self::PROP_SURIA, 'Whole unit', 3, 2, 1100, 'occupied', '2026-01-12T09:30:00Z'],
            [self::UNIT_TTDI_1, self::PROP_TTDI, 'Whole house', 4, 3, 2400, 'vacant', '2026-02-03T11:00:00Z'],
            [self::UNIT_WANGSA_1, self::PROP_WANGSA, 'Ground floor shop', null, null, 600, 'occupied', '2026-03-18T14:30:00Z'],
            [self::UNIT_WANGSA_2, self::PROP_WANGSA, 'Upper level office', null, null, 600, 'vacant', '2026-03-18T14:35:00Z'],
            [self::UNIT_USJ_1, self::PROP_USJ, 'Spare bedroom', 1, 0, null, 'maintenance', '2026-04-22T08:30:00Z'],
            [self::UNIT_SUBANG_MASTER, self::PROP_SUBANG, 'Master bedroom (en-suite)', 1, 1, 240, 'occupied', '2026-04-28T11:30:00Z'],
            [self::UNIT_SUBANG_ROOM2, self::PROP_SUBANG, 'Middle room', 1, 0, 140, 'occupied', '2026-04-28T11:35:00Z'],
            [self::UNIT_SUBANG_ROOM3, self::PROP_SUBANG, 'Back room', 1, 0, 130, 'vacant', '2026-04-28T11:40:00Z'],
        ];

        foreach ($units as [$id, $propertyId, $label, $bedrooms, $bathrooms, $sqft, $status, $createdAt]) {
            Unit::updateOrCreate(['id' => $id], [
                'property_id' => $propertyId,
                'label' => $label,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'sqft' => $sqft,
                'status' => $status,
            ]);
            $this->pinCreatedAt('units', $id, $createdAt);
        }
    }

    // ── Agreements (agreements.ts agreementsMock) ────────────────────────────

    /**
     * @param array<string, string> $tenants mock tenant id => uuid
     * @return array<string, array{id: string, unitId: string, tenantId: string, startDate: string, endDate: string, rentAmount: int, lateFee: int, rentDueDay: int, status: string}>
     */
    private function seedAgreements(array $tenants): array
    {
        $rows = [
            'a-suria-aminah' => [
                'id' => self::AGR_SURIA_AMINAH,
                'unitId' => self::UNIT_SURIA_1,
                'tenantId' => $tenants['t-aminah'],
                'startDate' => '2025-09-01',
                'endDate' => '2026-08-31',
                'rentAmount' => 350_000,
                'depositAmount' => 700_000,
                'lateFee' => 5_000,
                'rentDueDay' => 1,
                'status' => 'active',
                'createdAt' => '2025-08-25T10:00:00Z',
            ],
            'a-wangsa-arif' => [
                'id' => self::AGR_WANGSA_ARIF,
                'unitId' => self::UNIT_WANGSA_1,
                'tenantId' => $tenants['t-arif'],
                'startDate' => '2025-12-01',
                'endDate' => '2026-11-30',
                'rentAmount' => 400_000,
                'depositAmount' => 1_200_000,
                'lateFee' => 10_000,
                'rentDueDay' => 5,
                'status' => 'active',
                'createdAt' => '2025-11-20T09:30:00Z',
            ],
            'a-ttdi-liwei' => [
                'id' => self::AGR_TTDI_LIWEI,
                'unitId' => self::UNIT_TTDI_1,
                'tenantId' => $tenants['t-li-wei'],
                'startDate' => '2026-06-01',
                'endDate' => '2027-05-31',
                'rentAmount' => 320_000,
                'depositAmount' => 640_000,
                'lateFee' => 5_000,
                'rentDueDay' => 1,
                'status' => 'draft',
                'createdAt' => '2026-04-30T15:00:00Z',
            ],
            'a-usj-ravi' => [
                'id' => self::AGR_USJ_RAVI,
                'unitId' => self::UNIT_USJ_1,
                'tenantId' => $tenants['t-ravi'],
                'startDate' => '2024-04-01',
                'endDate' => '2025-03-31',
                'rentAmount' => 80_000,
                'depositAmount' => 160_000,
                'lateFee' => 2_000,
                'rentDueDay' => 1,
                'status' => 'expired',
                'createdAt' => '2024-03-25T11:00:00Z',
            ],
        ];

        foreach ($rows as $mockId => $a) {
            Agreement::updateOrCreate(['id' => $a['id']], [
                'unit_id' => $a['unitId'],
                'tenant_id' => $a['tenantId'],
                'start_date' => $a['startDate'],
                'end_date' => $a['endDate'],
                'rent_amount_cents' => $a['rentAmount'],
                'deposit_amount_cents' => $a['depositAmount'],
                'late_fee_cents' => $a['lateFee'],
                'rent_due_day' => $a['rentDueDay'],
                'status' => $a['status'],
            ]);
            $this->pinCreatedAt('agreements', $a['id'], $a['createdAt']);
        }

        return $rows;
    }

    // ── Invoices + payments (invoices.ts: generated from agreements + "today") ──

    /**
     * Ports the generation algorithm in frontend/app/mocks/invoices.ts verbatim:
     * one invoice per rent_due_day from an agreement's start until 30 days past
     * "today" (capped at the agreement end date), skipping draft agreements,
     * and deriving each invoice's status/late fee/payment from how far past due
     * it is relative to "today" — so re-running this on a later date naturally
     * produces a different (larger) invoice set, exactly like the frontend mock
     * recomputing against `new Date()` on every page load.
     *
     * @param array<string, array{id: string, unitId: string, tenantId: string, startDate: string, endDate: string, rentAmount: int, lateFee: int, rentDueDay: int, status: string}> $agreements
     */
    private function seedInvoicesAndPayments(array $agreements): void
    {
        $today = now()->startOfDay();
        $allInvoices = [];

        foreach ($agreements as $mockId => $a) {
            if ($a['status'] === 'draft') {
                continue;
            }

            $start = Carbon::parse($a['startDate']);
            $end = Carbon::parse($a['endDate']);
            $cutoff = $end->lessThan($today->copy()->addDays(30)) ? $end : $today->copy()->addDays(30);

            $year = (int) $start->format('Y');
            $month = (int) $start->format('n'); // 1-12
            $counter = 0;

            while (true) {
                $dueDate = Carbon::createFromDate($year, $month, $a['rentDueDay'])->startOfDay();
                if ($dueDate->greaterThan($cutoff)) {
                    break;
                }

                $counter++;
                $invoiceId = (string) Uuid::uuid5(self::UUID_NAMESPACE, "inv-{$mockId}-{$counter}");

                if ($a['status'] === 'expired' || $a['status'] === 'terminated') {
                    $status = 'paid';
                } elseif ($dueDate->copy()->addDays(30)->lessThan($today)) {
                    $status = 'paid';
                } elseif ($dueDate->lessThan($today)) {
                    $status = 'overdue';
                } else {
                    $status = 'pending';
                }
                $lateFee = $status === 'overdue' ? $a['lateFee'] : 0;

                $allInvoices[] = [
                    'id' => $invoiceId,
                    'agreementId' => $a['id'],
                    'amount' => $a['rentAmount'],
                    'lateFee' => $lateFee,
                    'dueDate' => $dueDate->toDateString(),
                    'status' => $status,
                    'createdAt' => $dueDate->toIso8601String(),
                    'mockId' => $mockId,
                    'counter' => $counter,
                ];

                $month++;
                if ($month > 12) {
                    $month = 1;
                    $year++;
                }
            }
        }

        // Sequence invoice numbers chronologically across the whole portfolio,
        // matching invoices.ts's global INV-0001… numbering.
        usort($allInvoices, fn (array $x, array $y) => $x['dueDate'] <=> $y['dueDate']);

        foreach ($allInvoices as $idx => $inv) {
            $invoiceNumber = 'INV-' . str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);

            Invoice::updateOrCreate(['id' => $inv['id']], [
                'agreement_id' => $inv['agreementId'],
                'invoice_number' => $invoiceNumber,
                'amount_cents' => $inv['amount'],
                'late_fee_cents' => $inv['lateFee'],
                'due_date' => $inv['dueDate'],
                'status' => $inv['status'],
            ]);
            $this->pinCreatedAt('invoices', $inv['id'], $inv['createdAt']);

            if ($inv['status'] === 'paid') {
                $paidAt = Carbon::parse($inv['dueDate'])->addDays(2);
                $paymentId = (string) Uuid::uuid5(self::UUID_NAMESPACE, "pay-inv-{$inv['mockId']}-{$inv['counter']}");

                Payment::updateOrCreate(['id' => $paymentId], [
                    'invoice_id' => $inv['id'],
                    'amount_cents' => $inv['amount'],
                    'method' => 'fpx',
                    'status' => 'successful',
                    'reference' => 'FPX-' . str_replace('-', '', $inv['dueDate']),
                    'paid_at' => $paidAt,
                ]);
                $this->pinCreatedAt('payments', $paymentId, $paidAt->toIso8601String());
            }
        }
    }

    // ── Tickets + comments (tickets.ts) ──────────────────────────────────────

    private function seedTicketsAndComments(): void
    {
        $tenantByMockId = [
            't-aminah' => self::TENANT_AMINAH,
            't-arif' => self::TENANT_ARIF,
            't-siti' => self::TENANT_SITI,
        ];

        $tickets = [
            [
                'id' => self::TK_POWER_TRIP,
                'unitId' => self::UNIT_WANGSA_1,
                'reporterId' => $tenantByMockId['t-arif'],
                'reporterRole' => 'tenant',
                'category' => 'electrical',
                'priority' => 'urgent',
                'title' => 'Power tripped repeatedly after storm',
                'description' => "Last night's storm seemed to take out the main panel — kept tripping when the AC starts. Whole shop without power for 30 mins this morning. Need someone to look ASAP, fridge is at risk.",
                'status' => 'new',
                'createdAt' => '2026-05-08T07:14:00Z',
                'updatedAt' => '2026-05-08T07:14:00Z',
                'resolvedAt' => null,
            ],
            [
                'id' => self::TK_REPAINT,
                'unitId' => self::UNIT_SUBANG_ROOM3,
                'reporterId' => self::OWNER_ID,
                'reporterRole' => 'owner',
                'category' => 'other',
                'priority' => 'medium',
                'title' => 'Schedule repaint before next tenant',
                'description' => 'Back room is vacant. Walls have nail holes from previous tenant + minor scuffs. Want to repaint and patch before listing again.',
                'status' => 'new',
                'createdAt' => '2026-05-05T03:00:00Z',
                'updatedAt' => '2026-05-05T03:00:00Z',
                'resolvedAt' => null,
            ],
            [
                'id' => self::TK_BATH_DRAIN,
                'unitId' => self::UNIT_SURIA_1,
                'reporterId' => $tenantByMockId['t-aminah'],
                'reporterRole' => 'tenant',
                'category' => 'plumbing',
                'priority' => 'high',
                'title' => 'Master bathroom drain very slow',
                'description' => 'Standing water after every shower, takes ~15 mins to drain. Tried plunger, no improvement. Smells starting to build up.',
                'status' => 'in_progress',
                'createdAt' => '2026-05-04T13:22:00Z',
                'updatedAt' => '2026-05-06T09:00:00Z',
                'resolvedAt' => null,
            ],
            [
                'id' => self::TK_TERMITES,
                'unitId' => self::UNIT_SUBANG_MASTER,
                'reporterId' => $tenantByMockId['t-siti'],
                'reporterRole' => 'tenant',
                'category' => 'pest',
                'priority' => 'high',
                'title' => 'Termites in window frame, exterminator booked',
                'description' => 'Found mud tunnels along the master bedroom window frame. Wood is soft to touch. Sent photos to landlord. Termite specialist scheduled for next Wednesday.',
                'status' => 'in_progress',
                'createdAt' => '2026-04-30T05:45:00Z',
                'updatedAt' => '2026-05-07T11:30:00Z',
                'resolvedAt' => null,
            ],
            [
                'id' => self::TK_TAP_DRIP,
                'unitId' => self::UNIT_SURIA_1,
                'reporterId' => $tenantByMockId['t-aminah'],
                'reporterRole' => 'tenant',
                'category' => 'plumbing',
                'priority' => 'low',
                'title' => 'Kitchen tap dripping',
                'description' => 'Cold side of the kitchen mixer drips constantly even when fully closed. Probably worn cartridge.',
                'status' => 'resolved',
                'createdAt' => '2026-04-12T10:00:00Z',
                'updatedAt' => '2026-04-18T14:00:00Z',
                'resolvedAt' => '2026-04-18T14:00:00Z',
            ],
            [
                'id' => self::TK_SHOP_LOCK,
                'unitId' => self::UNIT_WANGSA_1,
                'reporterId' => $tenantByMockId['t-arif'],
                'reporterRole' => 'tenant',
                'category' => 'other',
                'priority' => 'medium',
                'title' => 'Front shop door lock sticky',
                'description' => 'Key turns hard, especially in the morning. Worried about getting locked out at closing.',
                'status' => 'resolved',
                'createdAt' => '2026-03-22T09:30:00Z',
                'updatedAt' => '2026-04-02T16:20:00Z',
                'resolvedAt' => '2026-04-02T16:20:00Z',
            ],
            [
                'id' => self::TK_WATER_HEATER,
                'unitId' => self::UNIT_SUBANG_MASTER,
                'reporterId' => $tenantByMockId['t-siti'],
                'reporterRole' => 'tenant',
                'category' => 'appliance',
                'priority' => 'high',
                'title' => 'Water heater intermittent again',
                'description' => 'Was fixed a month ago (replaced heating element). Started cutting out again last week — only lukewarm water in the morning, sometimes cold mid-shower. Likely related to the same circuit.',
                'status' => 'reopened',
                'createdAt' => '2026-03-15T08:00:00Z',
                'updatedAt' => '2026-05-06T19:40:00Z',
                'resolvedAt' => null,
            ],
        ];

        foreach ($tickets as $t) {
            Ticket::updateOrCreate(['id' => $t['id']], [
                'unit_id' => $t['unitId'],
                'reporter_id' => $t['reporterId'],
                'reporter_role' => $t['reporterRole'],
                'category' => $t['category'],
                'priority' => $t['priority'],
                'title' => $t['title'],
                'description' => $t['description'],
                'status' => $t['status'],
                'resolved_at' => $t['resolvedAt'],
            ]);
            $this->pinTimestamps('tickets', $t['id'], $t['createdAt'], $t['updatedAt']);
        }

        $comments = [
            [self::TC_BATH_1, self::TK_BATH_DRAIN, self::OWNER_ID, 'owner', 'Plumber booked for Thursday morning, between 9am and 11am. Please be home or leave the spare key with the guard.', '2026-05-05T02:30:00Z'],
            [self::TC_BATH_2, self::TK_BATH_DRAIN, $tenantByMockId['t-aminah'], 'tenant', "Confirmed, I'll be home until noon. Thanks.", '2026-05-05T03:08:00Z'],
            [self::TC_BATH_3, self::TK_BATH_DRAIN, self::OWNER_ID, 'owner', "Plumber found a small clog near the trap. Cleared and tested — drains fine now. Will close once you confirm it's still good after a few days.", '2026-05-06T09:00:00Z'],
            [self::TC_TERM_1, self::TK_TERMITES, self::OWNER_ID, 'owner', "Got the photos, thanks. Booked Anti-Pest Sdn Bhd for Wed 14 May. They'll do a full perimeter check + treat the affected frame.", '2026-05-01T01:00:00Z'],
            [self::TC_TERM_2, self::TK_TERMITES, $tenantByMockId['t-siti'], 'tenant', "Noted. I'll be moving out end of May anyway, so please keep me updated if it affects the deposit.", '2026-05-07T11:30:00Z'],
            [self::TC_HEAT_1, self::TK_WATER_HEATER, self::OWNER_ID, 'owner', 'Replaced the lower heating element last month — issue was a corroded element draining heat to the housing.', '2026-03-20T05:00:00Z'],
            [self::TC_HEAT_2, self::TK_WATER_HEATER, $tenantByMockId['t-siti'], 'tenant', 'Reopening — water is lukewarm again starting last week. Same symptoms as before.', '2026-05-06T19:40:00Z'],
        ];

        foreach ($comments as [$id, $ticketId, $authorId, $authorRole, $body, $createdAt]) {
            TicketComment::updateOrCreate(['id' => $id], [
                'ticket_id' => $ticketId,
                'author_id' => $authorId,
                'author_role' => $authorRole,
                'body' => $body,
            ]);
            // Comments sort by created_at (Ticket::comments() orders by it) —
            // mock ordering must survive, so pin it explicitly.
            $this->pinCreatedAt('ticket_comments', $id, $createdAt);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function pinCreatedAt(string $table, string $id, string $createdAt): void
    {
        $this->pinTimestamps($table, $id, $createdAt, $createdAt);
    }

    /**
     * Raw-updates created_at/updated_at to mock-accurate values. Needed
     * because Eloquent stamps its own timestamps on create/update, and these
     * two columns aren't mass-assignable — but mock dates matter for
     * chronological ordering (e.g. ticket comment threads sort by
     * created_at). MySQL rejects ISO 8601 ("...T...Z") literals, so both
     * inputs are normalized through Carbon first.
     */
    private function pinTimestamps(string $table, string $id, string $createdAt, string $updatedAt): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_at' => Carbon::parse($createdAt)->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::parse($updatedAt)->format('Y-m-d H:i:s'),
        ]);
    }
}
