# Air Cargo Logistics Management System

A web based system for running air cargo imports and exports: customers, shipments,
AWB management, status history, documents, charges, a dashboard, role based access,
and public AWB tracking backed by a pluggable carrier API layer.

Built on Laravel 13 / PHP 8.3. Server rendered Blade with a hand written stylesheet,
so there is **no Node build step** — the app runs straight off a PHP host.

---

## What is in it

| Module | What it does |
| --- | --- |
| Customers | Full master record: contacts, address, tax reference, credit terms, default currency. Soft deleted, so history survives. |
| Airlines | IATA code, ICAO code and the 3 digit AWB accounting prefix. Each airline points at the tracking driver that answers for it. |
| AWB stock | Generate a block of numbers from the airline's stock sheet. The IATA **mod 7 check digit is computed**, never typed. Single numbers can be added by hand and are check digit validated. |
| Shipments | Imports and exports in one table with a direction flag. AWB, HAWB, routing, flight, pieces, gross/volume/chargeable weight, commodity, incoterm, prepaid/collect, DG flag, shipper and consignee. |
| Status | A **timeline of events**, not a single field. Each entry carries status, airport, flight, pieces, weight, time, source and whether the customer may see it. |
| Documents | Per shipment uploads, typed (AWB copy, invoice, packing list, DGD, bill of entry, delivery order, POD and more), with size, SHA-256 checksum, uploader, and a customer-visible flag. |
| Charges | Flat, per kg, per piece and per AWB lines against a charge type catalogue, receivable or payable, with a shipment total that follows the chargeable weight. |
| Dashboard | Open jobs, in transit, arriving today, delivered this month, export/import split, status breakdown, missing paperwork and unbilled shipments. Scoped per role. |
| Users and access | Administrator, operations staff and customer. A customer login is tied to one customer record and sees only that customer's cargo. |
| Public tracking | `/track?awb=...` — no login. Latest status, milestone rail and full history. Also available as JSON at `/api/track/{awb}`. |

---

## The AWB number

An air waybill is `PPP SSSSSSS C`:

* `PPP` — the airline's 3 digit IATA accounting prefix (020 Lufthansa, 176 Emirates, …)
* `SSSSSSS` — 7 running digits
* `C` — the check digit, which is `SSSSSSS mod 7`

`App\Support\AwbNumber` is the single place that knows this. It parses anything a
human types (`020-4471182 2`, `02044711822`, `020 4471182 2`), rejects a wrong check
digit with a message naming the digit it expected, and generates stock blocks with
the check digit filled in. A transposed digit is therefore caught at the form rather
than by the airline.

---

## Tracking: adding another airline or API

This is the part built for "scalable for adding more airlines/APIs later".

Every tracking source is a small class behind `App\Services\Tracking\Contracts\TrackingDriver`,
registered in `config/tracking.php`. `TrackingManager` resolves one per AWB:

1. the **airline row** for that prefix, if it names a driver (set in Admin → Airlines)
2. otherwise any configured driver whose `prefixes` list covers the AWB
3. otherwise the default driver

Shipped drivers:

* **`internal`** — serves tracking from our own `shipment_status_events`. For cargo this
  office handles itself, no API is involved and the customer sees what operations typed.
* **`demo`** — a deterministic fake carrier feed so the whole flow can be demonstrated
  and tested before any credentials exist. Turn it off with `TRACKING_DEMO=false`.
* **`GenericHttpDriver`** — a complete integration expressed as configuration.

### Adding a provider with no code at all

Most air cargo APIs are "call a URL with the AWB, get JSON back with a list of
movements". For those, add a block to `config/tracking.php`:

```php
'skycargo' => [
    'via'            => GenericHttpDriver::class,
    'name'           => 'Example SkyCargo',
    'prefixes'       => ['176'],                 // omit entirely for an aggregator
    'endpoint'       => env('TRACKING_SKY_URL'),
    'method'         => 'GET',
    'params'         => ['awbNumber' => '{awb}'],
    'api_key'        => env('TRACKING_SKY_KEY'),
    'api_key_header' => 'X-API-Key',
    'map' => [
        'origin'      => 'shipment.origin',      // dotted paths into their JSON
        'destination' => 'shipment.destination',
        'events'      => 'shipment.events',
        'event_status'      => 'code',
        'event_time'        => 'timestamp',
        'event_location'    => 'airport',
        'event_flight'      => 'flight',
        'event_description' => 'text',
    ],
    'status_map' => [                            // their codes to our milestones
        'RCS' => 'received', 'DEP' => 'departed',
        'ARR' => 'arrived',  'DLV' => 'delivered',
    ],
],
```

Then open **Admin → Airlines**, edit the carrier, and pick the new source. Nothing
else in the application changes.

Placeholders available in `endpoint` and `params`: `{awb}`, `{prefix}`, `{serial}`,
`{running_serial}`, `{awb_formatted}`.

### Adding a provider that needs code

Subclass `GenericHttpDriver` and override `request()` (for SOAP, XML, two step auth)
or `parse()` only, then point the config block's `via` at your class. Or implement
`TrackingDriver` from scratch for something genuinely unusual.

### Resilience

* Answers are cached in `tracking_snapshots` for `TRACKING_CACHE_TTL` minutes, so a
  refresh is one row read.
* If a provider is down or 500s, the **last known history is still shown**, labelled
  with when it was read, instead of an error page.
* A provider code that maps to nothing is dropped rather than displayed as a bogus
  milestone.
* Every lookup is logged in `tracking_lookups` with driver, duration, cache hit and
  any error.
* Staff can pull carrier events into our own history with **Pull carrier tracking** on
  the shipment page. It de-duplicates on status plus minute, so running it twice does
  not double up.

---

## Roles

| | Admin | Operations staff | Customer |
| --- | --- | --- | --- |
| Dashboard, shipment list | all | all | own customer only |
| Create / edit shipments, post status, upload documents, post charges | yes | yes | no |
| Customers | yes | yes | no |
| Airlines, AWB stock, users | yes | no | no |
| Delete a shipment | yes | no | no |

A customer login with no customer record attached sees **nothing**, not everything.
Documents and status entries can each be marked internal, and a customer cannot
download or read those. Document and charge routes also verify the record belongs to
the shipment in the URL, so an id from another shipment 404s.

---

## Running it locally

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # or point DB_* at MySQL
php artisan migrate --seed
php artisan serve
```

Demo logins created by the seeder:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | admin@aircargo.test | password |
| Operations | ops@aircargo.test | password |
| Customer | customer@aircargo.test | password |

The seeder also creates 10 airlines with real AWB prefixes, 12 charge types, 4
customers, 5 shipments with AWBs, status history and charges, and 50 free AWB
numbers. On `APP_ENV=production` it seeds **only** the admin, airlines and charge
types — no demo data.

### MySQL instead of SQLite

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=aircargo
DB_USERNAME=...
DB_PASSWORD=...
```

The schema is portable; nothing SQLite specific is used.

---

## Configuration

`config/aircargo.php`, driven by `.env`:

```
AIRCARGO_COMPANY="Skyline Air Cargo Services"   # shown in the header and footer
AIRCARGO_COMPANY_SHORT="Skyline Cargo"
AIRCARGO_CURRENCY=USD                            # default charge currency
AIRCARGO_CURRENCIES=USD,EUR,AED,INR,GBP,SGD      # currency picker
AIRCARGO_DOC_DISK=local                          # local, public, s3 …
AIRCARGO_DOC_MAX_KB=10240

TRACKING_DEFAULT=internal
TRACKING_DEMO=true                               # set false in production
TRACKING_CACHE_TTL=20
```

---

## Tests

```bash
php artisan test
```

45 tests covering the AWB check digit, shipment creation and AWB assignment,
chargeable weight derivation, status ordering, charge pricing and repricing, document
upload and download, role scoping, driver resolution, provider response mapping,
caching, and the stale-data fallback when a provider is down.
