# Lecciones aprendidas

- **Placeholders correctos**: Reemplaza `{{col.year}}` y `{{col.value}}` con los nombres reales de las columnas del dataset.
- **Dependencia de `preload()`**: Esta función bloquea la ejecución hasta que los datos se cargan; toma en cuenta su impacto en el tiempo de carga.
- **Manejo de errores**: Maneja errores en la carga de datos mostrando mensajes en la consola y, si es posible, también en la visualización.
- **Verificación de `table`**: Cuando se usa `preload()`, la tabla estará disponible en `draw()` y no necesita comprobaciones redundantes.
- **Cálculo y sincronización de rangos**: `calculateRanges()` debe considerar valores vacíos o no numéricos para evitar resultados incorrectos.
- **Reactividad**: Usa `windowResized()` y `redraw()` o `loop()` para ajustar la visualización al cambiar el tamaño de la ventana.
- **Estética y UX**: Añade etiquetas, títulos y usa color o grosor de línea para mejorar la comprensión del gráfico.
- **Visualización**: `beginShape()`/`endShape()` pueden complementarse con puntos para destacar los datos.
- **Optimización**: `noLoop()` es útil para evitar redibujos innecesarios en visualizaciones estáticas.
