@extends('layouts.app')

@section('title', $customer->exists ? 'Edit '.$customer->name : 'New customer')

@section('content')
@php($editing = $customer->exists)

<div class="page-head">
    <div><h1>{{ $editing ? 'Edit '.$customer->name : 'New customer' }}</h1></div>
    <div class="spacer">
        <a class="btn" href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}">Cancel</a>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card">
        <div class="card-head"><h2>Identity</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <x-field name="code" label="Customer code" help="Left blank, the next code in the series is used.">
                    <input id="code" type="text" name="code" value="{{ old('code', $customer->code) }}">
                </x-field>

                <x-field name="name" label="Company name">
                    <input id="name" type="text" name="name" value="{{ old('name', $customer->name) }}" required>
                </x-field>

                <x-field name="contact_person" label="Contact person">
                    <input id="contact_person" type="text" name="contact_person" value="{{ old('contact_person', $customer->contact_person) }}">
                </x-field>

                <x-field name="email" label="Email">
                    <input id="email" type="email" name="email" value="{{ old('email', $customer->email) }}">
                </x-field>

                <x-field name="phone" label="Phone">
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $customer->phone) }}">
                </x-field>

                <x-field name="tax_reference" label="Tax reference" help="GST, VAT or IEC number.">
                    <input id="tax_reference" type="text" name="tax_reference" value="{{ old('tax_reference', $customer->tax_reference) }}">
                </x-field>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Address</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <x-field name="address_line1" label="Address line 1" class="field span-2">
                    <input id="address_line1" type="text" name="address_line1" value="{{ old('address_line1', $customer->address_line1) }}">
                </x-field>

                <x-field name="address_line2" label="Address line 2" class="field span-2">
                    <input id="address_line2" type="text" name="address_line2" value="{{ old('address_line2', $customer->address_line2) }}">
                </x-field>

                <x-field name="city" label="City">
                    <input id="city" type="text" name="city" value="{{ old('city', $customer->city) }}">
                </x-field>

                <x-field name="state" label="State or region">
                    <input id="state" type="text" name="state" value="{{ old('state', $customer->state) }}">
                </x-field>

                <x-field name="postcode" label="Postcode">
                    <input id="postcode" type="text" name="postcode" value="{{ old('postcode', $customer->postcode) }}">
                </x-field>

                <x-field name="country" label="Country code" help="Two letters, e.g. AE, IN, DE.">
                    <input id="country" type="text" name="country" maxlength="2" style="text-transform:uppercase"
                           value="{{ old('country', $customer->country) }}">
                </x-field>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Commercial</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <x-field name="default_currency" label="Default currency">
                    <select id="default_currency" name="default_currency">
                        @foreach (config('aircargo.currencies') as $code)
                            <option value="{{ $code }}" @selected(old('default_currency', $customer->default_currency) === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="credit_days" label="Credit days">
                    <input id="credit_days" type="number" name="credit_days" min="0" max="365"
                           value="{{ old('credit_days', $customer->credit_days ?? 0) }}">
                </x-field>

                <x-field name="credit_limit" label="Credit limit">
                    <input id="credit_limit" type="number" name="credit_limit" min="0" step="0.01"
                           value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}">
                </x-field>

                <div class="field">
                    <label class="check" style="margin-top:1.3rem">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true))>
                        <span>Active</span>
                    </label>
                </div>

                <x-field name="notes" label="Internal notes" class="field span-2">
                    <textarea id="notes" name="notes">{{ old('notes', $customer->notes) }}</textarea>
                </x-field>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn primary" type="submit">{{ $editing ? 'Save changes' : 'Create customer' }}</button>
        <a class="btn" href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}">Cancel</a>
    </div>
</form>
@endsection
