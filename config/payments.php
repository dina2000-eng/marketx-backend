<?php

return [
    'default_gateway' =>
        env(
            'VENDO_PAYMENT_GATEWAY',
            'fake'
        ),

   'fake_enabled' =>
    (bool) env(
        'VENDO_FAKE_PAYMENTS_ENABLED',
        false
    ),
];