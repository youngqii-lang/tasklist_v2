<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task Evidence
    |--------------------------------------------------------------------------
    |
    | Evidence files are stored on a private disk and only served through
    | the Tasklist API after the permission check. The maximum size is in
    | kilobytes.
    |
    */

    'evidence' => [
        'disk' => env('TASKLIST_EVIDENCE_DISK', 'local'),
        'max_size_kb' => (int) env('TASKLIST_EVIDENCE_MAX_KB', 10240),
        'extensions' => ['doc', 'docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

];
