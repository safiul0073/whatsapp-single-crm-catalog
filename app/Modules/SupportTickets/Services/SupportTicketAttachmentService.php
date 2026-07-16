<?php

namespace App\Modules\SupportTickets\Services;

use App\Modules\SupportTickets\Models\SupportTicket;
use App\Modules\SupportTickets\Models\SupportTicketAttachment;
use App\Modules\SupportTickets\Models\SupportTicketReply;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachmentService
{
    public function store(
        UploadedFile $file,
        SupportTicket $ticket,
        ?SupportTicketReply $reply,
        string $uploadedByType,
        int $uploadedById,
    ): SupportTicketAttachment {
        $disk = config('support-tickets.attachments.disk');
        $pathPrefix = config('support-tickets.attachments.path');

        $fileName = uniqid('att_', true).'.'.$file->getClientOriginalExtension();
        $filePath = $file->storeAs($pathPrefix.'/'.$ticket->id, $fileName, $disk);

        return SupportTicketAttachment::create([
            'ticket_id' => $ticket->id,
            'reply_id' => $reply?->id,
            'uploaded_by_type' => $uploadedByType,
            'uploaded_by_id' => $uploadedById,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function storeMany(
        array $files,
        SupportTicket $ticket,
        ?SupportTicketReply $reply,
        string $uploadedByType,
        int $uploadedById,
    ): array {
        $attachments = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $attachments[] = $this->store($file, $ticket, $reply, $uploadedByType, $uploadedById);
            }
        }

        return $attachments;
    }

    public function delete(SupportTicketAttachment $attachment): void
    {
        Storage::disk(config('support-tickets.attachments.disk'))->delete($attachment->file_path);
        $attachment->delete();
    }
}
