@extends('layouts.app')

@section('title', $shipment->exists ? 'Edit '.$shipment->reference : 'New shipment')

@section('content')
@php($editing = $shipment->exists)

<div class="page-head">
    <div>
        <h1>{{ $editing ? 'Edit '.$shipment->reference : 'New '.$shipment->direction.' shipment' }}</h1>
        <p>{{ $editing ? 'Changes are saved against the same job number.' : 'The job number is reserved when you save.' }}</p>
    </div>
    <div class="spacer">
        <a class="btn" href="{{ $editing ? route('shipments.show', $shipment) : route('shipments.index') }}">Cancel</a>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('shipments.update', $shipment) : route('shipments.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card">
        <div class="card-head"><h2>Job</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <x-field name="reference" label="Reference" help="Leave as is unless you number jobs yourself.">
                    <input id="reference" type="text" name="reference" value="{{ old('reference', $shipment->reference) }}">
                </x-field>

                <x-field name="direction" label="Direction">
                    <select id="direction" name="direction">
                        <option value="export" @selected(old('direction', $shipment->direction) === 'export')>Export</option>
                        <option value="import" @selected(old('direction', $shipment->direction) === 'import')>Import</option>
                    </select>
                </x-field>

                <x-field name="customer_id" label="Customer">
                    <select id="customer_id" name="customer_id" required>
                        <option value="">Select a customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id', $shipment->customer_id) === (string) $customer->id)>
                                {{ $customer->code }} — {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="currency" label="Currency">
                    <select id="currency" name="currency">
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}" @selected(old('currency', $shipment->currency) === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Air waybill and routing</h2>
            <span class="small muted spacer">The check digit is validated, so a mistyped AWB is caught here.</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <x-field name="awb_input" label="AWB number"
                         help="3 digit prefix + 8 digit serial. Dashes and spaces are fine.">
                    <input id="awb_input" type="text" name="awb_input" class="awb-mono"
                           value="{{ old('awb_input', $shipment->awb_number ? $shipment->awbFormatted() : '') }}"
                           placeholder="020-1234567 5"
                           aria-invalid="{{ $errors->has('awb_input') ? 'true' : 'false' }}">
                </x-field>

                <x-field name="airline_id" label="Airline"
                         help="Left blank, it is taken from the AWB prefix.">
                    <select id="airline_id" name="airline_id">
                        <option value="">From AWB prefix</option>
                        @foreach ($airlines as $airline)
                            <option value="{{ $airline->id }}" @selected((string) old('airline_id', $shipment->airline_id) === (string) $airline->id)>
                                {{ $airline->label() }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="hawb_number" label="House AWB">
                    <input id="hawb_number" type="text" name="hawb_number" value="{{ old('hawb_number', $shipment->hawb_number) }}">
                </x-field>

                <x-field name="origin" label="Origin airport" help="IATA 3 letter code.">
                    <input id="origin" type="text" name="origin" maxlength="3" style="text-transform:uppercase"
                           value="{{ old('origin', $shipment->origin) }}" placeholder="BOM" required>
                </x-field>

                <x-field name="destination" label="Destination airport">
                    <input id="destination" type="text" name="destination" maxlength="3" style="text-transform:uppercase"
                           value="{{ old('destination', $shipment->destination) }}" placeholder="FRA" required>
                </x-field>

                <x-field name="flight_number" label="Flight number">
                    <input id="flight_number" type="text" name="flight_number" maxlength="10" style="text-transform:uppercase"
                           value="{{ old('flight_number', $shipment->flight_number) }}" placeholder="LH761">
                </x-field>

                <x-field name="flight_date" label="Flight date">
                    <input id="flight_date" type="date" name="flight_date"
                           value="{{ old('flight_date', $shipment->flight_date?->toDateString()) }}">
                </x-field>

                <x-field name="eta" label="ETA">
                    <input id="eta" type="date" name="eta" value="{{ old('eta', $shipment->eta?->toDateString()) }}">
                </x-field>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Cargo</h2>
            <span class="small muted spacer">
                Chargeable weight left blank is derived: the greater of gross and volume &times; 166.67.
            </span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <x-field name="pieces" label="Pieces">
                    <input id="pieces" type="number" name="pieces" min="1" step="1"
                           value="{{ old('pieces', $shipment->pieces ?: 1) }}" required>
                </x-field>

                <x-field name="gross_weight_kg" label="Gross weight (kg)">
                    <input id="gross_weight_kg" type="number" name="gross_weight_kg" min="0" step="0.01"
                           value="{{ old('gross_weight_kg', $shipment->gross_weight_kg) }}" required>
                </x-field>

                <x-field name="volume_cbm" label="Volume (cbm)">
                    <input id="volume_cbm" type="number" name="volume_cbm" min="0" step="0.001"
                           value="{{ old('volume_cbm', $shipment->volume_cbm) }}">
                </x-field>

                <x-field name="chargeable_weight_kg" label="Chargeable weight (kg)">
                    <input id="chargeable_weight_kg" type="number" name="chargeable_weight_kg" min="0" step="0.01"
                           value="{{ old('chargeable_weight_kg', $shipment->chargeable_weight_kg) }}" placeholder="Auto">
                </x-field>

                <x-field name="commodity" label="Commodity">
                    <input id="commodity" type="text" name="commodity" value="{{ old('commodity', $shipment->commodity) }}">
                </x-field>

                <x-field name="nature_of_goods" label="Nature of goods">
                    <input id="nature_of_goods" type="text" name="nature_of_goods" value="{{ old('nature_of_goods', $shipment->nature_of_goods) }}">
                </x-field>

                <x-field name="incoterm" label="Incoterm">
                    <select id="incoterm" name="incoterm">
                        <option value="">—</option>
                        @foreach ($incoterms as $term)
                            <option value="{{ $term }}" @selected(old('incoterm', $shipment->incoterm) === $term)>{{ $term }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="payment_term" label="Freight payment">
                    <select id="payment_term" name="payment_term">
                        <option value="prepaid" @selected(old('payment_term', $shipment->payment_term) === 'prepaid')>Prepaid</option>
                        <option value="collect" @selected(old('payment_term', $shipment->payment_term) === 'collect')>Collect</option>
                    </select>
                </x-field>

                <x-field name="shipper_name" label="Shipper">
                    <input id="shipper_name" type="text" name="shipper_name" value="{{ old('shipper_name', $shipment->shipper_name) }}">
                </x-field>

                <x-field name="consignee_name" label="Consignee">
                    <input id="consignee_name" type="text" name="consignee_name" value="{{ old('consignee_name', $shipment->consignee_name) }}">
                </x-field>

                <div class="field span-2">
                    <label class="check">
                        <input type="checkbox" name="is_dangerous_goods" value="1"
                               @checked(old('is_dangerous_goods', $shipment->is_dangerous_goods))>
                        <span>Dangerous goods — a DGD must be uploaded before departure</span>
                    </label>
                </div>

                <x-field name="remarks" label="Remarks" class="field span-2">
                    <textarea id="remarks" name="remarks">{{ old('remarks', $shipment->remarks) }}</textarea>
                </x-field>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn primary" type="submit">{{ $editing ? 'Save changes' : 'Create shipment' }}</button>
        <a class="btn" href="{{ $editing ? route('shipments.show', $shipment) : route('shipments.index') }}">Cancel</a>
    </div>
</form>
@endsection
