<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Awb;
use App\Services\ShipmentService;
use App\Support\AwbNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AwbController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments) {}

    public function index(Request $request): View
    {
        $digits = preg_replace('/\D+/', '', (string) $request->get('q')) ?? '';

        $awbs = Awb::query()
            ->with(['airline:id,iata_code,name', 'shipment:id,awb_id,reference,direction'])
            ->when($request->filled('airline_id'), fn ($q) => $q->where('airline_id', $request->get('airline_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($digits !== '', fn ($q) => $q->where('number', 'like', '%'.$digits.'%'))
            ->orderBy('prefix')
            ->orderBy('serial')
            ->paginate(40)
            ->withQueryString();

        return view('awbs.index', [
            'awbs' => $awbs,
            'airlines' => Airline::orderBy('name')->get(['id', 'name', 'iata_code', 'awb_prefix']),
            'filters' => $request->only(['q', 'airline_id', 'status']),
            'summary' => Awb::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
        ]);
    }

    /** Generate a stock block. Check digits are computed, never typed. */
    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'airline_id' => ['required', 'exists:airlines,id'],
            'from_serial' => ['required', 'digits_between:1,7'],
            'count' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $airline = Airline::findOrFail($data['airline_id']);

        try {
            $outcome = $this->shipments->generateAwbStock(
                $airline,
                (int) $data['from_serial'],
                (int) $data['count'],
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['count' => $e->getMessage()]);
        }

        $message = "{$outcome['created']} AWB numbers added for {$airline->name}.";

        if ($outcome['skipped'] > 0) {
            $message .= " {$outcome['skipped']} were already on file and were left alone.";
        }

        return redirect()->route('awbs.index', ['airline_id' => $airline->id])->with('status', $message);
    }

    /** Add one number by hand, validating the check digit the operator read off the AWB. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $value = AwbNumber::tryParse($data['number']);

        if (! $value) {
            $digits = preg_replace('/\D+/', '', $data['number']) ?? '';

            $hint = strlen($digits) === 11
                ? ' The check digit should be '.AwbNumber::checkDigitFor(substr($digits, 3, 7)).'.'
                : ' An AWB is 11 digits in total.';

            return back()->withInput()->withErrors(['number' => 'That is not a valid AWB number.'.$hint]);
        }

        if (Awb::query()->where('number', $value->digits())->exists()) {
            return back()->withInput()->withErrors(['number' => "AWB {$value->formatted()} is already on file."]);
        }

        $airline = Airline::query()->where('awb_prefix', $value->prefix)->first();

        if (! $airline) {
            return back()->withInput()->withErrors([
                'number' => "No airline is registered for prefix {$value->prefix}. Add the airline first.",
            ]);
        }

        Awb::fromNumber($airline, $value, [
            'notes' => $data['notes'] ?? null,
            'received_on' => now()->toDateString(),
        ]);

        return back()->with('status', "AWB {$value->formatted()} added to {$airline->name} stock.");
    }

    public function update(Request $request, Awb $awb): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['available', 'assigned', 'used', 'void'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($awb->shipment && $data['status'] === 'available') {
            return back()->withErrors([
                'status' => "AWB {$awb->formatted()} is on shipment {$awb->shipment->reference}. Remove it there first.",
            ]);
        }

        $awb->update($data);

        return back()->with('status', "AWB {$awb->formatted()} marked {$data['status']}.");
    }

    public function destroy(Awb $awb): RedirectResponse
    {
        if ($awb->shipment) {
            return back()->withErrors([
                'status' => "AWB {$awb->formatted()} is on shipment {$awb->shipment->reference} and cannot be deleted.",
            ]);
        }

        $formatted = $awb->formatted();
        $awb->delete();

        return back()->with('status', "AWB {$formatted} removed from stock.");
    }
}
