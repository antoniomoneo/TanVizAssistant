(function(){
  function norm(table, col){
    if (typeof col !== 'string') return col;
    const cols = table.columns || [];
    const idx = cols.map(c => String(c).toLowerCase()).indexOf(col.toLowerCase());
    return idx === -1 ? col : cols[idx];
  }
  function wrap(target, name, colIndex){
    const orig = target[name];
    if (typeof orig !== 'function') return;
    target[name] = function(...args){
      if (args[colIndex] && typeof args[colIndex] === 'string') {
        args[colIndex] = norm(this.table || this, args[colIndex]);
      }
      return orig.apply(this, args);
    };
  }
  if (window.p5 && p5.Table && !p5.Table.__tanviz_ci){
    wrap(p5.Table.prototype, 'get', 1);
    wrap(p5.Table.prototype, 'getString', 1);
    wrap(p5.Table.prototype, 'getNum', 1);
    wrap(p5.Table.prototype, 'set', 1);
    wrap(p5.Table.prototype, 'getColumn', 0);
    wrap(p5.TableRow.prototype, 'get', 0);
    wrap(p5.TableRow.prototype, 'getString', 0);
    wrap(p5.TableRow.prototype, 'getNum', 0);
    wrap(p5.TableRow.prototype, 'set', 0);
    p5.Table.__tanviz_ci = true;
  }
})();
