@extends('layouts.app')

@section('title', $customer->name)

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $customer->name }}
            @unless ($customer->is_active)<span class="badge grey">inactive</span>@endunless
        </h1>
        <p>{{ $customer->code }}@if ($customer->contact_person) <span class="sep">|</span> {{ $customer->contact_person }}@endif</p>
    </div>
    <div class="spacer">
        <a class="btn" href="{{ route('customers.edit', $customer) }}">Edit</a>
        <a class="btn accent" href="{{ route('shipments.create', ['direction' => 'export']) }}">New shipment</a>
        @can('administer')
            <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline-form"
                  onsubmit="return confirm('Delete {{ $customer->name }}?')">
                @csrf @method('DELETE')
                <button class="btn danger" type="submit">Delete</button>
            </form>
        @endcan
    </div>
</div>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head">
                <h2>Shipments</h2>
                <div class="spacer">
                    <a class="btn sm" href="{{ route('shipments.index', ['customer_id' => $customer->id]) }}">All shipments</a>
                </div>
            </div>
            <div class="table-scroll">
                <table class="data">
                    <thead>
                    <tr><th>Reference</th><th>AWB</th><th>Route</th><th>Status</th><th class="num">Chg kg</th><th class="num">Charges</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($shipments as $shipment)
                        <tr>
                            <td class="nowrap">
                                <a href="{{ route('shipments.show', $shipment) }}">{{ $shipment->reference }}</a><br>
                                <span class="pill-dir {{ $shipment->direction }}">{{ $shipment->direction }}</span>
                            </td>
                            <td class="nowrap awb-mono">{{ $shipment->awbFormatted() ?? '—' }}</td>
                            <td class="nowrap">{{ $shipment->origin }} &rarr; {{ $shipment->destination }}</td>
                            <td><x-status-badge :status="$shipment->current_status" /></td>
                            <td class="num">{{ number_format((float) $shipment->chargeable_weight_kg, 2) }}</td>
                            <td class="num nowrap">{{ $shipment->currency }} {{ number_format((float) $shipment->charges_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">No shipments for this customer yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-paginator :items="$shipments" />
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-head"><h3>Details</h3></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Email</dt><dd>{{ $customer->email ?? '—' }}</dd>
                    <dt>Phone</dt><dd>{{ $customer->phone ?? '—' }}</dd>
                    <dt>Address</dt>
                    <dd>
                        @forelse ($customer->addressLines() as $line)
                            {{ $line }}@if (! $loop->last)<br>@endif
                        @empty
                            —
                        @endforelse
                    </dd>
                    <dt>Tax reference</dt><dd>{{ $customer->tax_reference ?? '—' }}</dd>
                    <dt>Currency</dt><dd>{{ $customer->default_currency }}</dd>
                    <dt>Credit</dt>
                    <dd>{{ $customer->credit_days }} days
                        @if ((float) $customer->credit_limit > 0)
                            <span class="sep">|</span> limit {{ number_format((float) $customer->credit_limit, 2) }}
                        @endif
                    </dd>
                </dl>

                @if ($customer->notes)
                    <p class="small muted" style="margin-top:1rem"><strong>Notes:</strong> {{ $customer->notes }}</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Customer logins</h3></div>
            <div class="card-body">
                @forelse ($customer->users as $user)
                    <div style="padding:.3rem 0;border-bottom:1px solid var(--line-soft)">
                        <strong>{{ $user->name }}</strong>
                        @unless ($user->is_active)<span class="badge grey">disabled</span>@endunless
                        <br><span class="small muted">{{ $user->email }}</span>
                    </div>
                @empty
                    <p class="muted small">No login has been created for this customer.
                        @can('administer')
                            <a href="{{ route('users.create') }}">Create one</a>.
                        @endcan
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
