<?php

return [

    'fee_percentage' => env('ATM_FEE_PERCENTAGE', 0.01),

    'max_retries' => env('ATM_MAX_RETRIES', 3),

    'retry_delay' => env('ATM_RETRY_DELAY', 50), // in milliseconds

];