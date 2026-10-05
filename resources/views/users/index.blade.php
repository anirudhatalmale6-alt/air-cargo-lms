@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="page-head">
    <div>
        <h1>Users and access</h1>
        <p>Administrators manage everything. Operations staff run shipments. Customers see only their own cargo.</p>
    </div>
    <div class="spacer"><a class="btn accent" href="{{ route('users.create') }}">Add user</a></div>
</div>

<div class="card">
    <div class="table-scroll">
        <table class="data">
            <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Scoped to</th><th>Last signed in</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>
                        {{ $user->name }}
                        @unless ($user->is_active)<span class="badge grey">disabled</span>@endunless
                        @if ($user->phone)<br><span class="small muted">{{ $user->phone }}</span>@endif
                    </td>
                    <td class="small">{{ $user->email }}</td>
                    <td>
                        <span class="badge {{ $user->role === \App\Enums\UserRole::Admin ? 'indigo' : ($user->role === \App\Enums\UserRole::Staff ? 'blue' : 'teal') }}">
                            {{ $user->role->label() }}
                        </span>
                    </td>
                    <td class="small">{{ $user->customer?->name ?? 'All customers' }}</td>
                    <td class="small nowrap">{{ $user->last_login_at?->format('d M Y H:i') ?? 'never' }}</td>
                    <td class="right nowrap">
                        <a class="btn link sm" href="{{ route('users.edit', $user) }}">edit</a>
                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline-form"
                              onsubmit="return confirm('Delete {{ $user->name }}?')">
                            @csrf @method('DELETE')
                            <button class="btn link sm" type="submit">delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No users.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-paginator :items="$users" />
</div>
@endsection
