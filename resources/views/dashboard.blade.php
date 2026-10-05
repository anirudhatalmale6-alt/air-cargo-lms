@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>{{ now()->format('l, d F Y') }}</p>
    </div>
    @can('create', App\Models\Shipment::class)
        <div class="spacer">
            <a class="btn accent" href="{{ route('shipments.create', ['direction' => 'export']) }}">New export</a>
            <a class="btn primary" href="{{ route('shipments.create', ['direction' => 'import']) }}">New import</a>
        </div>
    @endcan
</div>

<div class="stats">
    <div class="stat accent">
        <div class="label">Open shipments</div>
        <div class="value">{{ number_format($counts['open']) }}</div>
        <div class="hint">{{ $splits['export'] }} export <span class="sep">|</span> {{ $splits['import'] }} import</div>
    </div>
    <div class="stat warn">
        <div class="label">In transit</div>
        <div class="value">{{ number_format($counts['in_transit']) }}</div>
        <div class="hint">Departed but not yet arrived</div>
    </div>
    <div class="stat">
        <div class="label">Arriving today</div>
        <div class="value">{{ number_format($counts['arriving_today']) }}</div>
        <div class="hint">ETA {{ today()->format('d M') }}</div>
    </div>
    <div class="stat good">
        <div class="label">Delivered this month</div>
        <div class="value">{{ number_format($counts['delivered_this_month']) }}</div>
        <div class="hint">{{ number_format($tonnage, 0) }} kg chargeable booked</div>
    </div>
    @if ($counts['on_hold'] > 0)
        <div class="stat bad">
            <div class="label">On hold</div>
            <div class="value">{{ number_format($counts['on_hold']) }}</div>
            <div class="hint">Needs attention</div>
        </div>
    @endif
    @if ($customerCount !== null)
        <div class="stat">
            <div class="label">Active customers</div>
            <div class="value">{{ number_format($customerCount) }}</div>
            <div class="hint">{{ number_format($lookupsToday) }} AWB lookups today</div>
        </div>
    @endif
</div>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head">
                <h2>Latest shipments</h2>
                <div class="spacer"><a class="btn sm" href="{{ route('shipments.index') }}">View all</a></div>
            </div>
            <div class="table-scroll">
                <table class="data">
                    <thead>
                    <tr>
                        <th>Reference</th>
                        <th>AWB</th>
                        <th>Customer</th>
                        <th>Route</th>
                        <th>Status</th>
                        <th class="num">Pcs / kg</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($recent as $shipment)
                        <tr>
                            <td class="nowrap">
                                <a href="{{ route('shipments.show', $shipment) }}">{{ $shipment->reference }}</a><br>
                                <span class="pill-dir {{ $shipment->direction }}">{{ $shipment->direction }}</span>
                            </td>
                            <td class="nowrap awb-mono">{{ $shipment->awbFormatted() ?? '—' }}</td>
                            <td>{{ $shipment->customer->name }}</td>
                            <td class="nowrap">{{ $shipment->origin }} &rarr; {{ $shipment->destination }}</td>
                            <td><x-status-badge :status="$shipment->current_status" /></td>
                            <td class="num nowrap">{{ $shipment->pieces }} / {{ number_format((float) $shipment->gross_weight_kg, 1) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">No shipments yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($documentGaps->isNotEmpty())
            <div class="card">
                <div class="card-head"><h2>Missing paperwork</h2></div>
                <div class="table-scroll">
                    <table class="data">
                        <thead>
                        <tr><th>Reference</th><th>Customer</th><th>Not on file</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($documentGaps as $shipment)
                            <tr>
                                <td class="nowrap"><a href="{{ route('shipments.show', $shipment) }}">{{ $shipment->reference }}</a></td>
                                <td>{{ $shipment->customer->name }}</td>
                                <td>
                                    <div class="tag-list">
                                        @foreach ($shipment->missingDocumentTypes() as $type)
                                            <span class="badge amber">{{ $type->label() }}</span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div>
        <div class="card">
            <div class="card-head"><h3>By status</h3></div>
            <div class="card-body">
                @forelse ($statusBreakdown as $label => $total)
                    <div style="display:flex;justify-content:space-between;gap:1rem;padding:.3rem 0;border-bottom:1px solid var(--line-soft)">
                        <span>{{ $label }}</span>
                        <strong>{{ number_format($total) }}</strong>
                    </div>
                @empty
                    <p class="muted">Nothing to show yet.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Billing</h3></div>
            <div class="card-body">
                <div class="stat" style="border:0;box-shadow:none;padding:0">
                    <div class="label">Shipments with no charges posted</div>
                    <div class="value">{{ number_format($unbilled) }}</div>
                    <div class="hint">Excludes drafts and cancelled jobs</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Quick AWB lookup</h3></div>
            <div class="card-body">
                <form method="GET" action="{{ route('tracking.show') }}">
                    <div class="field">
                        <input type="text" name="awb" placeholder="020-1234567 5" inputmode="numeric">
                    </div>
                    <div class="form-actions" style="margin-top:.6rem">
                        <button class="btn primary" type="submit">Track</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
