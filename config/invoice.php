<?php

return [
    'recipient_name' => env('INVOICE_RECIPIENT_NAME', 'ТОВ «ФЛАГМАН СВ»'),
    'iban'           => env('INVOICE_IBAN', 'UA000000000000000000000000000'),
    'edrpou'         => env('INVOICE_EDRPOU', '37490783'),
    'bank_name'      => env('INVOICE_BANK_NAME', ''),
    'mfo'            => env('INVOICE_MFO', ''),
];
