<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->get('status') === 'active'))
            ->withCount('shipments')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'q' => $request->string('q')->toString(),
            'status' => $request->get('status'),
        ]);
    }

    public function create(): View
    {
        return view('customers.form', [
            'customer' => new Customer(['code' => Customer::nextCode(), 'is_active' => true, 'default_currency' => config('aircargo.default_currency')]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $customer = Customer::create($data);

        return redirect()->route('customers.show', $customer)
            ->with('status', "Customer {$customer->name} created.");
    }

    public function show(Customer $customer): View
    {
        $customer->load(['users:id,name,email,customer_id,is_active']);

        return view('customers.show', [
            'customer' => $customer,
            'shipments' => $customer->shipments()
                ->with('airline:id,iata_code')
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)
            ->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->shipments()->exists()) {
            // Keep the history intact: deactivate rather than orphan shipments.
            $customer->update(['is_active' => false]);

            return redirect()->route('customers.index')
                ->with('status', "{$customer->name} has shipments on file, so it was deactivated instead of deleted.");
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('status', 'Customer deleted.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'code')->ignore($customer)],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'tax_reference' => ['nullable', 'string', 'max:40'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'default_currency' => ['required', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Nullable fields that were not submitted are absent, not null.
        foreach (['code', 'country', 'credit_days', 'credit_limit'] as $key) {
            $data[$key] ??= null;
        }

        $data['code'] = $data['code'] ?: Customer::nextCode();
        $data['country'] = $data['country'] ? strtoupper($data['country']) : null;
        $data['default_currency'] = strtoupper($data['default_currency']);
        $data['credit_days'] = $data['credit_days'] ?? 0;
        $data['credit_limit'] = $data['credit_limit'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
