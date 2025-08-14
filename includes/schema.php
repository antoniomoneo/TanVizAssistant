<?php
// Define the schema's properties once so the required list can be derived
// automatically. This keeps the arrays perfectly aligned.
$properties = [
    'ok' => ['type' => 'boolean'],
    'codigo' => ['type' => 'string', 'minLength' => 1],
    'descripcion' => ['type' => 'string', 'minLength' => 1],
    'dataset_contract' => [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'dataset_url' => ['type' => 'string', 'format' => 'uri'],
            'columns' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Mapa de alias de columnas, p.ej. {"year":"Año","value":"Valor"}',
            ],
            'requirements' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'must_include_tokens' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Tokens/literales que deben aparecer en el código (p.ej. {{DATASET_URL}}, {{col.year}}, {{col.value}}, yearMin, yearMax)',
                    ],
                ],
                'required' => ['must_include_tokens'],
            ],
        ],
        'required' => ['dataset_url', 'columns', 'requirements'],
    ],
    'placeholders' => [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'key' => ['type' => 'string', 'minLength' => 1],
                'description' => ['type' => 'string'],
            ],
            'required' => ['key'],
        ],
    ],
    'checks' => [
        'type' => 'array',
        'items' => [
            'type' => 'string',
            'description' => 'Reglas/chequeos que debe cumplir el código (sin eval/import dinámico, sin XHR/fetch en runtime, sin datos de ejemplo, etc.)',
        ],
    ],
];

return [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    'title' => 'TanVizResponse',
    'type' => 'object',
    'additionalProperties' => false,
    'properties' => $properties,
    'required' => array_keys($properties),
];
