<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Airline;
use App\Models\Awb;
use App\Models\ChargeType;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentService;
use App\Support\AwbNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShipmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Airline $airline;

    private Customer $customer;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->airline = Airline::create([
            'iata_code' => 'LH', 'icao_code' => 'DLH', 'awb_prefix' => '020',
            'name' => 'Lufthansa Cargo', 'tracking_driver' => 'internal', 'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUS-0001', 'name' => 'Meridian Pharma', 'default_currency' => 'USD', 'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Ops Desk', 'email' => 'ops@example.test', 'password' => 'secret1234',
            'role' => UserRole::Staff, 'is_active' => true,
        ]);
    }

    public function test_staff_can_create_a_shipment_and_the_awb_is_taken_out_of_stock(): void
    {
        $awb = AwbNumber::fromRunningSerial('020', 4471182);
        Awb::fromNumber($this->airline, $awb);

        $response = $this->actingAs($this->staff)->post(route('shipments.store'), $this->payload([
            'awb_input' => $awb->formatted(),
        ]));

        $shipment = Shipment::firstOrFail();

        $response->assertRedirect(route('shipments.show', $shipment));

        $this->assertSame($awb->digits(), $shipment->awb_number);
        $this->assertSame($this->airline->id, $shipment->airline_id);
        $this->assertSame('assigned', Awb::where('number', $awb->digits())->value('status'));

        // Creating a shipment opens its history with a Booked milestone.
        $this->assertSame(ShipmentStatus::Booked, $shipment->current_status);
        $this->assertSame(1, $shipment->statusEvents()->count());
    }

    public function test_a_mistyped_awb_is_rejected_by_the_check_digit(): void
    {
        $this->actingAs($this->staff)
            ->post(route('shipments.store'), $this->payload(['awb_input' => '020-1234567 9']))
            ->assertSessionHasErrors('awb_input');

        $this->assertSame(0, Shipment::count());
    }

    public function test_the_same_awb_cannot_sit_on_two_shipments(): void
    {
        $awb = AwbNumber::fromRunningSerial('020', 4471182);

        $first = $this->createShipment();
        app(ShipmentService::class)->assignAwb($first, $awb->digits());

        $second = $this->createShipment(['reference' => 'EXP-2026-00002']);

        $this->actingAs($this->staff)
            ->put(route('shipments.update', $second), $this->payload([
                'reference' => $second->reference,
                'awb_input' => $awb->formatted(),
            ]))
            ->assertSessionHasErrors('awb_input');

        $this->assertNull($second->fresh()->awb_number);
    }

    public function test_chargeable_weight_falls_back_to_the_volumetric_figure(): void
    {
        // 2 cbm x 166.6667 = 333.33 kg, which beats the 120 kg gross.
        $this->actingAs($this->staff)->post(route('shipments.store'), $this->payload([
            'gross_weight_kg' => 120,
            'volume_cbm' => 2,
            'chargeable_weight_kg' => null,
        ]));

        $this->assertSame('333.33', Shipment::firstOrFail()->chargeable_weight_kg);
    }

    public function test_the_headline_status_follows_the_newest_event_not_the_last_one_typed(): void
    {
        $shipment = $this->createShipment();
        $service = app(ShipmentService::class);

        $service->recordStatus($shipment, ShipmentStatus::Departed, now()->subDay(), user: $this->staff);
        $this->assertSame(ShipmentStatus::Departed, $shipment->fresh()->current_status);

        // A forgotten earlier milestone must not drag the shipment backwards.
        $service->recordStatus($shipment, ShipmentStatus::Received, now()->subDays(3), user: $this->staff);
        $this->assertSame(ShipmentStatus::Departed, $shipment->fresh()->current_status);

        $service->recordStatus($shipment, ShipmentStatus::Arrived, now(), user: $this->staff);
        $this->assertSame(ShipmentStatus::Arrived, $shipment->fresh()->current_status);
    }

    public function test_deleting_the_newest_status_rolls_the_shipment_back(): void
    {
        $shipment = $this->createShipment();
        $service = app(ShipmentService::class);

        $service->recordStatus($shipment, ShipmentStatus::Received, now()->subDay(), user: $this->staff);
        $latest = $service->recordStatus($shipment, ShipmentStatus::Departed, now(), user: $this->staff);

        $this->actingAs($this->staff)
            ->delete(route('shipments.status.destroy', [$shipment, $latest]))
            ->assertRedirect();

        $this->assertSame(ShipmentStatus::Received, $shipment->fresh()->current_status);
    }

    public function test_a_per_kg_charge_takes_its_quantity_from_the_chargeable_weight(): void
    {
        $shipment = $this->createShipment(['chargeable_weight_kg' => 642.50]);

        $type = ChargeType::create([
            'code' => 'AIR', 'name' => 'Air freight', 'basis' => 'per_kg', 'default_rate' => 2.85, 'is_active' => true,
        ]);

        $this->actingAs($this->staff)->post(route('shipments.charges.store', $shipment), [
            'charge_type_id' => $type->id,
            'description' => 'Air freight',
            'basis' => 'per_kg',
            'quantity' => 1,
            'rate' => 2.85,
            'currency' => 'USD',
            'side' => 'receivable',
        ])->assertRedirect();

        $charge = $shipment->charges()->firstOrFail();

        $this->assertSame('642.500', $charge->quantity);
        $this->assertSame('1831.13', $charge->amount);
        $this->assertSame('1831.13', $shipment->fresh()->charges_total);
    }

    public function test_repricing_follows_a_corrected_weight(): void
    {
        $shipment = $this->createShipment(['chargeable_weight_kg' => 100]);

        ChargeType::create(['code' => 'AIR', 'name' => 'Air freight', 'basis' => 'per_kg', 'default_rate' => 2, 'is_active' => true]);

        $shipment->charges()->create([
            'description' => 'Air freight', 'basis' => 'per_kg', 'quantity' => 100,
            'rate' => 2, 'amount' => 200, 'currency' => 'USD', 'side' => 'receivable',
        ]);
        $shipment->recalculateChargesTotal();

        $shipment->update(['chargeable_weight_kg' => 150]);

        $this->actingAs($this->staff)
            ->post(route('shipments.charges.reprice', $shipment))
            ->assertRedirect();

        $this->assertSame('300.00', $shipment->charges()->first()->amount);
        $this->assertSame('300.00', $shipment->fresh()->charges_total);
    }

    public function test_documents_are_stored_with_a_checksum_and_can_be_downloaded(): void
    {
        Storage::fake('local');

        $shipment = $this->createShipment();

        $this->actingAs($this->staff)->post(route('shipments.documents.store', $shipment), [
            'type' => 'invoice',
            'title' => 'Invoice 2026-114',
            'files' => [UploadedFile::fake()->create('invoice.pdf', 40, 'application/pdf')],
            'visible_to_customer' => '1',
        ])->assertRedirect();

        $document = $shipment->documents()->firstOrFail();

        $this->assertSame('invoice.pdf', $document->original_name);
        $this->assertSame(64, strlen($document->checksum));
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($this->staff)
            ->get(route('shipments.documents.download', [$shipment, $document]))
            ->assertOk();
    }

    public function test_an_unsupported_file_type_is_refused(): void
    {
        Storage::fake('local');

        $this->actingAs($this->staff)
            ->post(route('shipments.documents.store', $this->createShipment()), [
                'type' => 'invoice',
                'files' => [UploadedFile::fake()->create('payload.php', 5, 'application/x-php')],
            ])
            ->assertSessionHasErrors('files.0');
    }

    private function createShipment(array $overrides = []): Shipment
    {
        return Shipment::create(array_merge([
            'reference' => 'EXP-2026-00001',
            'direction' => 'export',
            'customer_id' => $this->customer->id,
            'origin' => 'BOM',
            'destination' => 'FRA',
            'pieces' => 18,
            'gross_weight_kg' => 642.50,
            'chargeable_weight_kg' => 642.50,
            'currency' => 'USD',
            'payment_term' => 'prepaid',
            'current_status' => ShipmentStatus::Draft,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'direction' => 'export',
            'customer_id' => $this->customer->id,
            'origin' => 'bom',
            'destination' => 'fra',
            'pieces' => 18,
            'gross_weight_kg' => 642.50,
            'currency' => 'USD',
            'payment_term' => 'prepaid',
        ], $overrides);
    }
}
