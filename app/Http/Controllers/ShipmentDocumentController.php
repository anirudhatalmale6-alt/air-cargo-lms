<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipmentDocumentController extends Controller
{
    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('manageDocuments', $shipment);

        $allowed = config('aircargo.documents.allowed');

        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'file',
                'max:'.config('aircargo.documents.max_kb'),
                'mimes:'.implode(',', $allowed),
            ],
            'visible_to_customer' => ['nullable', 'boolean'],
        ], [
            'files.*.mimes' => 'Only these file types are accepted: '.implode(', ', $allowed).'.',
            'files.*.max' => 'Each file must be under '.round(config('aircargo.documents.max_kb') / 1024).' MB.',
        ]);

        $disk = config('aircargo.documents.disk');
        $stored = 0;

        foreach ($request->file('files') as $file) {
            $path = $file->store("shipments/{$shipment->id}", $disk);

            $shipment->documents()->create([
                'type' => $data['type'],
                'title' => $data['title'] ?? null,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size_bytes' => Storage::disk($disk)->size($path),
                // Hash the stored bytes so a later integrity check is possible.
                'checksum' => hash('sha256', Storage::disk($disk)->get($path)),
                // An unticked checkbox is simply absent, so absent must mean
                // staff-only rather than quietly exposing the file.
                'visible_to_customer' => $request->boolean('visible_to_customer'),
                'uploaded_by' => $request->user()->id,
            ]);

            $stored++;
        }

        return back()->with('status', $stored.' '.str('document')->plural($stored).' uploaded.');
    }

    public function download(Request $request, Shipment $shipment, ShipmentDocument $document): StreamedResponse
    {
        Gate::authorize('view', $shipment);
        abort_if($document->shipment_id !== $shipment->id, 404);

        // A customer must not reach a document staff marked internal.
        abort_if(! $request->user()->isStaff() && ! $document->visible_to_customer, 403);

        $disk = Storage::disk(config('aircargo.documents.disk'));

        abort_unless($disk->exists($document->path), 404, 'The stored file is missing.');

        return $disk->download($document->path, $document->original_name);
    }

    public function destroy(Shipment $shipment, ShipmentDocument $document): RedirectResponse
    {
        Gate::authorize('manageDocuments', $shipment);
        abort_if($document->shipment_id !== $shipment->id, 404);

        Storage::disk(config('aircargo.documents.disk'))->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document removed.');
    }
}
