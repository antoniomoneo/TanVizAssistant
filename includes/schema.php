<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_p5_json_schema() {
    return [
        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
        'title' => 'TanViz p5.js Sketch',
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'codigo' => [
                'type' => 'string',
                'description' => 'Sketch p5.js (solo JS). Debe incluir function setup() y function draw(). Sin HTML.',
                'minLength' => 50,
                'maxLength' => 200000,
                'pattern' => '(?s)function\\s+setup\\s*\\(\\)[\\s\\S]*function\\s+draw\\s*\\(\\)',
                'not' => [ 'pattern' => '<\\s*(html|head|body|script|style)\\b' ],
            ],
            'variables' => [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 60,
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'Identificador usado en el código.',
                            'pattern' => '^[A-Za-z_][A-Za-z0-9_\\-]*$',
                            'minLength' => 2,
                            'maxLength' => 64,
                        ],
                        'label' => [ 'type' => 'string', 'minLength' => 1, 'maxLength' => 120 ],
                        'type' => [
                            'type' => 'string',
                            'enum' => [ 'number', 'select', 'text', 'boolean', 'color', 'range' ],
                        ],
                        'default' => (object) [],
                        'min' => [ 'type' => 'number' ],
                        'max' => [ 'type' => 'number' ],
                        'step' => [ 'type' => 'number' ],
                        'options' => [
                            'type' => 'array',
                            'description' => 'Opciones para select',
                            'minItems' => 1,
                            'maxItems' => 50,
                            'items' => [
                                'anyOf' => [
                                    [ 'type' => 'string' ],
                                    [ 'type' => 'number' ],
                                ],
                            ],
                        ],
                        'description' => [ 'type' => 'string', 'maxLength' => 400 ],
                    ],
                    'required' => [ 'key', 'label', 'type', 'default' ],
                    'allOf' => [
                        [
                            'if' => [ 'properties' => [ 'type' => [ 'const' => 'number' ] ], 'required' => [ 'type' ] ],
                            'then' => [ 'properties' => [ 'default' => [ 'type' => 'number' ] ] ],
                        ],
                        [
                            'if' => [ 'properties' => [ 'type' => [ 'const' => 'range' ] ], 'required' => [ 'type' ] ],
                            'then' => [
                                'required' => [ 'min', 'max', 'step' ],
                                'properties' => [
                                    'default' => [ 'type' => 'number' ],
                                    'min' => [ 'type' => 'number' ],
                                    'max' => [ 'type' => 'number' ],
                                    'step' => [ 'type' => 'number' ],
                                ],
                            ],
                        ],
                        [
                            'if' => [ 'properties' => [ 'type' => [ 'const' => 'boolean' ] ], 'required' => [ 'type' ] ],
                            'then' => [ 'properties' => [ 'default' => [ 'type' => 'boolean' ] ] ],
                        ],
                        [
                            'if' => [ 'properties' => [ 'type' => [ 'const' => 'text' ] ], 'required' => [ 'type' ] ],
                            'then' => [ 'properties' => [ 'default' => [ 'type' => 'string' ] ] ],
                        ],
                        [
                            'if' => [ 'properties' => [ 'type' => [ 'const' => 'color' ] ], 'required' => [ 'type' ] ],
                            'then' => [ 'properties' => [ 'default' => [ 'type' => 'string', 'pattern' => '^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$' ] ] ],
                        ],
                        [
                            'if' => [ 'properties' => [ 'type' => [ 'const' => 'select' ] ], 'required' => [ 'type' ] ],
                            'then' => [
                                'required' => [ 'options' ],
                                'properties' => [
                                    'default' => [
                                        'anyOf' => [
                                            [ 'type' => 'string' ],
                                            [ 'type' => 'number' ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'titulo' => [ 'type' => 'string', 'maxLength' => 140 ],
                    'descripcion' => [ 'type' => 'string', 'maxLength' => 400 ],
                    'requiereDataset' => [ 'type' => 'boolean' ],
                    'columnasUsadas' => [
                        'type' => 'array',
                        'items' => [ 'type' => 'string' ],
                        'maxItems' => 50,
                    ],
                ],
            ],
            'notas' => [ 'type' => 'string', 'maxLength' => 800 ],
            'advertencias' => [
                'type' => 'array',
                'maxItems' => 20,
                'items' => [ 'type' => 'string', 'maxLength' => 300 ],
            ],
        ],
        'required' => [ 'codigo', 'variables' ],
    ];
}
