@extends('layouts.app')

@section('title', $airline->exists ? 'Edit '.$airline->name : 'Add airline')

@section('content')
@php($editing = $airline->exists)

<div class="page-head">
    <div><h1>{{ $editing ? 'Edit '.$airline->name : 'Add airline' }}</h1></div>
    <div class="spacer"><a class="btn" href="{{ route('airlines.index') }}">Cancel</a></div>
</div>

<form method="POST" action="{{ $editing ? route('airlines.update', $airline) : route('airlines.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card">
        <div class="card-body">
            <div class="form-grid">
                <x-field name="name" label="Airline name">
                    <input id="name" type="text" name="name" value="{{ old('name', $airline->name) }}" required>
                </x-field>

                <x-field name="iata_code" label="IATA code" help="Two characters, e.g. LH, EK, AI.">
                    <input id="iata_code" type="text" name="iata_code" maxlength="2" style="text-transform:uppercase"
                           value="{{ old('iata_code', $airline->iata_code) }}" required>
                </x-field>

                <x-field name="icao_code" label="ICAO code" help="Optional, three letters.">
                    <input id="icao_code" type="text" name="icao_code" maxlength="3" style="text-transform:uppercase"
                           value="{{ old('icao_code', $airline->icao_code) }}">
                </x-field>

                <x-field name="awb_prefix" label="AWB accounting prefix"
                         help="The three digits that start every AWB for this carrier, e.g. 020 for Lufthansa.">
                    <input id="awb_prefix" type="text" name="awb_prefix" maxlength="3" inputmode="numeric"
                           value="{{ old('awb_prefix', $airline->awb_prefix) }}" required>
                </x-field>

                <x-field name="tracking_driver" label="Tracking source"
                         help="Where this airline's status comes from. Internal means our own operations records.">
                    <select id="tracking_driver" name="tracking_driver">
                        @foreach ($drivers as $key => $label)
                            <option value="{{ $key }}" @selected(old('tracking_driver', $airline->tracking_driver) === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <div class="field">
                    <label class="check" style="margin-top:1.3rem">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $airline->is_active ?? true))>
                        <span>Active</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn primary" type="submit">{{ $editing ? 'Save changes' : 'Add airline' }}</button>
        <a class="btn" href="{{ route('airlines.index') }}">Cancel</a>
    </div>
</form>
@endsection
