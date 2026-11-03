import * as XLSX from 'xlsx';

export function downloadPNG(filename, title, period, headers, rows) {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('Browser tidak mendukung pembuatan gambar.');

  const padding = 48;
  const titleHeight = 58;
  const metaHeight = 44;
  const headerHeight = 54;
  const fontSize = headers.length > 12 ? 13 : headers.length > 8 ? 14 : 15;
  const lineHeight = fontSize + 5;
  const maxColumnWidth = headers.length > 12 ? 190 : 260;
  const minColumnWidth = headers.length > 12 ? 88 : 110;
  ctx.font = `600 ${fontSize}px Arial, sans-serif`;

  const columnWidths = headers.map((header, index) => {
    const samples = [header, ...rows.slice(0, 80).map((row) => row[index] ?? '—')];
    const longest = Math.max(
      ...samples.map((value) =>
        String(value)
          .split(/\s+/)
          .reduce((max, word) => Math.max(max, ctx.measureText(word).width), 0)
      )
    );
    const preferred = Math.max(
      ...samples.slice(0, 30).map((value) => Math.min(maxColumnWidth, ctx.measureText(String(value)).width + 28)),
      0
    );
    return Math.max(minColumnWidth, Math.min(maxColumnWidth, Math.max(longest + 28, Math.min(preferred, maxColumnWidth))));
  });

  const tableWidth = columnWidths.reduce((sum, width) => sum + width, 0);
  const contentWidth = tableWidth + padding * 2;

  function wrapText(value, maxWidth, font) {
    ctx.font = font;
    const words = String(value ?? '—').replace(/\s+/g, ' ').trim().split(' ');
    const lines = [];
    let line = '';
    words.forEach((word) => {
      const candidate = line ? `${line} ${word}` : word;
      if (line && ctx.measureText(candidate).width > maxWidth) {
        lines.push(line);
        line = word;
      } else {
        line = candidate;
      }
    });
    if (line) lines.push(line);
    return lines.length ? lines : ['—'];
  }

  const bodyFont = `${fontSize}px Arial, sans-serif`;
  const headerFont = `700 ${fontSize}px Arial, sans-serif`;
  const preparedRows = rows.map((row) =>
    row.map((value, index) => wrapText(value, columnWidths[index] - 24, bodyFont))
  );
  const rowHeights = preparedRows.map((row) =>
    Math.max(42, Math.max(...row.map((lines) => lines.length)) * lineHeight + 22)
  );
  const totalHeight =
    padding + titleHeight + metaHeight + headerHeight + rowHeights.reduce((sum, h) => sum + h, 0) + padding + 28;
  const scale = 2;

  canvas.width = Math.ceil(contentWidth * scale);
  canvas.height = Math.ceil(totalHeight * scale);
  canvas.style.width = `${contentWidth}px`;
  canvas.style.height = `${totalHeight}px`;
  ctx.scale(scale, scale);

  ctx.fillStyle = '#f4f7fb';
  ctx.fillRect(0, 0, contentWidth, totalHeight);
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(padding, padding, tableWidth, totalHeight - padding * 2);

  ctx.fillStyle = '#102a43';
  ctx.fillRect(padding, padding, tableWidth, 8);
  ctx.fillStyle = '#102a43';
  ctx.font = '700 27px Arial, sans-serif';
  ctx.textBaseline = 'middle';
  ctx.fillText(title, padding, padding + 32);
  ctx.fillStyle = '#526579';
  ctx.font = '14px Arial, sans-serif';
  ctx.fillText(`Periode: ${period}  •  ${rows.length.toLocaleString('id-ID')} baris data`, padding, padding + titleHeight + 13);

  let y = padding + titleHeight + metaHeight;
  ctx.fillStyle = '#173b5e';
  ctx.fillRect(padding, y, tableWidth, headerHeight);
  let x = padding;
  headers.forEach((header, index) => {
    const lines = wrapText(header, columnWidths[index] - 24, headerFont);
    ctx.fillStyle = '#ffffff';
    ctx.font = headerFont;
    lines.forEach((line, lineIndex) =>
      ctx.fillText(line, x + 12, y + headerHeight / 2 + (lineIndex - (lines.length - 1) / 2) * lineHeight)
    );
    x += columnWidths[index];
  });
  y += headerHeight;

  preparedRows.forEach((row, rowIndex) => {
    const rowHeight = rowHeights[rowIndex];
    ctx.fillStyle = rowIndex % 2 === 0 ? '#ffffff' : '#f5f8fc';
    ctx.fillRect(padding, y, tableWidth, rowHeight);
    x = padding;
    row.forEach((lines, columnIndex) => {
      ctx.fillStyle = '#243b53';
      ctx.font = bodyFont;
      lines.forEach((line, lineIndex) =>
        ctx.fillText(line, x + 12, y + rowHeight / 2 + (lineIndex - (lines.length - 1) / 2) * lineHeight)
      );
      x += columnWidths[columnIndex];
    });
    ctx.strokeStyle = '#d8e1eb';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padding, y + rowHeight);
    ctx.lineTo(padding + tableWidth, y + rowHeight);
    ctx.stroke();
    y += rowHeight;
  });

  x = padding;
  ctx.strokeStyle = '#d8e1eb';
  ctx.lineWidth = 1;
  ctx.strokeRect(
    padding,
    padding + titleHeight + metaHeight,
    tableWidth,
    headerHeight + rowHeights.reduce((sum, h) => sum + h, 0)
  );
  columnWidths.slice(0, -1).forEach((width) => {
    x += width;
    ctx.beginPath();
    ctx.moveTo(x, padding + titleHeight + metaHeight);
    ctx.lineTo(x, y);
    ctx.stroke();
  });
  ctx.fillStyle = '#718096';
  ctx.font = '12px Arial, sans-serif';
  ctx.fillText('Digital WIP Coiling Board', padding, totalHeight - 20);

  canvas.toBlob((blob) => {
    if (!blob) return;
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }, 'image/png');
}

export function downloadExcel(filename, sheetName, headers, rows) {
  const worksheet = XLSX.utils.aoa_to_sheet([headers, ...rows]);
  const usedRange = worksheet['!ref'];

  if (usedRange) {
    const range = XLSX.utils.decode_range(usedRange);
    worksheet['!autofilter'] = { ref: usedRange };
    worksheet['!cols'] = headers.map((header, columnIndex) => {
      const sampleValues = [header, ...rows.slice(0, 100).map((row) => row[columnIndex])];
      const longest = sampleValues.reduce((max, value) => Math.max(max, String(value ?? '').length), 0);
      return { wch: Math.min(42, Math.max(12, longest + 2)) };
    });

    for (let rowIndex = 1; rowIndex <= range.e.r; rowIndex += 1) {
      for (let columnIndex = 0; columnIndex <= range.e.c; columnIndex += 1) {
        const cellAddress = XLSX.utils.encode_cell({ r: rowIndex, c: columnIndex });
        const cell = worksheet[cellAddress];
        if (cell?.t === 'n' && Number.isFinite(cell.v)) {
          cell.z = '#,##0.##';
        }
      }
    }
  }

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, String(sheetName).slice(0, 31));
  XLSX.writeFile(workbook, filename, { bookType: 'xlsx', compression: true });
}
