let table, yearCol, anomalyCol;

function preload() {
  table = loadTable(
    'https://raw.githubusercontent.com/antoniomoneo/Datasets/main/temperaturas_anomalias_1880_2024.csv',
    'csv',
    'header',
    () => console.log('Tabla cargada'),
    err => console.error('Error cargando la tabla:', err)
  );
}

function setup() {
  createCanvas(windowWidth, windowHeight);
  noStroke();
  yearCol = getColNameInsensitive(table, 'Year');
  anomalyCol = getColNameInsensitive(table, 'Anomaly');
}

function draw() {
  background(255);
  const maxRows = 20;
  const totalRows = min(table.getRowCount(), maxRows);

  for (let i = 0; i < totalRows; i++) {
    const row = table.getRow(i);
    const year = row.getNum(yearCol);
    const anomaly = row.getNum(anomalyCol);
    const x = map(year, 1880, 2024, 0, width);
    const y = map(anomaly, -2, 2, height, 0);
    
    fill(0, 100, 255, random(100, 255));
    ellipse(x, y, random(10, 30), random(10, 30));
  }
}

function windowResized() {
  resizeCanvas(windowWidth, windowHeight);
}

function getColNameInsensitive(tbl, target) {
  if (!tbl || typeof target !== 'string') return target;
  const cols = Array.isArray(tbl.columns) ? tbl.columns : [];
  const lower = target.toLowerCase();
  for (let i = 0; i < cols.length; i++) {
    if (String(cols[i]).toLowerCase() === lower) {
      return cols[i];
    }
  }
  return target;
}
