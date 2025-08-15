<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function tanviz_extract_structured( array $api_json ) {
    if ( ! empty( $api_json['output'] ) && is_array( $api_json['output'] ) ) {
        foreach ( $api_json['output'] as $item ) {
            if ( empty( $item['content'] ) || ! is_array( $item['content'] ) ) continue;
            foreach ( $item['content'] as $c ) {
                $type = $c['type'] ?? '';
                if ( ($type === 'json_schema' || $type === 'output_json') && isset( $c['json'] ) ) {
                    return $c['json'];
                }
            }
        }
    }
    if ( ! empty( $api_json['output_text'] ) && is_string( $api_json['output_text'] ) ) {
        $maybe = json_decode( $api_json['output_text'], true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe ) ) return $maybe;
    }
    $buf = '';
    if ( ! empty( $api_json['output'] ) ) {
        foreach ( $api_json['output'] as $item ) {
            foreach ( ($item['content'] ?? []) as $c ) {
                if ( ($c['type'] ?? '') === 'output_text' ) {
                    if ( isset($c['text']) && is_string($c['text']) ) $buf .= $c['text'];
                    elseif ( isset($c['text']['value']) && is_string($c['text']['value']) ) $buf .= $c['text']['value'];
                }
            }
        }
    }
    $buf = trim($buf);
    if ( $buf !== '' ) {
        $maybe = json_decode( $buf, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe ) ) return $maybe;
        if ( preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $buf, $m) ) {
            $maybe = json_decode( trim($m[1]), true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe ) ) return $maybe;
        }
    }
    return null;
}

function tanviz_is_valid_p5( $code ) {
    if ( ! is_string($code) || strlen($code) < 20 ) return false;
    $global_ok = preg_match('/\bfunction\s+setup\s*\(/i', $code) || preg_match('/\bsetup\s*=\s*function\s*\(/i', $code);
    $inst_ok   = preg_match('/new\s+p5\s*\(\s*function\s*\(\s*p\s*\)/i', $code) ||
                 preg_match('/new\s+p5\s*\(\s*\(\s*p\s*\)\s*=>/i', $code) ||
                 preg_match('/\bp\.\s*setup\s*=\s*function\s*\(/i', $code);
    return ( $global_ok || $inst_ok );
}

function tanviz_normalize_p5_code( $code ) {
    $code = preg_replace('/^```(?:p5|javascript|js)?\s*|\s*```$/m', '', $code);
    $code = preg_replace('#<\s*/?\s*script[^>]*>#i', '', $code);
    // Strip multiline and single-line JS comments
    $code = preg_replace('~/\*.*?\*/~s', '', $code);
    $code = preg_replace('~(?<!:)//.*$~m', '', $code);
    return trim($code);
}

function tanviz_build_user_content( $dataset_url, $user_prompt, $sample_rows = 20 ) {
    $input = <<<P5PROMPT
Eres un experto desarrollador de código p5.js. Genera una visualización en p5.js a partir del dataset indicado y del objetivo creativo descrito por el usuario.

OBJETIVO
    • Entregar SOLO el código final de p5.js en el espacio para la edición de código que sea funcional, óptimo, sin errores y listo para ejecutarse.

ENTRADAS
PROMPT DEL USUARIO:
{$user_prompt}

DATASET:
URL del dataset: {$dataset_url}
(El dataset puede ser CSV o JSON. Usa los placeholders existentes, por ejemplo: {{col.year}}, {{col.value}}, etc.)

⸻

REGLAS DE GENERACIÓN (OBLIGATORIAS)
    1. Estructura p5.js
    • Incluir (según corresponda) preload(), setup(), draw(), windowResized() y funciones auxiliares como drawGenerativeVisualization().
    • Declarar todas las variables de estado compartidas (table, dataJSON, years, values, minValue, maxValue y cualquier otra usada en varias funciones) una sola vez en el ámbito global (fuera de cualquier función).
    • Prohibido redeclarar estas variables dentro de funciones (setup, draw, windowResized, etc.).
    • Usar noLoop() y redraw() cuando el renderizado no sea animado.

    2. Carga de datos
    • Usar exclusivamente funciones de p5.js en preload() para {$dataset_url}:
      – CSV: loadTable({$dataset_url}, 'csv', 'header')
      – JSON: loadJSON({$dataset_url})
    • Implementar y usar SIEMPRE esta función auxiliar para buscar columnas ignorando mayúsculas/minúsculas en tablas CSV:

    ```js
    function getColNameInsensitive(tbl, target) {
      if (!tbl || !tbl.columns) return -1;
      for (let c = 0; c < tbl.columns.length; c++) {
        if (String(tbl.columns[c]).toLowerCase() === String(target).toLowerCase()) {
          return c;
        }
      }
      return -1;
    }
    ```

    • No inventar datos de ejemplo; usar SOLO el dataset indicado.
    • Respetar y reutilizar EXACTAMENTE los placeholders/variables/URLs existentes (p.ej., {{DATASET_URL}}, {{col.year}}, {{col.value}}).
    • Antes de acceder a campos/columnas, comprobar su existencia (en CSV con getColNameInsensitive; en JSON comprobando claves/longitud).

    3. Diseño y lógica
    • Implementar la visualización solicitada por {$user_prompt} con escalas/rangos dinámicos (p.ej., detectar año mínimo/máximo si procede).
    • Calcular minValue y maxValue a partir de los datos válidos. Si minValue === maxValue, ampliar ligeramente el rango para evitar divisiones por cero.
    • Si la visualización lo requiere, incluir ejes básicos y/o línea de cero cuando caiga dentro del rango.
    • Evitar patrones frágiles (eval, import dinámico, fetch/XHR manual fuera de p5.js).
    • No añadir dependencias externas nuevas.

    4. Robustez (métodos y manejo de errores)
    • Mostrar un mensaje de diagnóstico en el canvas cuando falten columnas/llaves o no existan datos válidos.
    • Incluir y usar una función drawGenerativeVisualization() que pinte la escena completa sin redefinir estado global.
    • Incluir una función drawMessage(msg) que pinte mensajes de diagnóstico en el canvas si hay problemas.
    • Si se dibujan ejes/etiquetas, incluir una función auxiliar (p.ej., drawAxesLabels(...)) y llamarla desde drawGenerativeVisualization().
    • windowResized() debe llamar a drawGenerativeVisualization() reutilizando las variables globales (sin reprocesar el dataset innecesariamente).
    • Manejar datasets grandes con eficiencia; evitar trabajo costoso dentro de draw() si no hay animación.

    5. Estilo de salida
    • SALIDA EXCLUSIVAMENTE EN FORMATO DE CÓDIGO JS (sin texto, comentarios, logs o anotaciones).
    • No incluir banners, encabezados ni “explicaciones”.
    • Prohibido usar console.log.

    6. Compatibilidad
    • Mantener la interfaz/nombres esperados por el entorno.
    • Conservar exactamente los placeholders existentes; no cambiarlos.

    7. Performance
    • Optimizar cálculos fuera de draw() siempre que sea posible.
    • No bloquear el hilo principal.
    • Para visualizaciones no animadas, usar noLoop() y llamar a redraw() solo cuando sea necesario (p.ej., al redimensionar).

⸻

VALIDACIONES AUTOMÁTICAS (ANTES DE DEVOLVER EL CÓDIGO)
    • ¿El archivo es completo, ejecutable en p5.js y sin errores de sintaxis?
    • ¿Usa {$dataset_url} en preload() con loadTable/loadJSON según corresponda?
    • ¿Se han conservado los placeholders/variables/URLs originales?
    • ¿No contiene comentarios, texto adicional ni console.log?
    • ¿Implementa fielmente el objetivo de {$user_prompt} con escalas/rangos dinámicos donde aplique?
    • ¿Evita dependencias externas y patrones frágiles?
    • ¿Las variables globales (years, values, etc.) están definidas solo una vez fuera de cualquier función?
    • ¿Existe la función getColNameInsensitive() y se usa para acceder a columnas?
    • ¿drawGenerativeVisualization() y drawMessage() existen y se usan correctamente?
    • ¿windowResized() llama a drawGenerativeVisualization() y no redeclara variables globales?
    • ¿Se evita el rango nulo (minValue === maxValue) ajustando el dominio antes de mapear?

⸻

FORMATO DE RESPUESTA (ESTRICTO)
Devuelve ÚNICAMENTE el código final del archivo p5.js, sin ningún texto antes o después.
P5PROMPT;
    return $input;
}
