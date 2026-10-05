@extends('layouts.public')

@section('title', $result && $result->found ? 'AWB '.$result->awb->formatted() : 'Track your air cargo')

@section('content')
<section class="hero">
    <div class="hero-inner">
        <h1>Track your air cargo</h1>
        <p>Enter the air waybill number from your documents. You will see the latest status and the full movement history.</p>

        <form class="track-form" method="GET" action="{{ route('tracking.show') }}">
            <input type="text" name="awb" value="{{ $raw }}" inputmode="numeric"
                   placeholder="e.g. 020-1234567 5" aria-label="Air waybill number" autofocus>
            <button class="btn accent" type="submit">Track</button>
        </form>
        <p class="hint">11 digits: a 3 digit airline prefix followed by the 8 digit serial.</p>
    </div>
</section>

<div class="public-wrap">
    @if ($error)
        <div class="card">
            <div class="card-body">
                <div class="alert err" style="margin:0">{{ $error }}</div>
            </div>
        </div>
    @elseif ($result && ! $result->found)
        <div class="card">
            <div class="card-body">
                <div class="alert warn" style="margin:0">
                    <strong>AWB {{ $result->awb->formatted() }}</strong><br>
                    {{ $result->message }}
                </div>
            </div>
        </div>
    @elseif ($result)
        @php($latest = $result->latest())
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="awb-mono">{{ $result->awb->formatted() }}</h2>
                    <span class="small muted">
                        @if ($result->origin && $result->destination)
                            {{ $result->origin }} &rarr; {{ $result->destination }}
                        @endif
                        @if ($shipment)
                            <span class="sep">|</span> Our reference {{ $shipment->reference }}
                        @endif
                    </span>
                </div>
                <div class="spacer">
                    <x-status-badge :status="$latest->status" />
                </div>
            </div>

            <div class="card-body">
                <div class="rail">
                    @foreach ($result->milestoneProgress() as $step)
                        <div class="seg {{ $step['reached'] ? 'on' : '' }}">
                            <div class="bar"></div>
                            {{ $step['status']->label() }}
                        </div>
                    @endforeach
                </div>

                <dl class="kv" style="margin-bottom:1.1rem">
                    <dt>Latest status</dt>
                    <dd><strong>{{ $latest->status->label() }}</strong>
                        @if ($latest->location) at {{ $latest->location }} @endif
                    </dd>
                    <dt>Last updated</dt>
                    <dd>{{ $latest->occurredAt->format('d M Y H:i') }}</dd>
                    @if ($result->pieces || $result->weightKg)
                        <dt>Pieces / weight</dt>
                        <dd>{{ $result->pieces ?? '-' }} pcs
                            @if ($result->weightKg) <span class="sep">|</span> {{ number_format($result->weightKg, 2) }} kg @endif
                        </dd>
                    @endif
                    @if ($latest->flightNumber)
                        <dt>Flight</dt>
                        <dd>{{ $latest->flightNumber }}</dd>
                    @endif
                </dl>

                <h3>Tracking history</h3>
                <ul class="timeline">
                    @foreach ($result->eventsDescending() as $i => $event)
                        <li class="{{ $i === 0 ? 'latest' : 'done' }}">
                            <div class="t-head">
                                <strong>{{ $event->status->label() }}</strong>
                                @if ($event->rawCode)
                                    <span class="badge outline">{{ $event->rawCode }}</span>
                                @endif
                            </div>
                            <div class="t-meta">
                                {{ $event->occurredAt->format('d M Y H:i') }}
                                @if ($event->location) <span class="sep">|</span> {{ $event->location }} @endif
                                @if ($event->flightNumber) <span class="sep">|</span> {{ $event->flightNumber }} @endif
                                @if ($event->pieces) <span class="sep">|</span> {{ $event->pieces }} pcs @endif
                            </div>
                            @if ($event->description)
                                <div class="t-note">{{ $event->description }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card-head" style="border-top:1px solid var(--line-soft);border-bottom:0">
                <span class="small muted">
                    Source: {{ $result->driver }}
                    <span class="sep">|</span>
                    @if ($result->fromCache)
                        last read {{ $result->fetchedAt->diffForHumans() }}
                    @else
                        read just now
                    @endif
                </span>
                <div class="spacer">
                    <a class="btn sm" href="{{ route('tracking.show', ['awb' => $result->awb->digits(), 'refresh' => 1]) }}">Refresh from carrier</a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
