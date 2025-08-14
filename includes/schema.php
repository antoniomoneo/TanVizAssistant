<?php
/**
 * TanViz / tangibleviz – JSON Schema y pre-validación para respuestas de código p5.js
 *
 * Reemplaza por completo tu archivo includes/schema.php con este.
 * PHP 8.1+
 */

namespace TanViz;

final class Schema
{
    /**
     * Devuelve el JSON Schema (Draft 2020-12) como array PHP.
     * Úsalo para pasarlo a tu validador (ajv vía node, opis/json-schema en PHP, etc.).
     */
    public static function responseSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'title' => 'TanVizResponse',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['ok', 'codigo', 'descripcion', 'dataset_contract', 'placeholders', 'checks'],
            'properties' => [
                'ok' => ['type' => 'boolean', 'const' => true],
                'descripcion' => ['type' => 'string', 'minLength' => 40, 'maxLength' => 600],
                'placeholders' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['dataset_url', 'columns'],
                    'properties' => [
                        'dataset_url' => ['type' => 'string', 'const' => '{{DATASET_URL}}'],
                        'columns' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['year', 'value'],
                            'properties' => [
                                'year' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                                'value' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                            ],
                        ],
                    ],
                ],
                'dataset_contract' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['requires_header', 'column_roles', 'delimiter'],
                    'properties' => [
                        'requires_header' => ['type' => 'boolean', 'const' => true],
                        'delimiter' => ['type' => 'string', 'enum' => [',', ';', "\t"]],
                        'column_roles' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['year', 'value'],
                            'properties' => [
                                'year' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
                                'value' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
                                'optional' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ],
                'checks' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['must_include', 'must_not_include'],
                    'properties' => [
                        'must_include' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'minItems' => 6,
                        ],
                        'must_not_include' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'minItems' => 3,
                        ],
                    ],
                ],
                'codigo' => [
                    'type' => 'string',
                    'minLength' => 200,
                    'maxLength' => 30000,
                    // “allOf” de patrones: obliga a funciones, placeholders, rangos, y bloquea malas prácticas
                    'allOf' => [
                        ['pattern' => 'function\\s+preload\\s*\\('],
                        ['pattern' => 'function\\s+setup\\s*\\('],
                        ['pattern' => 'function\\s+draw\\s*\\('],
                        ['pattern' => '\\{\\{DATASET_URL\\}\\}'],
                        ['pattern' => '\\{\\{col\\.year\\}\\}'],
                        ['pattern' => '\\{\\{col\\.value\\}\\}'],
                        ['pattern' => '\\byearMin\\b'],
                        ['pattern' => '\\byearMax\\b'],
                        ['not' => ['pattern' => '(?:const\\s+sampleData|Sample\\s*data)']],
                        ['not' => ['pattern' => '(?:eval\\s*\\(|new\\s+Function\\s*\\()']],
                        ['not' => ['pattern' => '(?:import\\s*\\(|XMLHttpRequest|fetch\\s*\\()']],
                    ],
                ],
            ],
        ];
    }

    /**
     * Pre‑validación rápida por regex antes del JSON Schema.
     * Devuelve un array de errores; si está vacío, pasa.
     *
     * @param string $code Código p5.js
     * @param array $checks Estructura de checks.must_include / must_not_include (opcional)
     * @return string[] lista de errores
     */
    public static function preflightValidate(string $code, array $checks = []): array
    {
        $errors = [];

        // 1) Requisitos mínimos
        $requiredPatterns = [
            'function preload(' => '/function\s+preload\s*\(/',
            'function setup('   => '/function\s+setup\s*\(/',
            'function draw('    => '/function\s+draw\s*\(/',
            '{{DATASET_URL}}'   => '/\{\{DATASET_URL\}\}/',
            '{{col.year}}'      => '/\{\{col\.year\}\}/',
            '{{col.value}}'     => '/\{\{col\.value\}\}/',
            'yearMin'           => '/\byearMin\b/',
            'yearMax'           => '/\byearMax\b/',
        ];
        foreach ($requiredPatterns as $label => $rx) {
            if (!preg_match($rx, $code)) {
                $errors[] = "Falta obligatorio: {$label}";
            }
        }

        // 2) Patrones prohibidos
        $forbidden = [
            'sample data inline' => '/(?:const\s+sampleData|Sample\s*data)/i',
            'eval/new Function'  => '/(?:eval\s*\(|new\s+Function\s*\()/i',
            'import/fetch/XHR'   => '/(?:import\s*\(|XMLHttpRequest|fetch\s*\()/i',
        ];
        foreach ($forbidden as $label => $rx) {
            if (preg_match($rx, $code)) {
                $errors[] = "Patrón prohibido detectado: {$label}";
            }
        }

        // 3) checks externos (si se pasan desde el JSON)
        if (!empty($checks['must_include']) && is_array($checks['must_include'])) {
            foreach ($checks['must_include'] as $needle) {
                if (!is_string($needle) || $needle === '') { continue; }
                if (strpos($code, $needle) === false) {
                    $errors[] = "checks.must_include no satisfecho: \"{$needle}\"";
                }
            }
        }
        if (!empty($checks['must_not_include']) && is_array($checks['must_not_include'])) {
            foreach ($checks['must_not_include'] as $needle) {
                if (!is_string($needle) || $needle === '') { continue; }
                if (strpos($code, $needle) !== false) {
                    $errors[] = "checks.must_not_include infringido: \"{$needle}\"";
                }
            }
        }

        return $errors;
    }

    /**
     * Devuelve un payload “checks” por defecto, útil si el modelo no lo rellena.
     */
    public static function defaultChecks(): array
    {
        return [
            'must_include' => [
                'function preload(',
                'function setup(',
                'function draw(',
                '{{DATASET_URL}}',
                '{{col.year}}',
                '{{col.value}}',
            ],
            'must_not_include' => [
                'const sampleData',
                'eval(',
                'import(',
            ],
        ];
    }
}

