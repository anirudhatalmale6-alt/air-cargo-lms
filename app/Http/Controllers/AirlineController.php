<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Services\Tracking\TrackingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AirlineController extends Controller
{
    public function __construct(private readonly TrackingManager $tracking) {}

    public function index(): View
    {
        return view('airlines.index', [
            'airlines' => Airline::query()
                ->withCount([
                    'awbs',
                    'awbs as available_awbs_count' => fn ($q) => $q->where('status', 'available'),
                    'shipments',
                ])
                ->orderBy('name')
                ->get(),
            'drivers' => $this->tracking->available(),
        ]);
    }

    public function create(): View
    {
        return view('airlines.form', [
            'airline' => new Airline(['is_active' => true, 'tracking_driver' => 'internal']),
            'drivers' => $this->tracking->available(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Airline::create($this->validated($request));

        return redirect()->route('airlines.index')->with('status', 'Airline added.');
    }

    public function edit(Airline $airline): View
    {
        return view('airlines.form', [
            'airline' => $airline,
            'drivers' => $this->tracking->available(),
        ]);
    }

    public function update(Request $request, Airline $airline): RedirectResponse
    {
        $airline->update($this->validated($request, $airline));

        return redirect()->route('airlines.index')->with('status', 'Airline updated.');
    }

    public function destroy(Airline $airline): RedirectResponse
    {
        if ($airline->shipments()->exists() || $airline->awbs()->exists()) {
            $airline->update(['is_active' => false]);

            return redirect()->route('airlines.index')->with('status',
                "{$airline->name} is referenced by shipments or AWB stock, so it was deactivated instead of deleted.");
        }

        $airline->delete();

        return redirect()->route('airlines.index')->with('status', 'Airline deleted.');
    }

    private function validated(Request $request, ?Airline $airline = null): array
    {
        $data = $request->validate([
            'iata_code' => ['required', 'string', 'size:2', 'alpha_num', Rule::unique('airlines', 'iata_code')->ignore($airline)],
            'icao_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'awb_prefix' => ['required', 'digits:3', Rule::unique('airlines', 'awb_prefix')->ignore($airline)],
            'name' => ['required', 'string', 'max:255'],
            'tracking_driver' => ['required', 'string', Rule::in(array_keys($this->tracking->available()))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['icao_code'] ??= null;
        $data['iata_code'] = strtoupper($data['iata_code']);
        $data['icao_code'] = $data['icao_code'] ? strtoupper($data['icao_code']) : null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
