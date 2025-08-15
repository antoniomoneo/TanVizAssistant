# Lecciones aprendidas

1. **Placeholders Incorrectos**: Haz de reemplazar `{{col.year}}` y `{{col.value}}` con los nombres correctos de las columnas en el CSV. Sin esto, el código no funcionará correctamente.
2. **Cálculo de Rango**: La función `calculateRanges()` busca valores mínimos y máximos, lo cual es correcto. Sin embargo, asegúrate de que los datos no estén vacíos o mal formateados para evitar errores.
3. **Visualización**: El uso de `beginShape()` y `endShape()` está bien para crear una línea, pero podrías considerar agregar puntos para mostrar mejor los datos.
4. **Manejo de Errores**: Considera añadir manejo de errores para cargar el CSV, en caso de que la URL sea incorrecta o el archivo no esté disponible.
5. **Reactividad**: La función `windowResized()` está correctamente configurada para redibujar el gráfico al cambiar el tamaño de la ventana, pero asegúrate de que el aspecto visual se mantenga adecuado en diferentes resoluciones.
6. **Estilo Visual**: Podrías mejorar la visualización añadiendo etiquetas para los ejes y un título para dar contexto a los datos mostrados.
7. **Optimización**: `noLoop()` evita que `draw()` se llame repetidamente, lo cual es eficiente para este tipo de visualización estática.
