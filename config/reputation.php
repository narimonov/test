<?php

/*
|--------------------------------------------------------------------------
| Reviews and blacklisting
|--------------------------------------------------------------------------
|
| Drivers rate carriers and carriers rate drivers. Whoever collects more
| than the allowed number of unsatisfactory reviews is blacklisted, and can
| appeal.
|
*/

return [

    // At or below this rating a review counts as unsatisfactory.
    'negative_rating_at_or_below' => env('REPUTATION_NEGATIVE_AT', 2),

    // How many unsatisfactory reviews lead to a blacklist.
    'blacklist_after_negative' => env('REPUTATION_BLACKLIST_AFTER', 3),

    /*
    | After a successful appeal the old negative reviews stop counting,
    | otherwise the account would be re-listed immediately.
    */
    'reset_counter_on_appeal' => true,
];
