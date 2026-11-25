<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    |
    | Protects the server from file floods and oversized ("bomb") images.
    |
    */

    // Files allowed in a single submission or a single upload action.
    'max_files_per_submission' => (int) env('UPLOAD_MAX_FILES_PER_SUBMISSION', 5),

    // Total evidence files allowed on one inquiry.
    'max_files_per_inquiry' => (int) env('UPLOAD_MAX_FILES_PER_INQUIRY', 20),

    // Files one account may upload in a rolling 24 hours. Demo accounts are
    // shared by many visitors, so they get a higher allowance.
    'daily_file_quota' => (int) env('UPLOAD_DAILY_FILE_QUOTA', 30),
    'daily_file_quota_demo' => (int) env('UPLOAD_DAILY_FILE_QUOTA_DEMO', 100),

    // Reject images larger than this, even when the file itself is small.
    'max_image_side' => (int) env('UPLOAD_MAX_IMAGE_SIDE', 6000),
    'max_image_pixels' => (int) env('UPLOAD_MAX_IMAGE_PIXELS', 25000000),

    // Longest side after re-encoding profile photos and agency logos.
    'avatar_max_side' => 800,
    'logo_max_side' => 512,

];
