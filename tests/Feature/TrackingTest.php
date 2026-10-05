<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Airline;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\TrackingLookup;
use App\Models\TrackingSnapshot;
use App\Models\User;
use App\Services\Tracking\Drivers\GenericHttpDriver;
use App\Services\Tracking\Drivers\InternalDriver;
use App\Services\Tracking\TrackingManager;
use App\Support\AwbNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private Airline $lufthansa;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tracking.demo_enabled', false);

        $this->lufthansa = Airline::create([
            'iata_code' => 'LH', 'awb_prefix' => '020', 'name' => 'Lufthansa Cargo',
            'tracking_driver' => 'internal', 'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUS-0001', 'name' => 'Meridian Pharma', 'default_currency' => 'USD', 'is_active' => true,
        ]);
    }

    public function test_the_public_page_rejects_a_bad_check_digit_before_any_lookup(): void
    {
        $this->get(route('tracking.show', ['awb' => '020-1234567 9']))
            ->assertOk()
            ->assertSee('should end in 5');

        $this->assertSame(0, TrackingLookup::count());
    }

    public function test_an_awb_we_hold_is_tracked_from_our_own_status_history(): void
    {
        $shipment = $this->shipmentWithHistory();

        $this->get(route('tracking.show', ['awb' => $shipment->awb_number]))
            ->assertOk()
            ->assertSee('Departed')
            ->assertSee('Cargo received at the terminal')
            ->assertSee($shipment->reference);
    }

    public function test_an_entry_hidden_from_the_customer_never_reaches_the_public_page(): void
    {
        $shipment = $this->shipmentWithHistory();

        $shipment->statusEvents()->create([
            'status' => ShipmentStatus::OnHold,
            'occurred_at' => now(),
            'description' => 'Credit stop, hold at warehouse',
            'visible_to_customer' => false,
        ]);

        $this->get(route('tracking.show', ['awb' => $shipment->awb_number]))
            ->assertOk()
            ->assertDontSee('Credit stop');
    }

    public function test_an_unknown_awb_is_reported_rather_than_erroring(): void
    {
        $this->get(route('tracking.show', ['awb' => AwbNumber::fromRunningSerial('020', 9999999)->formatted()]))
            ->assertOk()
            ->assertSee('not in our records');
    }

    public function test_the_json_endpoint_returns_a_normalised_payload(): void
    {
        $shipment = $this->shipmentWithHistory();

        $response = $this->getJson(route('tracking.api', ['awb' => $shipment->awb_number]))->assertOk();

        $response->assertJsonPath('awb_number', $shipment->awb_number);
        $response->assertJsonPath('latest_status', ShipmentStatus::Departed->value);
        $response->assertJsonPath('found', true);
        $this->assertCount(3, $response->json('events'));
    }

    public function test_the_json_endpoint_rejects_an_invalid_awb_with_422(): void
    {
        $this->getJson(route('tracking.api', ['awb' => '02012345679']))->assertStatus(422);
    }

    public function test_every_lookup_is_logged(): void
    {
        $shipment = $this->shipmentWithHistory();

        $this->get(route('tracking.show', ['awb' => $shipment->awb_number]));

        $lookup = TrackingLookup::firstOrFail();

        $this->assertSame($shipment->awb_number, $lookup->awb_number);
        $this->assertSame('internal', $lookup->driver);
        $this->assertTrue($lookup->found);
    }

    /*
    |--------------------------------------------------------------------------
    | Driver resolution: the part that makes "add more airlines later" true
    |--------------------------------------------------------------------------
    */

    public function test_the_airline_row_decides_which_driver_answers(): void
    {
        $this->configureFakeProvider();

        /** @var TrackingManager $manager */
        $manager = app(TrackingManager::class);
        $awb = AwbNumber::fromRunningSerial('020', 4471182);

        $this->assertInstanceOf(InternalDriver::class, $manager->driverFor($awb));

        $this->lufthansa->update(['tracking_driver' => 'test_airline']);

        $resolved = app(TrackingManager::class)->driverFor($awb);
        $this->assertInstanceOf(GenericHttpDriver::class, $resolved);
        $this->assertSame('test_airline', $resolved->key());
    }

    public function test_a_prefix_scoped_driver_claims_its_airline_even_with_no_airline_row(): void
    {
        // No row at all for 020, so resolution has to fall back to prefix lists.
        $this->lufthansa->delete();

        $manager = app(TrackingManager::class);

        // config/tracking.php ships a 020-scoped Lufthansa Cargo driver.
        $this->assertSame('lufthansa', $manager->driverFor(AwbNumber::fromRunningSerial('020', 1))->key());

        // 176 is in no driver's prefix list, so it falls through to the default.
        $this->assertSame('internal', $manager->driverFor(AwbNumber::fromRunningSerial('176', 1))->key());
    }

    public function test_a_provider_response_is_mapped_into_our_milestones(): void
    {
        $this->configureFakeProvider();
        $this->lufthansa->update(['tracking_driver' => 'test_airline']);

        $awb = AwbNumber::fromRunningSerial('020', 4471182);

        Http::fake([
            'cargo.test/*' => Http::response([
                'shipment' => [
                    'origin' => 'BOM',
                    'destination' => 'FRA',
                    'pieces' => 18,
                    'weight' => 642.5,
                    'events' => [
                        ['code' => 'RCS', 'timestamp' => '2026-09-28T08:15:00Z', 'airport' => 'BOM', 'text' => 'Received'],
                        ['code' => 'DEP', 'timestamp' => '2026-09-29T02:40:00Z', 'airport' => 'BOM', 'flight' => 'LH761', 'text' => 'Uplifted'],
                        ['code' => 'ARR', 'timestamp' => '2026-09-29T11:05:00Z', 'airport' => 'FRA', 'text' => 'Arrived'],
                        ['code' => 'ZZZ', 'timestamp' => '2026-09-29T12:00:00Z', 'airport' => 'FRA', 'text' => 'Unknown code'],
                    ],
                ],
            ]),
        ]);

        $result = app(TrackingManager::class)->track($awb->digits());

        $this->assertTrue($result->found);
        $this->assertSame('test_airline', $result->driver);
        $this->assertSame('BOM', $result->origin);
        $this->assertSame(18, $result->pieces);

        // The unmapped ZZZ code is dropped rather than shown as a bogus milestone.
        $this->assertCount(3, $result->events);
        $this->assertSame(ShipmentStatus::Arrived, $result->latestStatus());
        $this->assertSame('LH761', $result->events[1]->flightNumber);
    }

    public function test_a_provider_answer_is_cached_so_the_second_lookup_makes_no_call(): void
    {
        $this->configureFakeProvider();
        $this->lufthansa->update(['tracking_driver' => 'test_airline']);

        $awb = AwbNumber::fromRunningSerial('020', 4471182);

        Http::fake(['cargo.test/*' => Http::response(['shipment' => ['events' => [
            ['code' => 'DEP', 'timestamp' => '2026-09-29T02:40:00Z', 'airport' => 'BOM'],
        ]]])]);

        app(TrackingManager::class)->track($awb->digits());
        Http::assertSentCount(1);

        $second = app(TrackingManager::class)->track($awb->digits());

        Http::assertSentCount(1);
        $this->assertTrue($second->fromCache);
        $this->assertSame(1, TrackingSnapshot::where('awb_number', $awb->digits())->count());
    }

    public function test_a_dead_provider_falls_back_to_the_last_known_history(): void
    {
        $this->configureFakeProvider();
        $this->lufthansa->update(['tracking_driver' => 'test_airline']);

        $awb = AwbNumber::fromRunningSerial('020', 4471182);

        // First call succeeds, second call gets a dead gateway.
        Http::fakeSequence('cargo.test/*')
            ->push(['shipment' => ['events' => [
                ['code' => 'DEP', 'timestamp' => '2026-09-29T02:40:00Z', 'airport' => 'BOM'],
            ]]])
            ->pushStatus(502);

        app(TrackingManager::class)->track($awb->digits());

        $result = app(TrackingManager::class)->track($awb->digits(), forceRefresh: true);

        $this->assertTrue($result->found, 'A dead provider must not wipe out the history we already hold.');
        $this->assertTrue($result->fromCache);
        $this->assertSame(ShipmentStatus::Departed, $result->latestStatus());
    }

    public function test_an_unconfigured_airline_driver_says_so_instead_of_pretending(): void
    {
        config()->set('tracking.drivers.empty_airline', [
            'via' => GenericHttpDriver::class,
            'name' => 'Not Wired Up Yet',
            'prefixes' => ['020'],
            'endpoint' => null,
        ]);

        $this->lufthansa->update(['tracking_driver' => 'empty_airline']);

        $result = app(TrackingManager::class)->track(AwbNumber::fromRunningSerial('020', 4471182)->digits());

        $this->assertFalse($result->found);
        $this->assertStringContainsString('no endpoint configured', $result->message);
    }

    public function test_staff_can_pull_carrier_events_into_our_own_history_without_duplicates(): void
    {
        $this->configureFakeProvider();
        $this->lufthansa->update(['tracking_driver' => 'test_airline']);

        $shipment = $this->shipmentWithHistory();
        $staff = User::create([
            'name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'secret1234',
            'role' => UserRole::Staff, 'is_active' => true,
        ]);

        Http::fake(['cargo.test/*' => Http::response(['shipment' => ['events' => [
            ['code' => 'ARR', 'timestamp' => '2026-09-29T11:05:00Z', 'airport' => 'FRA'],
            ['code' => 'NFD', 'timestamp' => '2026-09-29T13:20:00Z', 'airport' => 'FRA'],
        ]]])]);

        $before = $shipment->statusEvents()->count();

        $this->actingAs($staff)->post(route('shipments.tracking.sync', $shipment))->assertRedirect();

        $this->assertSame($before + 2, $shipment->statusEvents()->count());
        $this->assertSame(ShipmentStatus::ReadyForDelivery, $shipment->fresh()->current_status);
        $this->assertSame(2, $shipment->statusEvents()->where('source', 'api:test_airline')->count());

        // Running it again must not double up.
        $this->actingAs($staff)->post(route('shipments.tracking.sync', $shipment));
        $this->assertSame($before + 2, $shipment->statusEvents()->count());
    }

    private function configureFakeProvider(): void
    {
        config()->set('tracking.drivers.test_airline', [
            'via' => GenericHttpDriver::class,
            'name' => 'Test Carrier API',
            'prefixes' => ['020'],
            'endpoint' => 'https://cargo.test/awb/{awb}',
            'method' => 'GET',
            'api_key' => 'test-key',
            'api_key_header' => 'X-API-Key',
            'map' => [
                'origin' => 'shipment.origin',
                'destination' => 'shipment.destination',
                'pieces' => 'shipment.pieces',
                'weight' => 'shipment.weight',
                'events' => 'shipment.events',
                'event_status' => 'code',
                'event_time' => 'timestamp',
                'event_location' => 'airport',
                'event_flight' => 'flight',
                'event_description' => 'text',
            ],
            'status_map' => [
                'RCS' => 'received', 'DEP' => 'departed', 'ARR' => 'arrived',
                'NFD' => 'ready_for_delivery', 'DLV' => 'delivered',
            ],
        ]);
    }

    private function shipmentWithHistory(): Shipment
    {
        $awb = AwbNumber::fromRunningSerial('020', 4471182);

        $shipment = Shipment::create([
            'reference' => 'EXP-2026-00001',
            'direction' => 'export',
            'customer_id' => $this->customer->id,
            'airline_id' => $this->lufthansa->id,
            'awb_number' => $awb->digits(),
            'origin' => 'BOM',
            'destination' => 'FRA',
            'flight_number' => 'LH761',
            'pieces' => 18,
            'gross_weight_kg' => 642.50,
            'chargeable_weight_kg' => 642.50,
            'currency' => 'USD',
            'payment_term' => 'prepaid',
            'current_status' => ShipmentStatus::Departed,
        ]);

        foreach ([
            [ShipmentStatus::Booked, '2026-09-27 10:00:00', 'Space confirmed'],
            [ShipmentStatus::Received, '2026-09-28 08:15:00', 'Cargo received at the terminal'],
            [ShipmentStatus::Departed, '2026-09-29 02:40:00', 'Uplifted on LH761'],
        ] as [$status, $when, $note]) {
            $shipment->statusEvents()->create([
                'status' => $status,
                'occurred_at' => $when,
                'location' => 'BOM',
                'description' => $note,
                'visible_to_customer' => true,
            ]);
        }

        return $shipment;
    }
}
