<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\ShipmentStatus;
use App\Models\Airline;
use App\Models\ChargeType;
use App\Models\Customer;
use App\Models\Shipment;
use App\Rules\ValidAwb;
use App\Services\ShipmentService;
use App\Support\AwbNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ShipmentController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments) {}

    public function index(Request $request): View
    {
        $direction = in_array($request->get('direction'), ['import', 'export'], true)
            ? $request->get('direction')
            : null;

        $shipments = Shipment::query()
            ->visibleTo($request->user())
            ->with(['customer:id,name,code', 'airline:id,iata_code,name'])
            ->search($request->string('q')->toString())
            ->when($direction, fn ($q) => $q->where('direction', $direction))
            ->when($request->filled('status'), fn ($q) => $q->where('current_status', $request->get('status')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->get('customer_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('flight_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('flight_date', '<=', $request->date('to')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('shipments.index', [
            'shipments' => $shipments,
            'direction' => $direction,
            'filters' => $request->only(['q', 'status', 'customer_id', 'from', 'to']),
            'customers' => $request->user()->isStaff()
                ? Customer::active()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'statuses' => ShipmentStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Shipment::class);

        $direction = $request->get('direction') === 'import' ? 'import' : 'export';

        return view('shipments.form', [
            'shipment' => new Shipment([
                'direction' => $direction,
                'reference' => Shipment::nextReference($direction),
                'current_status' => ShipmentStatus::Draft,
                'pieces' => 1,
                'payment_term' => 'prepaid',
                'currency' => config('aircargo.default_currency'),
            ]),
        ] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Shipment::class);

        $data = $this->validated($request);
        $awbInput = $data['awb_input'] ?? null;
        unset($data['awb_input']);

        $data['created_by'] = $request->user()->id;
        $data['current_status'] = ShipmentStatus::Draft->value;

        $shipment = Shipment::create($data);

        if ($awbInput) {
            try {
                $this->shipments->assignAwb($shipment, $awbInput);
            } catch (RuntimeException $e) {
                return redirect()->route('shipments.edit', $shipment)
                    ->withErrors(['awb_input' => $e->getMessage()]);
            }
        }

        $this->shipments->recordStatus(
            shipment: $shipment,
            status: ShipmentStatus::Booked,
            occurredAt: now(),
            location: $shipment->origin,
            description: 'Shipment created and booked',
            user: $request->user(),
        );

        return redirect()->route('shipments.show', $shipment)
            ->with('status', "Shipment {$shipment->reference} created.");
    }

    public function show(Request $request, Shipment $shipment): View
    {
        Gate::authorize('view', $shipment);

        $shipment->load([
            'customer', 'airline', 'awb', 'createdBy:id,name',
            'statusEvents.recordedBy:id,name',
            'documents.uploadedBy:id,name',
            'charges.chargeType:id,code,name',
        ]);

        $isStaff = $request->user()->isStaff();

        return view('shipments.show', [
            'shipment' => $shipment,
            'events' => $isStaff
                ? $shipment->statusEvents
                : $shipment->statusEvents->where('visible_to_customer', true),
            'documents' => $isStaff
                ? $shipment->documents
                : $shipment->documents->where('visible_to_customer', true),
            'statuses' => ShipmentStatus::options(),
            'documentTypes' => DocumentType::options(),
            'chargeTypes' => ChargeType::active()->orderBy('name')->get(),
            'currencies' => config('aircargo.currencies'),
            'missingDocuments' => $shipment->missingDocumentTypes(),
        ]);
    }

    public function edit(Shipment $shipment): View
    {
        Gate::authorize('update', $shipment);

        return view('shipments.form', ['shipment' => $shipment] + $this->formData());
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('update', $shipment);

        $data = $this->validated($request, $shipment);
        $awbInput = $data['awb_input'] ?? null;
        unset($data['awb_input']);

        $shipment->update($data);

        $typed = $awbInput ? AwbNumber::tryParse($awbInput)?->digits() : null;

        if ($typed && $typed !== $shipment->awb_number) {
            try {
                $this->shipments->assignAwb($shipment, $awbInput);
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['awb_input' => $e->getMessage()]);
            }
        } elseif (! $awbInput && $shipment->awb_number) {
            $this->shipments->releaseAwb($shipment);
        }

        return redirect()->route('shipments.show', $shipment)
            ->with('status', 'Shipment updated.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        Gate::authorize('delete', $shipment);

        $reference = $shipment->reference;
        $this->shipments->releaseAwb($shipment);
        $shipment->delete();

        return redirect()->route('shipments.index')
            ->with('status', "Shipment {$reference} deleted. Its AWB was returned to stock.");
    }

    private function formData(): array
    {
        return [
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name', 'code', 'default_currency']),
            'airlines' => Airline::active()->orderBy('name')->get(),
            'currencies' => config('aircargo.currencies'),
            'incoterms' => ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'],
        ];
    }

    private function validated(Request $request, ?Shipment $shipment = null): array
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:30', Rule::unique('shipments', 'reference')->ignore($shipment)],
            'direction' => ['required', Rule::in(['import', 'export'])],
            'customer_id' => ['required', 'exists:customers,id'],
            'airline_id' => ['nullable', 'exists:airlines,id'],
            'awb_input' => ['nullable', 'string', 'max:20', new ValidAwb],
            'hawb_number' => ['nullable', 'string', 'max:30'],
            'origin' => ['required', 'string', 'size:3', 'alpha'],
            'destination' => ['required', 'string', 'size:3', 'alpha', 'different:origin'],
            'flight_number' => ['nullable', 'string', 'max:10'],
            'flight_date' => ['nullable', 'date'],
            'eta' => ['nullable', 'date', 'after_or_equal:flight_date'],
            'pieces' => ['required', 'integer', 'min:1', 'max:100000'],
            'gross_weight_kg' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'volume_cbm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'chargeable_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'commodity' => ['nullable', 'string', 'max:255'],
            'nature_of_goods' => ['nullable', 'string', 'max:255'],
            'incoterm' => ['nullable', Rule::in(['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'])],
            'payment_term' => ['required', Rule::in(['prepaid', 'collect'])],
            'is_dangerous_goods' => ['nullable', 'boolean'],
            'shipper_name' => ['nullable', 'string', 'max:255'],
            'consignee_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        // A nullable field that was not submitted is absent from $data, not null,
        // so every optional key is normalised before it is read.
        foreach ([
            'reference', 'airline_id', 'awb_input', 'hawb_number', 'flight_number', 'flight_date', 'eta',
            'volume_cbm', 'chargeable_weight_kg', 'commodity', 'nature_of_goods', 'incoterm',
            'shipper_name', 'consignee_name', 'remarks',
        ] as $key) {
            $data[$key] ??= null;
        }

        $data['reference'] = $data['reference'] ?: Shipment::nextReference($data['direction']);
        $data['origin'] = strtoupper($data['origin']);
        $data['destination'] = strtoupper($data['destination']);
        $data['currency'] = strtoupper($data['currency']);
        $data['flight_number'] = $data['flight_number'] ? strtoupper($data['flight_number']) : null;
        $data['is_dangerous_goods'] = $request->boolean('is_dangerous_goods');

        // Blank chargeable weight is the common case: derive it rather than store 0.
        if (($data['chargeable_weight_kg'] ?? null) === null || (float) $data['chargeable_weight_kg'] === 0.0) {
            $volumetric = ($data['volume_cbm'] ?? null) !== null
                ? (float) $data['volume_cbm'] * config('aircargo.volumetric_factor')
                : 0.0;

            $data['chargeable_weight_kg'] = round(max((float) $data['gross_weight_kg'], $volumetric), 2);
        }

        return $data;
    }
}
