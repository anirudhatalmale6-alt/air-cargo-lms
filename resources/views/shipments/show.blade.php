@extends('layouts.app')

@section('title', $shipment->reference)

@section('content')
@php($isStaff = auth()->user()->isStaff())

<div class="page-head">
    <div>
        <h1>{{ $shipment->reference }}
            <span class="pill-dir {{ $shipment->direction }}">{{ $shipment->direction }}</span>
        </h1>
        <p>
            {{ $shipment->customer->name }}
            <span class="sep">|</span>
            {{ $shipment->origin }} &rarr; {{ $shipment->destination }}
            @if ($shipment->awb_number)
                <span class="sep">|</span> AWB <span class="awb-mono">{{ $shipment->awbFormatted() }}</span>
            @endif
        </p>
    </div>
    <div class="spacer">
        <x-status-badge :status="$shipment->current_status" />
        @if ($shipment->awb_number)
            <a class="btn sm" href="{{ route('tracking.show', ['awb' => $shipment->awb_number]) }}">Customer view</a>
        @endif
        @can('update', $shipment)
            <a class="btn sm" href="{{ route('shipments.edit', $shipment) }}">Edit</a>
        @endcan
        @can('delete', $shipment)
            <form method="POST" action="{{ route('shipments.destroy', $shipment) }}" class="inline-form"
                  onsubmit="return confirm('Delete {{ $shipment->reference }}? The AWB goes back to stock.')">
                @csrf @method('DELETE')
                <button class="btn sm danger" type="submit">Delete</button>
            </form>
        @endcan
    </div>
</div>

@if ($shipment->is_dangerous_goods)
    <div class="alert warn">Dangerous goods shipment. A dangerous goods declaration must be on file before departure.</div>
@endif

@if ($isStaff && $missingDocuments !== [])
    <div class="alert info">
        Paperwork not yet on file:
        @foreach ($missingDocuments as $type){{ $type->label() }}@if (! $loop->last), @endif @endforeach
    </div>
@endif

<div class="grid grid-main">
    <div>
        {{-- Shipment detail --}}
        <div class="card">
            <div class="card-head"><h2>Shipment</h2></div>
            <div class="card-body">
                <div class="grid grid-2">
                    <dl class="kv">
                        <dt>Customer</dt>
                        <dd>@if ($isStaff)<a href="{{ route('customers.show', $shipment->customer) }}">{{ $shipment->customer->name }}</a>@else{{ $shipment->customer->name }}@endif</dd>
                        <dt>Airline</dt>
                        <dd>{{ $shipment->airline?->label() ?? '—' }}</dd>
                        <dt>Master AWB</dt>
                        <dd class="awb-mono">{{ $shipment->awbFormatted() ?? '—' }}</dd>
                        <dt>House AWB</dt>
                        <dd>{{ $shipment->hawb_number ?? '—' }}</dd>
                        <dt>Flight</dt>
                        <dd>{{ $shipment->flight_number ?? '—' }}
                            @if ($shipment->flight_date) on {{ $shipment->flight_date->format('d M Y') }} @endif
                        </dd>
                        <dt>ETA</dt>
                        <dd>{{ $shipment->eta?->format('d M Y') ?? '—' }}</dd>
                    </dl>

                    <dl class="kv">
                        <dt>Pieces</dt>
                        <dd>{{ number_format($shipment->pieces) }}</dd>
                        <dt>Gross weight</dt>
                        <dd>{{ number_format((float) $shipment->gross_weight_kg, 2) }} kg</dd>
                        <dt>Volume</dt>
                        <dd>
                            @if ($shipment->volume_cbm)
                                {{ number_format((float) $shipment->volume_cbm, 3) }} cbm
                                <span class="small muted">({{ number_format($shipment->volumetricWeightKg(), 2) }} kg volumetric)</span>
                            @else — @endif
                        </dd>
                        <dt>Chargeable</dt>
                        <dd><strong>{{ number_format((float) $shipment->chargeable_weight_kg, 2) }} kg</strong></dd>
                        <dt>Commodity</dt>
                        <dd>{{ $shipment->commodity ?? '—' }}</dd>
                        <dt>Terms</dt>
                        <dd>{{ $shipment->incoterm ?? '—' }} <span class="sep">|</span> {{ ucfirst($shipment->payment_term) }}</dd>
                        <dt>Shipper</dt>
                        <dd>{{ $shipment->shipper_name ?? '—' }}</dd>
                        <dt>Consignee</dt>
                        <dd>{{ $shipment->consignee_name ?? '—' }}</dd>
                    </dl>
                </div>

                @if ($shipment->remarks)
                    <p class="small muted" style="margin:1rem 0 0"><strong>Remarks:</strong> {{ $shipment->remarks }}</p>
                @endif
            </div>
        </div>

        {{-- Status history --}}
        <div class="card">
            <div class="card-head">
                <h2>Status history</h2>
                @can('update', $shipment)
                    @if ($shipment->awb_number)
                        <div class="spacer">
                            <form method="POST" action="{{ route('shipments.tracking.sync', $shipment) }}" class="inline-form">
                                @csrf
                                <button class="btn sm" type="submit">Pull carrier tracking</button>
                            </form>
                        </div>
                    @endif
                @endcan
            </div>
            <div class="card-body">
                @if ($events->isEmpty())
                    <p class="muted">No movements recorded yet.</p>
                @else
                    <ul class="timeline">
                        @foreach ($events as $event)
                            <li class="{{ $loop->first ? 'latest' : 'done' }}">
                                <div class="t-head">
                                    <strong>{{ $event->status->label() }}</strong>
                                    @if ($event->status->imp())
                                        <span class="badge outline">{{ $event->status->imp() }}</span>
                                    @endif
                                    @if ($event->isFromApi())
                                        <span class="badge sky">carrier feed</span>
                                    @endif
                                    @if ($isStaff && ! $event->visible_to_customer)
                                        <span class="badge grey">internal only</span>
                                    @endif
                                </div>
                                <div class="t-meta">
                                    {{ $event->occurred_at->format('d M Y H:i') }}
                                    @if ($event->location) <span class="sep">|</span> {{ $event->location }} @endif
                                    @if ($event->flight_number) <span class="sep">|</span> {{ $event->flight_number }} @endif
                                    @if ($event->pieces) <span class="sep">|</span> {{ $event->pieces }} pcs @endif
                                    @if ($event->recordedBy) <span class="sep">|</span> {{ $event->recordedBy->name }} @endif
                                    @can('update', $shipment)
                                        <form method="POST" action="{{ route('shipments.status.destroy', [$shipment, $event]) }}"
                                              class="inline-form" onsubmit="return confirm('Remove this status entry?')">
                                            @csrf @method('DELETE')
                                            <button class="btn link sm" type="submit">remove</button>
                                        </form>
                                    @endcan
                                </div>
                                @if ($event->description)
                                    <div class="t-note">{{ $event->description }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @can('update', $shipment)
                <div class="card-body" style="border-top:1px solid var(--line-soft);background:#fbfcfe">
                    <h3>Record a movement</h3>
                    <form method="POST" action="{{ route('shipments.status.store', $shipment) }}">
                        @csrf
                        <div class="form-grid">
                            <x-field name="status" label="Status">
                                <select id="status" name="status" required>
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-field>

                            <x-field name="occurred_at" label="When">
                                <input id="occurred_at" type="datetime-local" name="occurred_at"
                                       value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}" required>
                            </x-field>

                            <x-field name="location" label="Airport">
                                <input id="location" type="text" name="location" maxlength="3" style="text-transform:uppercase"
                                       value="{{ old('location', $shipment->origin) }}">
                            </x-field>

                            <x-field name="flight_number" label="Flight">
                                <input type="text" name="flight_number" maxlength="10" style="text-transform:uppercase"
                                       value="{{ old('flight_number', $shipment->flight_number) }}">
                            </x-field>

                            <x-field name="pieces" label="Pieces">
                                <input type="number" name="pieces" min="0" step="1" value="{{ old('pieces', $shipment->pieces) }}">
                            </x-field>

                            <x-field name="weight_kg" label="Weight (kg)">
                                <input type="number" name="weight_kg" min="0" step="0.01" value="{{ old('weight_kg', $shipment->gross_weight_kg) }}">
                            </x-field>

                            <x-field name="description" label="Note" class="field span-2">
                                <input type="text" name="description" value="{{ old('description') }}"
                                       placeholder="Anything the next person should know">
                            </x-field>

                            <div class="field span-2">
                                <label class="check">
                                    <input type="checkbox" name="visible_to_customer" value="1" checked>
                                    <span>Show this entry to the customer on the tracking page</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-actions" style="margin-top:.8rem">
                            <button class="btn primary" type="submit">Record status</button>
                        </div>
                    </form>
                </div>
            @endcan
        </div>

        {{-- Documents --}}
        <div class="card">
            <div class="card-head"><h2>Documents</h2>
                <span class="small muted spacer">{{ $documents->count() }} on file</span>
            </div>

            @if ($documents->isEmpty())
                <div class="card-body"><p class="muted">No documents uploaded yet.</p></div>
            @else
                <div class="table-scroll">
                    <table class="data">
                        <thead>
                        <tr><th>Type</th><th>File</th><th class="num">Size</th><th>Uploaded</th><th></th></tr>
                        </thead>
                        <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <td class="nowrap">
                                    {{ $document->type->label() }}
                                    @if ($isStaff && ! $document->visible_to_customer)
                                        <br><span class="badge grey">internal only</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('shipments.documents.download', [$shipment, $document]) }}">
                                        {{ $document->title ?: $document->original_name }}
                                    </a>
                                    @if ($document->title)
                                        <br><span class="small muted">{{ $document->original_name }}</span>
                                    @endif
                                </td>
                                <td class="num nowrap">{{ $document->humanSize() }}</td>
                                <td class="nowrap small">
                                    {{ $document->created_at->format('d M Y H:i') }}
                                    @if ($document->uploadedBy)<br><span class="muted">{{ $document->uploadedBy->name }}</span>@endif
                                </td>
                                <td class="nowrap right">
                                    @can('manageDocuments', $shipment)
                                        <form method="POST" action="{{ route('shipments.documents.destroy', [$shipment, $document]) }}"
                                              class="inline-form" onsubmit="return confirm('Delete this document?')">
                                            @csrf @method('DELETE')
                                            <button class="btn link sm" type="submit">delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @can('manageDocuments', $shipment)
                <div class="card-body" style="border-top:1px solid var(--line-soft);background:#fbfcfe">
                    <h3>Upload</h3>
                    <form method="POST" action="{{ route('shipments.documents.store', $shipment) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-grid">
                            <x-field name="type" label="Document type">
                                <select id="type" name="type" required>
                                    @foreach ($documentTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-field>

                            <x-field name="title" label="Label (optional)">
                                <input type="text" name="title" placeholder="e.g. Invoice 2026-114">
                            </x-field>

                            <x-field name="files" label="Files"
                                     help="{{ strtoupper(implode(', ', config('aircargo.documents.allowed'))) }}, up to {{ round(config('aircargo.documents.max_kb') / 1024) }} MB each.">
                                <input id="files" type="file" name="files[]" multiple required>
                            </x-field>

                            <div class="field">
                                <label class="check" style="margin-top:1.3rem">
                                    <input type="checkbox" name="visible_to_customer" value="1" checked>
                                    <span>Customer may download</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-actions" style="margin-top:.8rem">
                            <button class="btn primary" type="submit">Upload</button>
                        </div>
                    </form>
                </div>
            @endcan
        </div>
    </div>

    {{-- Sidebar: charges --}}
    <div>
        @can('viewCharges', $shipment)
            <div class="card">
                <div class="card-head">
                    <h3>Charges</h3>
                    @can('manageCharges', $shipment)
                        <div class="spacer">
                            <form method="POST" action="{{ route('shipments.charges.reprice', $shipment) }}" class="inline-form">
                                @csrf
                                <button class="btn sm" type="submit" title="Recalculate weight and piece based lines">Reprice</button>
                            </form>
                        </div>
                    @endcan
                </div>

                @if ($shipment->charges->isEmpty())
                    <div class="card-body"><p class="muted">No charges posted.</p></div>
                @else
                    <div class="table-scroll">
                        <table class="data">
                            <thead><tr><th>Charge</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($shipment->charges as $charge)
                                <tr>
                                    <td>
                                        {{ $charge->description }}
                                        <br><span class="small muted">{{ str_replace('_', ' ', $charge->basis) }}@if ($charge->side === 'payable') <span class="badge orange">payable</span>@endif</span>
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format((float) $charge->quantity, 3), '0'), '.') }}</td>
                                    <td class="num">{{ rtrim(rtrim(number_format((float) $charge->rate, 4), '0'), '.') }}</td>
                                    <td class="num nowrap">{{ $charge->currency }} {{ number_format((float) $charge->amount, 2) }}</td>
                                    <td class="right">
                                        @can('manageCharges', $shipment)
                                            <form method="POST" action="{{ route('shipments.charges.destroy', [$shipment, $charge]) }}" class="inline-form">
                                                @csrf @method('DELETE')
                                                <button class="btn link sm" type="submit">&times;</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="3"><strong>Total receivable</strong></td>
                                <td class="num nowrap"><strong>{{ $shipment->currency }} {{ number_format((float) $shipment->charges_total, 2) }}</strong></td>
                                <td></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                @endif

                @can('manageCharges', $shipment)
                    <div class="card-body" style="border-top:1px solid var(--line-soft);background:#fbfcfe">
                        <form method="POST" action="{{ route('shipments.charges.store', $shipment) }}">
                            @csrf
                            <div class="form-grid" style="grid-template-columns:1fr 1fr">
                                <x-field name="charge_type_id" label="Charge" class="field span-2">
                                    <select name="charge_type_id" id="charge_type_id">
                                        <option value="">Custom line</option>
                                        @foreach ($chargeTypes as $type)
                                            <option value="{{ $type->id }}" data-basis="{{ $type->basis }}" data-rate="{{ $type->default_rate }}">
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </x-field>

                                <x-field name="description" label="Description" class="field span-2">
                                    <input type="text" name="description" id="charge_description"
                                           placeholder="Taken from the charge type if blank">
                                </x-field>

                                <x-field name="basis" label="Basis">
                                    <select name="basis" id="charge_basis">
                                        <option value="flat">Flat</option>
                                        <option value="per_kg">Per kg</option>
                                        <option value="per_piece">Per piece</option>
                                        <option value="per_awb">Per AWB</option>
                                    </select>
                                </x-field>

                                <x-field name="rate" label="Rate">
                                    <input type="number" name="rate" id="charge_rate" step="0.0001" min="0" value="0" required>
                                </x-field>

                                <x-field name="quantity" label="Quantity" help="Ignored for per kg and per piece.">
                                    <input type="number" name="quantity" step="0.001" min="0" value="1">
                                </x-field>

                                <x-field name="currency" label="Currency">
                                    <select name="currency">
                                        @foreach ($currencies as $code)
                                            <option value="{{ $code }}" @selected($shipment->currency === $code)>{{ $code }}</option>
                                        @endforeach
                                    </select>
                                </x-field>

                                <x-field name="side" label="Side" class="field span-2">
                                    <select name="side">
                                        <option value="receivable">Receivable from customer</option>
                                        <option value="payable">Payable to carrier or vendor</option>
                                    </select>
                                </x-field>
                            </div>
                            <div class="form-actions" style="margin-top:.7rem">
                                <button class="btn primary" type="submit">Add charge</button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>
        @endcan

        <div class="card">
            <div class="card-head"><h3>Audit</h3></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Created</dt>
                    <dd>{{ $shipment->created_at->format('d M Y H:i') }}</dd>
                    @if ($shipment->createdBy)
                        <dt>By</dt><dd>{{ $shipment->createdBy->name }}</dd>
                    @endif
                    <dt>Last change</dt>
                    <dd>{{ $shipment->updated_at->format('d M Y H:i') }}</dd>
                    <dt>Status since</dt>
                    <dd>{{ $shipment->status_changed_at?->format('d M Y H:i') ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

@can('manageCharges', $shipment)
    <script>
        // Picking a charge type prefills basis and rate, which staff can still override.
        document.addEventListener('DOMContentLoaded', function () {
            var select = document.getElementById('charge_type_id');
            if (!select) return;

            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                var basis = option.getAttribute('data-basis');
                var rate = option.getAttribute('data-rate');

                if (basis) document.getElementById('charge_basis').value = basis;
                if (rate !== null && rate !== '') document.getElementById('charge_rate').value = parseFloat(rate);
                document.getElementById('charge_description').value = select.selectedIndex > 0 ? option.text : '';
            });
        });
    </script>
@endcan
@endsection
