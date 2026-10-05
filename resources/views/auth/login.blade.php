@extends('layouts.public')

@section('title', 'Sign in')

@section('content')
<div class="wrap" style="max-width:420px">
    <x-alerts />

    <div class="card">
        <div class="card-head"><h2>Sign in</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-grid" style="grid-template-columns:1fr">
                    <x-field name="email" label="Email address">
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               required autofocus autocomplete="username"
                               aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    </x-field>

                    <x-field name="password" label="Password">
                        <input id="password" type="password" name="password" required autocomplete="current-password">
                    </x-field>

                    <label class="check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>Keep me signed in on this device</span>
                    </label>
                </div>

                <div class="form-actions" style="margin-top:1rem">
                    <button class="btn primary" type="submit">Sign in</button>
                    <a class="btn link" href="{{ route('tracking') }}">Track an AWB instead</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
