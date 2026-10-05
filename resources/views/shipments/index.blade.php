@extends('layouts.app')

@section('title', $direction ? ucfirst($direction).'s' : 'Shipments')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $direction ? ucfirst($direction).' shipments' : 'All shipments' }}</h1>
        <p>{{ number_format($shipments->total()) }} {{ Str::plural('record', $shipments->total()) }}</p>
    </div>
    @can('create', App\Models\Shipment::class)
        <div class="spacer">
            <a class="btn accent" href="{{ route('shipments.create', ['direction' => $direction ?: 'export']) }}">
                New {{ $direction ?: 'export' }}
            </a>
        </div>
    @endcan
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('shipments.index') }}" class="filters">
            @if ($direction)
                <input type="hidden" name="direction" value="{{ $direction }}">
            @endif

            <div class="field grow">
                <label for="q">Search</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="AWB, reference, flight, shipper, consignee">
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Any</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($customers->isNotEmpty())
                <div class="field">
                    <label for="customer_id">Customer</label>
                    <select id="customer_id" name="customer_id">
                        <option value="">Any</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="field">
                <label for="from">Flight from</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>

            <div class="field">
                <label for="to">Flight to</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>

            <div class="field">
                <button class="btn primary" type="submit">Filter</button>
            </div>
            <div class="field">
                <a class="btn" href="{{ route('shipments.index', $direction ? ['direction' => $direction] : []) }}">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-scroll">
        <table class="data">
            <thead>
            <tr>
                <th>Reference</th>
                <th>AWB</th>
                <th>Customer</th>
                <th>Route</th>
                <th>Flight</th>
                <th>Status</th>
                <th class="num">Pcs</th>
                <th class="num">Gross kg</th>
                <th class="num">Chg kg</th>
                <th class="num">Charges</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($shipments as $shipment)
                <tr>
                    <td class="nowrap">
                        <a href="{{ route('shipments.show', $shipment) }}">{{ $shipment->reference }}</a><br>
                        <span class="pill-dir {{ $shipment->direction }}">{{ $shipment->direction }}</span>
                    </td>
                    <td class="nowrap awb-mono">
                        {{ $shipment->awbFormatted() ?? '—' }}
                        @if ($shipment->hawb_number)
                            <br><span class="small muted">HAWB {{ $shipment->hawb_number }}</span>
                        @endif
                    </td>
                    <td>{{ $shipment->customer->name }}</td>
                    <td class="nowrap">{{ $shipment->origin }} &rarr; {{ $shipment->destination }}</td>
                    <td class="nowrap">
                        {{ $shipment->flight_number ?? '—' }}
                        @if ($shipment->flight_date)
                            <br><span class="small muted">{{ $shipment->flight_date->format('d M Y') }}</span>
                        @endif
                    </td>
                    <td><x-status-badge :status="$shipment->current_status" /></td>
                    <td class="num">{{ number_format($shipment->pieces) }}</td>
                    <td class="num">{{ number_format((float) $shipment->gross_weight_kg, 2) }}</td>
                    <td class="num">{{ number_format((float) $shipment->chargeable_weight_kg, 2) }}</td>
                    <td class="num nowrap">
                        @if ((float) $shipment->charges_total > 0)
                            {{ $shipment->currency }} {{ number_format((float) $shipment->charges_total, 2) }}
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No shipments match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-paginator :items="$shipments" />
</div>
@endsection
