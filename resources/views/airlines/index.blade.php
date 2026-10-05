@extends('layouts.app')

@section('title', 'Airlines')

@section('content')
<div class="page-head">
    <div>
        <h1>Airlines</h1>
        <p>Each airline points at the tracking driver that answers for its AWB prefix.</p>
    </div>
    <div class="spacer"><a class="btn accent" href="{{ route('airlines.create') }}">Add airline</a></div>
</div>

<div class="alert info">
    Adding a new airline API is one class plus one block in <code>config/tracking.php</code>. Once it is there it shows
    up in the driver list below, and this screen is where you point an airline at it. Nothing else in the system changes.
</div>

<div class="card">
    <div class="table-scroll">
        <table class="data">
            <thead>
            <tr>
                <th>IATA</th><th>Name</th><th>AWB prefix</th><th>Tracking source</th>
                <th class="num">AWB stock</th><th class="num">Shipments</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($airlines as $airline)
                <tr>
                    <td class="mono"><strong>{{ $airline->iata_code }}</strong>
                        @if ($airline->icao_code)<br><span class="small muted">{{ $airline->icao_code }}</span>@endif
                    </td>
                    <td>
                        {{ $airline->name }}
                        @unless ($airline->is_active)<span class="badge grey">inactive</span>@endunless
                    </td>
                    <td class="mono">{{ $airline->awb_prefix }}</td>
                    <td>
                        <span class="badge {{ $airline->tracking_driver === 'internal' ? 'grey' : 'sky' }}">
                            {{ $drivers[$airline->tracking_driver] ?? $airline->tracking_driver }}
                        </span>
                    </td>
                    <td class="num">
                        {{ number_format($airline->available_awbs_count) }} free
                        <br><span class="small muted">of {{ number_format($airline->awbs_count) }}</span>
                    </td>
                    <td class="num">{{ number_format($airline->shipments_count) }}</td>
                    <td class="right nowrap">
                        <a class="btn link sm" href="{{ route('airlines.edit', $airline) }}">edit</a>
                        <form method="POST" action="{{ route('airlines.destroy', $airline) }}" class="inline-form"
                              onsubmit="return confirm('Remove {{ $airline->name }}?')">
                            @csrf @method('DELETE')
                            <button class="btn link sm" type="submit">remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No airlines yet. Add one to start issuing AWB numbers.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>Tracking drivers registered in this build</h3></div>
    <div class="card-body">
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Key</th><th>Driver</th></tr></thead>
                <tbody>
                @foreach ($drivers as $key => $name)
                    <tr><td class="mono">{{ $key }}</td><td>{{ $name }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
