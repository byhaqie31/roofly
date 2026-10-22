<?php

/*
| Operator + contact details for the email footer (App\Support\EmailFooter).
| Mirrors frontend/app/config/legal.ts — change both together. A null value
| drops out of the footer; it never renders as a placeholder.
*/

return [

    'operator' => [
        'name'               => 'Axel Nova Ventures',
        'ssm_number'         => '202603119899 (CA0420977-U)',
        'registered_address' => 'I-City, Seksyen 7, Shah Alam, Selangor',
    ],

    'contact' => [
        'email' => 'baihaqie@axelnova.tech',
    ],

];
