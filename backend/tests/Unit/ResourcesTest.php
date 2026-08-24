<?php

namespace Tests\Unit;

use App\Http\Resources\AgreementResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\TenantResource;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_resource_shape(): void
    {
        $invoice = Invoice::factory()->create(['amount_cents' => 180000, 'late_fee_cents' => 5000]);
        $out = (new InvoiceResource($invoice))->resolve();

        $this->assertSame(
            ['id', 'agreementId', 'invoiceNumber', 'amount', 'lateFee', 'dueDate', 'status', 'createdAt'],
            array_keys($out)
        );
        $this->assertSame(180000, $out['amount']);
        $this->assertSame(5000, $out['lateFee']);
        $this->assertSame('2026-07-01', $out['dueDate']);
        $this->assertSame('pending', $out['status']);
    }

    public function test_agreement_resource_shape(): void
    {
        $agreement = Agreement::factory()->create();
        $out = (new AgreementResource($agreement))->resolve();

        $this->assertSame(
            ['id', 'unitId', 'tenantId', 'startDate', 'endDate', 'rentAmount', 'depositAmount', 'lateFee', 'rentDueDay', 'status', 'createdAt'],
            array_keys($out)
        );
        $this->assertSame(180000, $out['rentAmount']);
        $this->assertSame('2026-01-01', $out['startDate']);
    }

    public function test_property_resource_shape_with_co_owners_and_blobs(): void
    {
        $property = Property::factory()->create([
            'ownership' => ['titleType' => 'freehold', 'purchasePrice' => 45000000],
            'utilities' => ['tnbAccountNo' => '123456'],
        ]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $property->owner_id]);
        $out = (new PropertyResource($property->load('coOwners')))->resolve();

        $this->assertSame(
            ['id', 'ownerId', 'name', 'internalLabel', 'type', 'purpose', 'notes', 'address', 'city', 'state', 'postcode',
             'yearBuilt', 'builtUpSqft', 'landSqft', 'bedrooms', 'bathrooms', 'parkingLots', 'furnishing',
             'ownership', 'utilities', 'coOwners', 'createdAt'],
            array_keys($out)
        );
        // Blob interiors pass through camelCase verbatim
        $this->assertSame('freehold', $out['ownership']['titleType']);
        $coOwner = (array) $out['coOwners'][0]->resolve();
        $this->assertSame(['id', 'name', 'sharePct', 'isPrimary'], array_keys($coOwner));
        $this->assertSame(100.0, $coOwner['sharePct']);
        $this->assertTrue($coOwner['isPrimary']);
    }

    public function test_tenant_resource_shape(): void
    {
        $tenant = User::factory()->invitedTenant()->create([
            'personal_info'     => ['icNumber' => '880314-14-5687', 'monthlyIncome' => 650000],
            'emergency_contact' => ['name' => 'Ali', 'phone' => '+60 12', 'relationship' => 'Brother'],
        ]);
        $out = (new TenantResource($tenant))->resolve();

        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'status', 'invitedAt', 'createdAt', 'personal', 'emergencyContact'],
            array_keys($out)
        );
        $this->assertSame('invited', $out['status']);
        $this->assertSame(650000, $out['personal']['monthlyIncome']);
    }
}
