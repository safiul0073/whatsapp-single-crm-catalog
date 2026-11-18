<?php

namespace App\Modules\Shipping\Services;

use App\Models\User;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Labels, commercial invoices and customs papers, kept on the private disk.
 */
class ShippingDocumentService
{
    public const DISK = 'local';

    public function __construct(private ShippingAuditService $audit) {}

    public function storeContent(Shipment $shipment, string $type, string $name, string $content, string $mime, ?User $user = null): ShippingDocument
    {
        $path = $this->directory($shipment).'/'.Str::random(12).'-'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
        Storage::disk(self::DISK)->put($path, $content);

        return ShippingDocument::query()->create([
            'workspace_id' => $shipment->workspace_id,
            'shipping_shipment_id' => $shipment->id,
            'type' => $type,
            'file_path' => $path,
            'original_name' => mb_substr($name, 0, 255),
            'mime' => $mime,
            'size' => strlen($content),
            'source' => 'api',
            'created_by' => $user?->id,
        ]);
    }

    public function storeUpload(Shipment $shipment, string $type, UploadedFile $file, User $user): ShippingDocument
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $document = ShippingDocument::query()->create([
            'workspace_id' => $shipment->workspace_id,
            'shipping_shipment_id' => $shipment->id,
            'type' => $type,
            'file_path' => $file->storeAs($this->directory($shipment), Str::random(12).'.'.$extension, self::DISK),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'source' => 'upload',
            'created_by' => $user->id,
        ]);
        $this->audit->record($shipment->workspace_id, 'document.uploaded', $document->typeLabel().' uploaded for '.$shipment->reference, $shipment, null, ['document' => $document->original_name], $user->id);

        return $document;
    }

    public function delete(ShippingDocument $document, User $user): void
    {
        Storage::disk(self::DISK)->delete($document->file_path);
        $document->delete();
        $this->audit->record($document->workspace_id, 'document.deleted', $document->typeLabel().' removed: '.$document->original_name, $document->shipment, ['document' => $document->original_name], null, $user->id);
    }

    private function directory(Shipment $shipment): string
    {
        return 'shipping-documents/'.$shipment->workspace_id.'/'.$shipment->id;
    }
}
