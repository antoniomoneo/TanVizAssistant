<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_p5_json_schema() {
    return [
        'type'       => 'object',
        'properties' => [
            'codigo' => [
                'type'        => 'string',
                'minLength'   => 40,
                'pattern'     => '(?is)(?=.*(function\\s+setup\\s*\\(|\\bsetup\\s*=\\s*function|p\\.setup\\s*=))(?=.*(function\\s+draw\\s*\\(|\\bdraw\\s*=\\s*function|p\\.draw\\s*=)).*',
                'description' => 'Código p5.js que genera la visualización. Debe definir funciones setup() y draw().',
            ],
            'descripcion' => [
                'type'        => 'string',
                'description' => 'Descripción breve de la visualización.',
            ],
        ],
        'required' => [ 'codigo', 'descripcion' ],
        'additionalProperties' => false,
    ];
}
