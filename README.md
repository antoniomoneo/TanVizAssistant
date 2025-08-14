# TanViz (WordPress plugin)

Admin‑only generator of **generative p5.js** visualizations using OpenAI **Responses API** with **JSON Schema**.

## Installation
1. Copy the `TanViz/` folder into `wp-content/plugins/`.
2. Activate **TanViz** in WordPress admin → Plugins.
3. Go to **TanViz → Settings** and set:
   - OpenAI API Key.
   - OpenAI Model (default: `gpt-4o-2024-08-06`).
   - OpenAI Assistant ID (optional).
   - GitHub Datasets Base (raw URL prefix, e.g. `https://raw.githubusercontent.com/user/repo/branch/path/`).
   - Overlay Logo URL (optional).

## First test
1. Place a CSV or JSON file (e.g., `data.csv`) under the configured GitHub base URL.
2. In **TanViz → Sandbox**:
   - Write a prompt describing the visualization.
   - Pick the dataset; a 20‑row sample is shown.
   - Click **Generate visualization** to call OpenAI.
   - Inspect/edit the p5.js sketch and **Update preview**.
   - Provide a Title and Slug then **Save to Library**.
   - Use **Export PNG** or **Export GIF** (experimental, same‑origin canvases only).
   - **Copy iframe** to reuse elsewhere.

## Library & embedding
- Saved sketches are stored as CPT `tanviz_visualization` and listed with actions to Edit, Copy shortcode, Copy iframe, or Delete.
- Use the shortcode `[TanViz slug="your-slug"]` in posts/pages. TanViz enqueues p5.js only when needed.
- Public iframe endpoint: `<iframe src="https://your-site.com/tanviz/embed/your-slug" loading="lazy"></iframe>`.

## Limits
- GIF export relies on `gif.js` and may fail with cross‑origin assets or heavy animations.
- Dataset listing is a simple file list; adjust via the `tanviz_dataset_candidates` filter for custom repos.
- Keep your API key server‑side. The plugin never exposes it to the browser.

## Troubleshooting dataset loading

If the preview console shows `Script error (0,0)`, the CSV likely failed to download due to cross‑origin restrictions. Run the sketch from a web server instead of opening the file directly:

```bash
npx http-server
# or
python3 -m http.server
```

Add an error callback to `loadTable` to inspect failures:

```javascript
table = loadTable(
  'https://raw.githubusercontent.com/antoniomoneo/Datasets/main/temperaturas_anomalias_1880_2024.csv',
  'csv',
  'header',
  () => console.log('Tabla cargada'),
  err => console.error('Error cargando la tabla:', err)
);
```

If the remote CSV remains inaccessible, download it and load it locally:

```javascript
table = loadTable('temperaturas_anomalias_1880_2024.csv', 'csv', 'header');
```

See `examples/temperature-anomaly` for a complete sketch demonstrating these steps.
