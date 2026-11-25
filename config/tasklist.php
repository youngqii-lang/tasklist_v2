<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task evidence uploads
    |--------------------------------------------------------------------------
    |
    | The largest evidence file a PIC may upload, in kilobytes. Keep it at or below PHP's upload_max_filesize and
    | post_max_size, or larger files fail before they reach validation.
    |
    */

    'evidence' => [
        'max_kilobytes' => (int) env('TASK_EVIDENCE_MAX_KB', 10240),
    ],

];
