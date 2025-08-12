<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_p5_json_schema() {
    return [
        'type'                 => 'object',
        'properties'           => [
            'code' => [
                'type'        => 'string',
                'description' => 'El código p5.js generado',
                'format'      => [
                    'name' => 'plain_text',
                ],
            ],
        ],
        'required'             => [ 'code' ],
        'additionalProperties' => false,
    ];
}
