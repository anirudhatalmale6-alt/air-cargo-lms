<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentStatusEvent;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShipmentStatusController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments) {}

    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('update', $shipment);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'occurred_at' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateTimeString()],
            'location' => ['nullable', 'string', 'size:3', 'alpha'],
            'flight_number' => ['nullable', 'string', 'max:10'],
            'pieces' => ['nullable', 'integer', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visible_to_customer' => ['nullable', 'boolean'],
        ]);

        $event = $this->shipments->recordStatus(
            shipment: $shipment,
            status: ShipmentStatus::from($data['status']),
            occurredAt: now()->parse($data['occurred_at']),
            location: $data['location'] ?? null,
            flightNumber: $data['flight_number'] ?? null,
            pieces: $data['pieces'] ?? null,
            weightKg: isset($data['weight_kg']) ? (float) $data['weight_kg'] : null,
            description: $data['description'] ?? null,
            // Absent checkbox means internal only, never "show it anyway".
            visibleToCustomer: $request->boolean('visible_to_customer'),
            user: $request->user(),
        );

        return back()->with('status', "Status {$event->status->label()} recorded.");
    }

    public function destroy(Request $request, Shipment $shipment, ShipmentStatusEvent $event): RedirectResponse
    {
        Gate::authorize('update', $shipment);
        abort_if($event->shipment_id !== $shipment->id, 404);

        $event->delete();

        // The headline status must follow whatever event is now the newest.
        $newest = $shipment->statusEvents()->orderByDesc('occurred_at')->orderByDesc('id')->first();

        $shipment->update([
            'current_status' => $newest?->status ?? ShipmentStatus::Draft,
            'status_changed_at' => $newest?->occurred_at,
        ]);

        return back()->with('status', 'Status entry removed.');
    }
}
