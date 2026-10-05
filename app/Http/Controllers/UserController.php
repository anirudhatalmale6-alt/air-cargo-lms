<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->with('customer:id,name')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'user' => new User(['role' => UserRole::Staff, 'is_active' => true]),
        ] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['password'] = $request->input('password');

        User::create($data);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user] + $this->formData());
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        // Never let the last active admin lock everyone out.
        if ($user->isAdmin() && ($data['role'] !== UserRole::Admin->value || ! $data['is_active'])) {
            $otherAdmins = User::where('role', UserRole::Admin->value)
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->count();

            if ($otherAdmins === 0) {
                return back()->withInput()->withErrors([
                    'role' => 'This is the only active administrator. Promote someone else first.',
                ]);
            }
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['role' => 'You cannot delete the account you are signed in with.']);
        }

        if ($user->isAdmin() && User::where('role', UserRole::Admin->value)->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['role' => 'That is the only active administrator.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }

    private function formData(): array
    {
        return [
            'roles' => UserRole::options(),
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name', 'code']),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'customer_id' => ['nullable', 'exists:customers,id', Rule::requiredIf($request->input('role') === UserRole::Customer->value)],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ], [
            'customer_id.required' => 'A customer login must be linked to a customer, otherwise it would see nothing.',
        ]);

        unset($data['password']);

        $data['customer_id'] ??= null;
        $data['phone'] ??= null;
        $data['is_active'] = $request->boolean('is_active');

        // Staff and admins are never scoped to one customer.
        if ($data['role'] !== UserRole::Customer->value) {
            $data['customer_id'] = null;
        }

        return $data;
    }
}
