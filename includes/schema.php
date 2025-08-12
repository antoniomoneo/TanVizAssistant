<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_p5_json_schema() {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['code'],
        'properties' => [
            'code' => [
                'type' => 'string',
                'description' => 'Pure p5.js sketch (global or instance). No <script> or HTML.',
                'minLength' => 20,
            ],
            'meta' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'title'  => [ 'type' => 'string' ],
                    'width'  => [ 'type' => 'integer', 'minimum' => 0 ],
                    'height' => [ 'type' => 'integer', 'minimum' => 0 ],
                    'palette'=> [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                ],
            ],
            'diagnostics' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'warnings' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'actions'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                ],
            ],
        ],
    ];
}
