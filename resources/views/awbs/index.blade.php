@extends('layouts.app')

@section('title', 'AWB stock')

@section('content')
<div class="page-head">
    <div>
        <h1>AWB stock</h1>
        <p>Check digits are computed here, so nobody has to work them out by hand.</p>
    </div>
</div>

<div class="stats">
    @foreach (['available' => 'Available', 'assigned' => 'Assigned', 'used' => 'Used', 'void' => 'Void'] as $key => $label)
        <div class="stat {{ $key === 'available' ? 'good' : ($key === 'void' ? 'bad' : '') }}">
            <div class="label">{{ $label }}</div>
            <div class="value">{{ number_format($summary[$key] ?? 0) }}</div>
        </div>
    @endforeach
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h3>Generate a block</h3></div>
        <div class="card-body">
            <p class="small muted">
                Enter the first running serial from the airline's stock sheet. The 8th digit of each number is the
                check digit, calculated as the 7 running digits modulo 7.
            </p>
            <form method="POST" action="{{ route('awbs.generate') }}">
                @csrf
                <div class="form-grid">
                    <x-field name="airline_id" label="Airline">
                        <select id="airline_id" name="airline_id" required>
                            <option value="">Select</option>
                            @foreach ($airlines as $airline)
                                <option value="{{ $airline->id }}" @selected((string) ($filters['airline_id'] ?? '') === (string) $airline->id)>
                                    {{ $airline->iata_code }} {{ $airline->name }} ({{ $airline->awb_prefix }})
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field name="from_serial" label="First running serial" help="Up to 7 digits, e.g. 1234567.">
                        <input id="from_serial" type="text" name="from_serial" inputmode="numeric" maxlength="7" required>
                    </x-field>

                    <x-field name="count" label="How many" help="Up to 1000 at a time.">
                        <input id="count" type="number" name="count" min="1" max="1000" value="25" required>
                    </x-field>
                </div>
                <div class="form-actions" style="margin-top:.8rem">
                    <button class="btn primary" type="submit">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Add one number</h3></div>
        <div class="card-body">
            <p class="small muted">
                For a single AWB read off a physical set. The check digit is verified, so a transposed digit is
                rejected here rather than by the airline.
            </p>
            <form method="POST" action="{{ route('awbs.store') }}">
                @csrf
                <div class="form-grid">
                    <x-field name="number" label="AWB number">
                        <input id="number" type="text" name="number" class="awb-mono"
                               placeholder="020-1234567 5" value="{{ old('number') }}" required>
                    </x-field>

                    <x-field name="notes" label="Note">
                        <input id="notes" type="text" name="notes" value="{{ old('notes') }}">
                    </x-field>
                </div>
                <div class="form-actions" style="margin-top:.8rem">
                    <button class="btn primary" type="submit">Add to stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('awbs.index') }}" class="filters">
            <div class="field grow">
                <label for="q">AWB number</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Any part of the number">
            </div>
            <div class="field">
                <label for="airline_filter">Airline</label>
                <select id="airline_filter" name="airline_id">
                    <option value="">Any</option>
                    @foreach ($airlines as $airline)
                        <option value="{{ $airline->id }}" @selected((string) ($filters['airline_id'] ?? '') === (string) $airline->id)>
                            {{ $airline->iata_code }} {{ $airline->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Any</option>
                    @foreach (['available', 'assigned', 'used', 'void'] as $value)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ ucfirst($value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><button class="btn primary" type="submit">Filter</button></div>
            <div class="field"><a class="btn" href="{{ route('awbs.index') }}">Clear</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-scroll">
        <table class="data">
            <thead>
            <tr><th>AWB</th><th>Airline</th><th>Status</th><th>On shipment</th><th>Received</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($awbs as $awb)
                <tr>
                    <td class="awb-mono nowrap">{{ $awb->formatted() }}</td>
                    <td class="nowrap">{{ $awb->airline->iata_code }} {{ $awb->airline->name }}</td>
                    <td>
                        <span class="badge {{ ['available' => 'green', 'assigned' => 'blue', 'used' => 'grey', 'void' => 'red'][$awb->status] }}">
                            {{ ucfirst($awb->status) }}
                        </span>
                    </td>
                    <td class="nowrap">
                        @if ($awb->shipment)
                            <a href="{{ route('shipments.show', $awb->shipment) }}">{{ $awb->shipment->reference }}</a>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="small nowrap">{{ $awb->received_on?->format('d M Y') ?? '—' }}</td>
                    <td class="right nowrap">
                        @unless ($awb->shipment)
                            <form method="POST" action="{{ route('awbs.update', $awb) }}" class="inline-form">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $awb->status === 'void' ? 'available' : 'void' }}">
                                <button class="btn link sm" type="submit">{{ $awb->status === 'void' ? 'restore' : 'void' }}</button>
                            </form>
                            <form method="POST" action="{{ route('awbs.destroy', $awb) }}" class="inline-form"
                                  onsubmit="return confirm('Remove {{ $awb->formatted() }} from stock?')">
                                @csrf @method('DELETE')
                                <button class="btn link sm" type="submit">delete</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No AWB numbers match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-paginator :items="$awbs" />
</div>
@endsection
