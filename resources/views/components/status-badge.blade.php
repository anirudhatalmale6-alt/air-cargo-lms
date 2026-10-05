@props(['status'])

@php($enum = $status instanceof \App\Enums\ShipmentStatus ? $status : \App\Enums\ShipmentStatus::tryFrom((string) $status))

@if ($enum)
    <span class="badge {{ $enum->tone() }}" title="{{ $enum->imp() ? 'IATA code '.$enum->imp() : $enum->label() }}">{{ $enum->label() }}</span>
@else
    <span class="badge grey">{{ $status }}</span>
@endif
