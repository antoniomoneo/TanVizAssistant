<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_p5_json_schema() {
    return [
        'type'       => 'object',
        'properties' => [
            'codigo' => [
                'type'        => 'string',
                'description' => 'Código p5.js que genera la visualización.',
            ],
            'titulo' => [
                'type'        => 'string',
                'description' => 'Nombre o título de la visualización.',
            ],
            'descripcion' => [
                'type'        => 'string',
                'description' => 'Descripción breve de la visualización.',
            ],
            'tags' => [
                'type'        => 'array',
                'description' => 'Palabras clave para clasificar la visualización.',
                'items'       => [
                    'type' => 'string',
                ],
            ],
        ],
        'required' => [ 'codigo', 'titulo' ],
        'additionalProperties' => false,
    ];
}
