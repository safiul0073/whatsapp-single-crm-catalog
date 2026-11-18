<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\ShippingDocumentRequest;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingDocument;
use App\Modules\Shipping\Services\ShippingDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends ShippingBaseController
{
    public function __construct(private ShippingDocumentService $documents) {}

    public function store(ShippingDocumentRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->documents->storeUpload($shipment, $request->validated('type'), $request->file('file'), $request->user());

        return back()->with('success', __('Document uploaded.'));
    }

    public function download(Request $request, ShippingDocument $document): BinaryFileResponse
    {
        $this->assertWorkspace($request, $document);
        abort_unless(Storage::disk(ShippingDocumentService::DISK)->exists($document->file_path), 404);

        return response()->download(Storage::disk(ShippingDocumentService::DISK)->path($document->file_path), $document->original_name);
    }

    public function destroy(Request $request, ShippingDocument $document): RedirectResponse
    {
        $this->assertWorkspace($request, $document);
        $this->documents->delete($document, $request->user());

        return back()->with('success', __('Document removed.'));
    }
}
