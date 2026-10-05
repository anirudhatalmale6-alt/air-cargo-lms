<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\TrackingLookup;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // A fresh builder per metric: reusing one would stack the where clauses.
        $base = fn () => Shipment::query()->visibleTo($user);

        $counts = [
            'open' => $base()->whereNotIn('current_status', [
                ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value,
            ])->count(),
            'in_transit' => $base()->whereIn('current_status', [
                ShipmentStatus::Departed->value, ShipmentStatus::InTransit->value,
            ])->count(),
            'arriving_today' => $base()->whereDate('eta', today())
                ->whereNotIn('current_status', [
                    ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value,
                ])->count(),
            'delivered_this_month' => $base()
                ->where('current_status', ShipmentStatus::Delivered->value)
                ->where('status_changed_at', '>=', now()->startOfMonth())
                ->count(),
            'on_hold' => $base()->where('current_status', ShipmentStatus::OnHold->value)->count(),
        ];

        $splits = [
            'export' => $base()->where('direction', 'export')->where('current_status', '!=', ShipmentStatus::Cancelled->value)->count(),
            'import' => $base()->where('direction', 'import')->where('current_status', '!=', ShipmentStatus::Cancelled->value)->count(),
        ];

        $statusBreakdown = $base()
            ->selectRaw('current_status, count(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'current_status')
            ->mapWithKeys(fn ($total, $status) => [
                (ShipmentStatus::tryFrom($status)?->label() ?? $status) => $total,
            ])
            ->all();

        $recent = $base()
            ->with(['customer:id,name,code', 'airline:id,iata_code,name'])
            ->latest('id')
            ->limit(8)
            ->get();

        // Open jobs whose expected paperwork is not on file yet.
        $documentGaps = $base()
            ->with(['documents:id,shipment_id,type', 'customer:id,name'])
            ->whereNotIn('current_status', [ShipmentStatus::Draft->value, ShipmentStatus::Cancelled->value])
            ->latest('id')
            ->limit(40)
            ->get()
            ->filter(fn (Shipment $s) => $s->missingDocumentTypes() !== [])
            ->take(6);

        $unbilled = $base()
            ->whereNotIn('current_status', [ShipmentStatus::Draft->value, ShipmentStatus::Cancelled->value])
            ->where('charges_total', 0)
            ->count();

        $tonnage = (float) $base()
            ->where('current_status', '!=', ShipmentStatus::Cancelled->value)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('chargeable_weight_kg');

        return view('dashboard', [
            'counts' => $counts,
            'splits' => $splits,
            'statusBreakdown' => $statusBreakdown,
            'recent' => $recent,
            'documentGaps' => $documentGaps,
            'unbilled' => $unbilled,
            'tonnage' => $tonnage,
            'customerCount' => $user->isStaff() ? Customer::active()->count() : null,
            'lookupsToday' => $user->isStaff()
                ? TrackingLookup::whereDate('created_at', today())->count()
                : null,
        ]);
    }
}
