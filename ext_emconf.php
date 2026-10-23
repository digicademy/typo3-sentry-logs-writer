<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Sentry Logs Writer',
    'description' => 'TYPO3 log writer that sends log records to the Logs view of Sentry or GlitchTip via the Sentry Logs API.',
    'category' => 'misc',
    'author' => 'Frodo Podschwadek',
    'author_email' => 'frodo.podschwadek@adwmainz.de',
    'author_company' => 'Academy of Sciences and Literature | Mainz',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
            'sentry_client' => '6.0.0-6.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'sentry_transaction_handler' => '1.0.0-1.99.99',
        ],
    ],
];
