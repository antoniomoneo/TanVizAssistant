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
                'pattern' => 'function\\s+setup\\s*\\(\\)[\\s\\S]*function\\s+draw\\s*\\(\\)',
                // Ensure prohibited HTML tags are not present. The subschema used in
                // the "not" keyword must explicitly declare its type to satisfy
                // the API's JSON schema validator.
                'not' => [
                    'type'    => 'string',
                    'pattern' => '<\\s*(html|head|body|script|style)\\b',
                ],
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
                        'default' => [ 'type' => [ 'number', 'string', 'boolean' ] ],
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
                    'anyOf' => [
                        [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'type' => [ 'type' => 'string', 'const' => 'number' ],
                                'default' => [ 'type' => 'number' ],
                            ],
                        ],
                        [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'type' => [ 'type' => 'string', 'const' => 'range' ],
                                'default' => [ 'type' => 'number' ],
                                'min'     => [ 'type' => 'number' ],
                                'max'     => [ 'type' => 'number' ],
                                'step'    => [ 'type' => 'number' ],
                            ],
                            'required' => [ 'min', 'max', 'step' ],
                        ],
                        [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'type' => [ 'type' => 'string', 'const' => 'boolean' ],
                                'default' => [ 'type' => 'boolean' ],
                            ],
                        ],
                        [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'type' => [ 'type' => 'string', 'const' => 'text' ],
                                'default' => [ 'type' => 'string' ],
                            ],
                        ],
                        [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'type' => [ 'type' => 'string', 'const' => 'color' ],
                                'default' => [ 'type' => 'string', 'pattern' => '^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$' ],
                            ],
                        ],
                        [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'type' => [ 'type' => 'string', 'const' => 'select' ],
                                'default' => [
                                    'anyOf' => [
                                        [ 'type' => 'string' ],
                                        [ 'type' => 'number' ],
                                    ],
                                ],
                            ],
                            'required' => [ 'options' ],
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
