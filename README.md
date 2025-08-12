# TanViz (WordPress plugin)

Admin‑only generator of **generative p5.js** visualizations using OpenAI **Responses API** with **JSON Schema**.

## Install
1. Copy the `TanViz/` folder into `wp-content/plugins/`.
2. Activate **TanViz** in WordPress admin → Plugins.
3. Go to **TanViz → Settings** and set:
   - OpenAI API Key
   - Model (default: `gpt-4o-2024-08-06`)
   - GitHub Datasets Base (raw URL prefix, e.g. `https://raw.githubusercontent.com/user/repo/branch/path/`)
   - Overlay Logo URL (optional)

## Use
- **Sandbox**: write a prompt, pick a dataset, click **Generate visualization**. Edit code if needed and **Update preview**. Save to **Library** (coming soon) or export PNG.
- **Library**: stores visualizations as CPT `tanviz_visualization`. Embed via shortcode: `[TanViz slug="your-slug"]`.

## Notes
- This is a first minimal version; library save route and dataset discovery are minimal on purpose.
- Keep your API key server-side. The plugin never exposes it to the browser.
