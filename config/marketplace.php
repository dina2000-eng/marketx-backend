<?php

return [
    'default_commission_pct' =>
        (float) env(
            'VENDO_DEFAULT_COMMISSION_PCT',
            10
        ),
];