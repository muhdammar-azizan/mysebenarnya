<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Service level targets (in days)
    |--------------------------------------------------------------------------
    |
    | triage_days        - how long a new inquiry may wait for MCMC to triage it.
    | investigation_days - how long an assigned inquiry may stay under
    |                      investigation before it is flagged as overdue.
    |
    */

    'triage_days' => (int) env('SLA_TRIAGE_DAYS', 3),

    'investigation_days' => (int) env('SLA_INVESTIGATION_DAYS', 14),

];
