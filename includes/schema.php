<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_p5_json_schema() {
    return [
        'type'       => 'object',
        'properties' => [
            'code' => [
                'type'        => 'string',
                'description' => 'Código p5.js que genera la visualización.',
            ],
            'descripcion' => [
                'type'        => 'string',
                'description' => 'Descripción breve de la visualización.',
            ],
        ],
        'required' => [ 'code', 'descripcion' ],
        'additionalProperties' => false,
    ];
}
