@extends('layouts.app')

@section('title', 'Customers')

@section('content')
<div class="page-head">
    <div>
        <h1>Customers</h1>
        <p>{{ number_format($customers->total()) }} {{ Str::plural('customer', $customers->total()) }}</p>
    </div>
    <div class="spacer">
        <a class="btn accent" href="{{ route('customers.create') }}">New customer</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('customers.index') }}" class="filters">
            <div class="field grow">
                <label for="q">Search</label>
                <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Name, code, email, phone, city">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Any</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="field"><button class="btn primary" type="submit">Filter</button></div>
            <div class="field"><a class="btn" href="{{ route('customers.index') }}">Clear</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-scroll">
        <table class="data">
            <thead>
            <tr>
                <th>Code</th><th>Name</th><th>Contact</th><th>Location</th>
                <th class="num">Shipments</th><th>Terms</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td class="nowrap mono">{{ $customer->code }}</td>
                    <td>
                        <a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a>
                        @unless ($customer->is_active)
                            <span class="badge grey">inactive</span>
                        @endunless
                    </td>
                    <td class="small">
                        {{ $customer->contact_person ?: '—' }}
                        @if ($customer->email)<br><span class="muted">{{ $customer->email }}</span>@endif
                        @if ($customer->phone)<br><span class="muted">{{ $customer->phone }}</span>@endif
                    </td>
                    <td class="small">{{ trim(($customer->city ?: '').' '.($customer->country ?: '')) ?: '—' }}</td>
                    <td class="num">{{ number_format($customer->shipments_count) }}</td>
                    <td class="small nowrap">
                        {{ $customer->default_currency }}
                        @if ($customer->credit_days)<br><span class="muted">{{ $customer->credit_days }} days credit</span>@endif
                    </td>
                    <td class="right nowrap">
                        <a class="btn link sm" href="{{ route('customers.edit', $customer) }}">edit</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No customers match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-paginator :items="$customers" />
</div>
@endsection
