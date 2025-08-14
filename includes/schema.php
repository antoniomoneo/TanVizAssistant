<?php
return [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    'title' => 'TanVizResponse',
    'type' => 'object',
    'additionalProperties' => false,
    'required' => ['ok','codigo','descripcion','dataset_contract','placeholders','checks'],
    'properties' => [
        'ok' => ['type' => 'boolean', 'const' => true],
        'codigo' => ['type' => 'string', 'minLength' => 1],
        'descripcion' => ['type' => 'string'],
        'dataset_contract' => [
            'type' => 'object',
            'additionalProperties' => false,
        ],
        'placeholders' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
        ],
        'checks' => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
];
