<?php

namespace App\Http\Controllers;

use App\Models\ChargeType;
use App\Models\Shipment;
use App\Models\ShipmentCharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShipmentChargeController extends Controller
{
    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('manageCharges', $shipment);

        $data = $request->validate([
            'charge_type_id' => ['nullable', 'exists:charge_types,id'],
            'description' => ['required_without:charge_type_id', 'nullable', 'string', 'max:255'],
            'basis' => ['required', Rule::in(['flat', 'per_kg', 'per_piece', 'per_awb'])],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'side' => ['required', Rule::in(['receivable', 'payable'])],
            'is_taxable' => ['nullable', 'boolean'],
        ]);

        // Nullable fields absent from the request are absent from $data too.
        $data['charge_type_id'] ??= null;
        $data['description'] ??= null;
        $data['quantity'] ??= null;

        $type = $data['charge_type_id'] ? ChargeType::find($data['charge_type_id']) : null;

        $charge = new ShipmentCharge([
            'basis' => $data['basis'],
            'quantity' => $data['quantity'] ?? 1,
        ]);

        // Weight and piece based charges take their quantity from the shipment so
        // a corrected weight reprices instead of leaving a stale amount behind.
        $quantity = $charge->resolveQuantity($shipment);
        $rate = (float) $data['rate'];

        $shipment->charges()->create([
            'charge_type_id' => $type?->id,
            'description' => $data['description'] ?: ($type?->name ?? 'Charge'),
            'basis' => $data['basis'],
            'quantity' => $quantity,
            'rate' => $rate,
            'amount' => round($quantity * $rate, 2),
            'currency' => strtoupper($data['currency']),
            'side' => $data['side'],
            'is_taxable' => $request->boolean('is_taxable'),
        ]);

        $shipment->recalculateChargesTotal();

        return back()->with('status', 'Charge added.');
    }

    public function destroy(Shipment $shipment, ShipmentCharge $charge): RedirectResponse
    {
        Gate::authorize('manageCharges', $shipment);
        abort_if($charge->shipment_id !== $shipment->id, 404);

        $charge->delete();
        $shipment->recalculateChargesTotal();

        return back()->with('status', 'Charge removed.');
    }

    /** Re-price every weight based line after an operations weight correction. */
    public function reprice(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('manageCharges', $shipment);

        $changed = 0;

        foreach ($shipment->charges as $charge) {
            $quantity = $charge->resolveQuantity($shipment);
            $amount = round($quantity * (float) $charge->rate, 2);

            if ((float) $charge->amount !== $amount || (float) $charge->quantity !== $quantity) {
                $charge->update(['quantity' => $quantity, 'amount' => $amount]);
                $changed++;
            }
        }

        $shipment->recalculateChargesTotal();

        return back()->with('status', $changed === 0
            ? 'Charges were already in line with the current weight and pieces.'
            : "{$changed} charge ".str('line')->plural($changed).' repriced.');
    }
}
