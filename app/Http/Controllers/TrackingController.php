<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\Tracking\TrackingManager;
use App\Support\AwbNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The public face of the system: a customer types an AWB and sees the latest
 * status plus the full history. No login, no customer record needed.
 */
class TrackingController extends Controller
{
    public function __construct(private readonly TrackingManager $tracking) {}

    public function show(Request $request): View
    {
        $raw = trim((string) $request->get('awb', ''));

        if ($raw === '') {
            return view('tracking.show', ['raw' => '', 'result' => null, 'error' => null, 'shipment' => null]);
        }

        try {
            $result = $this->tracking->track(
                $raw,
                forceRefresh: $request->boolean('refresh'),
                ip: $request->ip(),
            );
        } catch (InvalidArgumentException $e) {
            return view('tracking.show', [
                'raw' => $raw,
                'result' => null,
                'error' => $e->getMessage(),
                'shipment' => null,
            ]);
        }

        // If the AWB is one of ours, show the job reference and route too.
        $shipment = Shipment::query()
            ->where('awb_number', $result->awb->digits())
            ->first(['id', 'reference', 'direction', 'origin', 'destination', 'pieces', 'gross_weight_kg', 'flight_number']);

        return view('tracking.show', [
            'raw' => $result->awb->formatted(),
            'result' => $result,
            'error' => null,
            'shipment' => $shipment,
        ]);
    }

    /** Same answer as JSON, so the client can embed tracking in another site later. */
    public function api(Request $request, string $awb): JsonResponse
    {
        try {
            AwbNumber::parse($awb);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $result = $this->tracking->track($awb, forceRefresh: $request->boolean('refresh'), ip: $request->ip());

        return response()->json($result->jsonSerialize(), $result->found ? 200 : 404);
    }
}
