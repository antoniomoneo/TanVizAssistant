// Include this script on the p5.js editor page to capture client-side errors.
(function(){
  if (window.tanvizClientLoggerLoaded) return;
  window.tanvizClientLoggerLoaded = true;

  async function sha1Hex(str){
    const buf = new TextEncoder().encode(str);
    const hash = await crypto.subtle.digest('SHA-1', buf);
    return Array.from(new Uint8Array(hash)).map(b=>b.toString(16).padStart(2,'0')).join('');
  }

  async function send(type, data){
    try {
      const meta = window.TANVIZ_META || {};
      const payload = {
        action: 'client_error',
        type: type,
        message: data.message || String(data.reason || ''),
        source: data.filename || '',
        lineno: data.lineno || 0,
        colno: data.colno || 0,
        stack: data.error && data.error.stack ? data.error.stack : (data.reason && data.reason.stack ? data.reason.stack : ''),
      };
      if (meta.datasetUrl) payload.dataset_url = meta.datasetUrl;
      if (meta.code) payload.code_hash = (await sha1Hex(meta.code)).slice(0,12);
      fetch('/wp-json/tanvizassistant/v1/logs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true
      });
    } catch(e) {}
  }

  window.addEventListener('error', function(e){ send('error', e); });
  window.addEventListener('unhandledrejection', function(e){ send('unhandledrejection', e); });
})();
