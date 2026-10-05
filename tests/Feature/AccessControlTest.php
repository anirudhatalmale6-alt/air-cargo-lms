<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Customer $mine;

    private Customer $theirs;

    private User $customerUser;

    private User $staff;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mine = Customer::create(['code' => 'CUS-0001', 'name' => 'My Company', 'default_currency' => 'USD', 'is_active' => true]);
        $this->theirs = Customer::create(['code' => 'CUS-0002', 'name' => 'Another Company', 'default_currency' => 'USD', 'is_active' => true]);

        $this->customerUser = User::create([
            'name' => 'Customer Login', 'email' => 'customer@example.test', 'password' => 'secret1234',
            'role' => UserRole::Customer, 'customer_id' => $this->mine->id, 'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'secret1234',
            'role' => UserRole::Staff, 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret1234',
            'role' => UserRole::Admin, 'is_active' => true,
        ]);
    }

    public function test_the_dashboard_requires_a_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_disabled_account_cannot_sign_in_even_with_the_right_password(): void
    {
        $this->staff->update(['is_active' => false]);

        $this->post(route('login'), ['email' => 'ops@example.test', 'password' => 'secret1234'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_valid_login_reaches_the_dashboard(): void
    {
        $this->post(route('login'), ['email' => 'ops@example.test', 'password' => 'secret1234'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->staff);
        $this->assertNotNull($this->staff->fresh()->last_login_at);
    }

    public function test_a_customer_sees_only_their_own_shipments_in_the_list(): void
    {
        $ours = $this->shipmentFor($this->mine, 'EXP-2026-00001');
        $other = $this->shipmentFor($this->theirs, 'EXP-2026-00002');

        $this->actingAs($this->customerUser)
            ->get(route('shipments.index'))
            ->assertOk()
            ->assertSee($ours->reference)
            ->assertDontSee($other->reference);
    }

    public function test_a_customer_cannot_open_another_customers_shipment(): void
    {
        $other = $this->shipmentFor($this->theirs, 'EXP-2026-00002');

        $this->actingAs($this->customerUser)
            ->get(route('shipments.show', $other))
            ->assertForbidden();
    }

    public function test_a_customer_login_with_no_customer_record_sees_nothing(): void
    {
        $orphan = User::create([
            'name' => 'Orphan', 'email' => 'orphan@example.test', 'password' => 'secret1234',
            'role' => UserRole::Customer, 'is_active' => true,
        ]);

        $ours = $this->shipmentFor($this->mine, 'EXP-2026-00001');

        $this->actingAs($orphan)
            ->get(route('shipments.index'))
            ->assertOk()
            ->assertDontSee($ours->reference);

        $this->actingAs($orphan)->get(route('shipments.show', $ours))->assertForbidden();
    }

    public function test_a_customer_cannot_create_or_edit_shipments(): void
    {
        $ours = $this->shipmentFor($this->mine, 'EXP-2026-00001');

        $this->actingAs($this->customerUser)->get(route('shipments.create'))->assertForbidden();
        $this->actingAs($this->customerUser)->get(route('shipments.edit', $ours))->assertForbidden();
        $this->actingAs($this->customerUser)->post(route('shipments.status.store', $ours), [
            'status' => ShipmentStatus::Delivered->value,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
        ])->assertForbidden();
    }

    public function test_a_customer_cannot_reach_customer_airline_awb_or_user_admin(): void
    {
        foreach (['customers.index', 'airlines.index', 'awbs.index', 'users.index'] as $route) {
            $this->actingAs($this->customerUser)->get(route($route))->assertForbidden();
        }
    }

    public function test_operations_staff_can_manage_customers_but_not_users_or_airlines(): void
    {
        $this->actingAs($this->staff)->get(route('customers.index'))->assertOk();
        $this->actingAs($this->staff)->get(route('airlines.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('users.index'))->assertForbidden();
    }

    public function test_an_admin_reaches_every_admin_screen(): void
    {
        foreach (['customers.index', 'airlines.index', 'awbs.index', 'users.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
    }

    public function test_a_customer_cannot_download_a_document_marked_internal(): void
    {
        Storage::fake('local');

        $ours = $this->shipmentFor($this->mine, 'EXP-2026-00001');

        $this->actingAs($this->staff)->post(route('shipments.documents.store', $ours), [
            'type' => 'invoice',
            'files' => [UploadedFile::fake()->create('internal.pdf', 20, 'application/pdf')],
            // Checkbox unticked: staff only.
        ]);

        $document = $ours->documents()->firstOrFail();
        $this->assertFalse($document->visible_to_customer);

        $this->actingAs($this->customerUser)
            ->get(route('shipments.documents.download', [$ours, $document]))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->get(route('shipments.documents.download', [$ours, $document]))
            ->assertOk();
    }

    public function test_an_internal_status_entry_is_hidden_from_the_customer(): void
    {
        $ours = $this->shipmentFor($this->mine, 'EXP-2026-00001');

        $ours->statusEvents()->create([
            'status' => ShipmentStatus::OnHold,
            'occurred_at' => now(),
            'description' => 'Customer credit on stop, do not release',
            'visible_to_customer' => false,
        ]);

        $this->actingAs($this->customerUser)
            ->get(route('shipments.show', $ours))
            ->assertOk()
            ->assertDontSee('do not release');

        $this->actingAs($this->staff)
            ->get(route('shipments.show', $ours))
            ->assertOk()
            ->assertSee('do not release');
    }

    public function test_a_document_from_another_shipment_cannot_be_downloaded_through_this_one(): void
    {
        Storage::fake('local');

        $ours = $this->shipmentFor($this->mine, 'EXP-2026-00001');
        $other = $this->shipmentFor($this->theirs, 'EXP-2026-00002');

        $this->actingAs($this->staff)->post(route('shipments.documents.store', $other), [
            'type' => 'invoice',
            'files' => [UploadedFile::fake()->create('theirs.pdf', 20, 'application/pdf')],
            'visible_to_customer' => '1',
        ]);

        $theirDocument = $other->documents()->firstOrFail();

        // The customer can see $ours, so the guard has to be the shipment_id check.
        $this->actingAs($this->customerUser)
            ->get(route('shipments.documents.download', [$ours, $theirDocument]))
            ->assertNotFound();
    }

    public function test_the_last_active_admin_cannot_be_demoted(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'role' => UserRole::Staff->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    private function shipmentFor(Customer $customer, string $reference): Shipment
    {
        return Shipment::create([
            'reference' => $reference,
            'direction' => 'export',
            'customer_id' => $customer->id,
            'origin' => 'BOM',
            'destination' => 'FRA',
            'pieces' => 5,
            'gross_weight_kg' => 100,
            'chargeable_weight_kg' => 100,
            'currency' => 'USD',
            'payment_term' => 'prepaid',
            'current_status' => ShipmentStatus::Booked,
        ]);
    }
}
