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
    const html = `<!doctype html><html><head><meta charset="utf-8">
      <style>html,body{margin:0;height:100%;}#wrap{position:relative;height:100%;}
      #ovl{position:absolute;top:8px;left:8px;display:flex;align-items:center;gap:.5rem;font:14px/1.2 system-ui}
      #ovl img{height:24px}</style>
      <script src="https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js"></script></head>
      <body><div id="wrap"><div id="ovl"><img src="${logo}"/><div>${(title||'')}</div></div></div>
      <script>${code}</script></body></html>`;
    doc.open(); doc.write(html); doc.close();
  }

  $(document).on('click','#tanviz-generate',function(e){
    e.preventDefault();
    const prompt = $('#tanviz-prompt').val();
    const dataset_url = $('#tanviz-dataset').val();
    $('#tanviz-rr').text('Generating...');
    $.ajax({
      url: TanVizCfg.rest.generate,
      method: 'POST',
      headers: { 'X-WP-Nonce': TanVizCfg.nonce },
      contentType: 'application/json',
      data: JSON.stringify({ prompt, dataset_url }),
    }).done(function(resp){
      $('#tanviz-rr').text(JSON.stringify(resp,null,2));
      if (resp && resp.structured && resp.structured.code){
        setCode(resp.structured.code);
        writeIframe(resp.structured.code, $('#tanviz-title').val() || (resp.structured.meta && resp.structured.meta.title));
      }
    }).fail(function(xhr){
      $('#tanviz-rr').text(xhr.responseText || 'Error');
    });
  });

  $(document).on('click','#tanviz-preview',function(e){
    e.preventDefault();
    writeIframe(getCode(), $('#tanviz-title').val());
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

  $(document).ready(initEditor);
})(jQuery);
