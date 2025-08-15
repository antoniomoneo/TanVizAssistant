(function($){
  let editor;
  function initEditor(){
    if (window.wp && window.wp.codeEditor && window.tanvizCodeEditorSettings){
      editor = wp.codeEditor.initialize( $('#tanviz-code'), window.tanvizCodeEditorSettings );
    }
  }
  function getCode(){ return editor ? editor.codemirror.getValue() : $('#tanviz-code').val(); }
  function setCode(v){ if(editor){editor.codemirror.setValue(v);} else {$('#tanviz-code').val(v);} }

  function writeIframe(code, title){
    const $if = $('#tanviz-iframe');
    const doc = $if[0].contentWindow.document;
    const logo = TanVizCfg.logo || '';
    $('#tanviz-console').text('');
    if(!code || !code.trim()){
      $('#tanviz-console').text('No code provided');
    } else {
      $('#tanviz-console').text('Running visualization...');
    }
    const html = `<!doctype html><html><head><meta charset="utf-8">
      <style>html,body{margin:0;height:100%;}#wrap{position:relative;height:100%;}
      #ovl{position:absolute;top:8px;left:8px;display:flex;align-items:center;gap:.5rem;font:14px/1.2 system-ui}
      #ovl img{height:24px}</style>
      <script src="https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js" crossorigin="anonymous"></script>
      <script>
        window.onerror = function(msg, src, line, col){
          parent.postMessage({type:'tanviz-error', message: msg+' ('+line+':'+col+')'}, '*');
        };
        console.error = (function(orig){return function(){
          parent.postMessage({type:'tanviz-error', message: Array.from(arguments).join(' ')}, '*');
          return orig.apply(console, arguments);
        };})(console.error);
        console.log = (function(orig){return function(){
          parent.postMessage({type:'tanviz-log', message: Array.from(arguments).join(' ')}, '*');
          return orig.apply(console, arguments);
        };})(console.log);
      </script></head>
      <body><div id="wrap"><div id="ovl"><img src="${logo}"/><div>${(title||'')}</div></div></div>
      <script>
        try{${code}}catch(e){parent.postMessage({type:'tanviz-error', message:e.message}, '*');}
      </script></body></html>`;
    doc.open(); doc.write(html); doc.close();
  }

  window.addEventListener('message', function(e){
    if(e.data && (e.data.type === 'tanviz-error' || e.data.type === 'tanviz-log')){
      const $c = $('#tanviz-console');
      const txt = $c.text();
      const prefix = e.data.type === 'tanviz-error' ? 'ERROR: ' : '';
      $c.text(txt + (txt ? '\n' : '') + prefix + e.data.message);
    }
  });

  $(document).on('click','#tanviz-generate',function(e){
    e.preventDefault();
    const prompt = $('#tanviz-prompt').val();
    const dataset_url = $('#tanviz-dataset').val();
    const body = { prompt, dataset_url };
    $('#tanviz-rr').text('Generating...');
    $('#tanviz-rr-wrap').prop('open', true);
    $.ajax({
      url: TanVizCfg.rest.generate,
      method: 'POST',
      headers: { 'X-WP-Nonce': TanVizCfg.nonce },
      contentType: 'application/json',
      data: JSON.stringify(body),
    }).done(function(resp){
      $('#tanviz-rr').text(JSON.stringify({request:body,response:resp},null,2));
      const code = resp && (resp.codigo || (resp.structured && resp.structured.code));
      if (code){
        setCode(code);
        const title = $('#tanviz-title').val() || resp.titulo || (resp.structured && resp.structured.meta && resp.structured.meta.title);
        writeIframe(code, title);
      }
    }).fail(function(xhr){
      $('#tanviz-rr').text(xhr.responseText || 'Error');
    });
  });

  $(document).on('click','#tanviz-export',function(e){
    e.preventDefault();
    const ifr = document.getElementById('tanviz-iframe');
    try {
      const canvas = ifr.contentWindow.document.querySelector('canvas');
      if (!canvas) { alert('No canvas found'); return; }
      const url = canvas.toDataURL('image/png');
      const a = document.createElement('a'); a.href=url; a.download='tanviz.png'; a.click();
    } catch(err){ alert('Export failed (browser sandbox)'); }
  });

  $(document).on('click','#tanviz-export-gif',function(e){
    e.preventDefault();
    const ifr = document.getElementById('tanviz-iframe');
    try {
      const canvas = ifr.contentWindow.document.querySelector('canvas');
      if (!canvas) { alert('No canvas found'); return; }
      const load = function(){
        const gif = new GIF({ workers:2, quality:10 });
        gif.addFrame(canvas, {copy:true, delay:100});
        gif.render();
        gif.on('finished', function(blob){
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a'); a.href=url; a.download='tanviz.gif'; a.click();
        });
      };
      if (typeof GIF === 'undefined') {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/gif.js@0.2.0/dist/gif.js';
        s.onload = load; document.body.appendChild(s);
      } else { load(); }
    } catch(err){ alert('GIF export failed'); }
  });

  $(document).on('change','#tanviz-dataset',function(){
    const url = $(this).val();
    if (!url){ $('#tanviz-sample').text(''); return; }
    $('#tanviz-sample').text('Loading...');
    $.ajax({
      url: TanVizCfg.rest.sample,
      method: 'GET',
      headers: { 'X-WP-Nonce': TanVizCfg.nonce },
      data: { url }
    }).done(function(resp){
      $('#tanviz-sample').text(resp.data || '');
    }).fail(function(){ $('#tanviz-sample').text('Error'); });
  });

  $(document).on('click','#tanviz-save',function(e){
    e.preventDefault();
    const payload = {
      title: $('#tanviz-title').val(),
      slug: $('#tanviz-slug').val(),
      code: getCode(),
      dataset_url: $('#tanviz-dataset').val()
    };
    $.ajax({
      url: TanVizCfg.rest.save,
      method: 'POST',
      headers: { 'X-WP-Nonce': TanVizCfg.nonce },
      contentType: 'application/json',
      data: JSON.stringify(payload)
    }).done(function(){ alert('Saved'); })
      .fail(function(){ alert('Save failed'); });
  });

  $(document).on('click','#tanviz-copy-iframe',function(e){
    e.preventDefault();
    const slug = $('#tanviz-slug').val();
    if (!slug){ alert('Slug required'); return; }
    const iframe = '<iframe src="'+TanVizCfg.embed+encodeURIComponent(slug)+'" loading="lazy"></iframe>';
    navigator.clipboard.writeText(iframe).then(()=>alert('Copied'));
  });

  $(document).on('click','#tanviz-copy-code',function(e){
    e.preventDefault();
    navigator.clipboard.writeText(getCode()).then(()=>alert('Copied'));
  });

  $(document).on('click','#tanviz-run',function(e){
    e.preventDefault();
    const code = getCode();
    const title = $('#tanviz-title').val();
    writeIframe(code, title);
  });

  $(document).on('click','#tanviz-fix',function(e){
    e.preventDefault();
    const code = getCode();
    const error = $('#tanviz-console').text();
    $('#tanviz-rr').text('Fixing...');
    $('#tanviz-rr-wrap').prop('open', true);
    $.ajax({
      url: TanVizCfg.rest.fix,
      method: 'POST',
      headers: { 'X-WP-Nonce': TanVizCfg.nonce },
      contentType: 'application/json',
      data: JSON.stringify({ code, error })
    }).done(function(resp){
      $('#tanviz-rr').text(JSON.stringify({request:{code,error},response:resp},null,2));
      const fixed = resp && resp.codigo;
      if (fixed){
        setCode(fixed);
        const title = $('#tanviz-title').val();
        writeIframe(fixed, title);
      }
    }).fail(function(xhr){
      $('#tanviz-rr').text(xhr.responseText || 'Error');
    });
  });

  $(document).on('click','#tanviz-copy-rr',function(e){
    e.preventDefault();
    const txt = $('#tanviz-rr').text();
    if (txt){ navigator.clipboard.writeText(txt).then(()=>alert('Copied')); }
  });

  $(document).on('click','#tanviz-copy-console',function(e){
    e.preventDefault();
    const txt = $('#tanviz-console').text();
    if (txt){ navigator.clipboard.writeText(txt).then(()=>alert('Copied')); }
  });

  $(document).on('click','.tanviz-copy-shortcode',function(e){
    e.preventDefault();
    const sc = $(this).data('shortcode');
    navigator.clipboard.writeText(sc).then(()=>alert('Copied'));
  });
  $(document).on('click','.tanviz-copy-iframe',function(e){
    e.preventDefault();
    const sc = $(this).data('iframe');
    navigator.clipboard.writeText(sc).then(()=>alert('Copied'));
  });

  $(document).ready(initEditor);
})(jQuery);
