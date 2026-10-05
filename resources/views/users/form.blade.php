@extends('layouts.app')

@section('title', $user->exists ? 'Edit '.$user->name : 'Add user')

@section('content')
@php($editing = $user->exists)

<div class="page-head">
    <div><h1>{{ $editing ? 'Edit '.$user->name : 'Add user' }}</h1></div>
    <div class="spacer"><a class="btn" href="{{ route('users.index') }}">Cancel</a></div>
</div>

<form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card">
        <div class="card-body">
            <div class="form-grid">
                <x-field name="name" label="Full name">
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                </x-field>

                <x-field name="email" label="Email address">
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                </x-field>

                <x-field name="phone" label="Phone">
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}">
                </x-field>

                <x-field name="role" label="Role">
                    <select id="role" name="role">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="customer_id" label="Customer"
                         help="Required for a customer login. It is what limits them to their own shipments.">
                    <select id="customer_id" name="customer_id">
                        <option value="">Not scoped (staff and admins)</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id', $user->customer_id) === (string) $customer->id)>
                                {{ $customer->code }} — {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <div class="field">
                    <label class="check" style="margin-top:1.3rem">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
                        <span>Active — a disabled account cannot sign in</span>
                    </label>
                </div>

                <x-field name="password" label="{{ $editing ? 'New password' : 'Password' }}"
                         help="{{ $editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.' }}">
                    <input id="password" type="password" name="password" autocomplete="new-password" @required(! $editing)>
                </x-field>

                <x-field name="password_confirmation" label="Repeat password">
                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" @required(! $editing)>
                </x-field>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn primary" type="submit">{{ $editing ? 'Save changes' : 'Create user' }}</button>
        <a class="btn" href="{{ route('users.index') }}">Cancel</a>
    </div>
</form>
@endsection
