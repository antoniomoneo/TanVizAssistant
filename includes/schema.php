<?php
// Define the schema's properties once so the required list can be derived
// automatically. This keeps the arrays perfectly aligned.
$properties = [
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
];

return [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    'title' => 'TanVizResponse',
    'type' => 'object',
    'additionalProperties' => false,
    'required' => array_keys($properties),
    'properties' => $properties,
];
