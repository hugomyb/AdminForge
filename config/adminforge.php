<?php

return [
    'openai' => [
        'enabled' => true,
        'api_key' => null,
        'model' => 'gpt-3.5-turbo',
    ],
    'sql' => [
        'max_query_length' => 1000,
        'enable_query_history' => true,
        'max_history_items' => 50,
    ],
];
