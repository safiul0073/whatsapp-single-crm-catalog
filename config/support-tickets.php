<?php

return [
    'attachments' => [
        'disk' => env('SUPPORT_TICKET_ATTACHMENTS_DISK', 'support-attachments'),
        'max_size' => env('SUPPORT_TICKET_MAX_ATTACHMENT_SIZE', 5 * 1024), // kilobytes
        'path' => env('SUPPORT_TICKET_ATTACHMENTS_PATH', 'support-tickets'),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
            'application/zip',
            'application/x-zip-compressed',
        ],
    ],
];
