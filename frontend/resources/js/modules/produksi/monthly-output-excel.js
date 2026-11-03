import XLSX from 'xlsx-js-style';

export function buildMonthlyOutputWorkbook(payload) {
  const dayNumbers = payload.headers.slice(2);
  const columnCount = dayNumbers.length + 2;
  const data = [
    ['SHIFT', 'BULAN\nTANGGAL', payload.monthLabel, ...dayNumbers.slice(1).map(() => '')],
    ['', '', ...dayNumbers],
    ...payload.rows.map((row) => row.map((value) => value ?? '—')),
  ];
  const sheet = XLSX.utils.aoa_to_sheet(data);
  const merges = [
    { s: { r: 0, c: 0 }, e: { r: 1, c: 0 } },
    { s: { r: 0, c: 1 }, e: { r: 1, c: 1 } },
    { s: { r: 0, c: 2 }, e: { r: 0, c: columnCount - 1 } },
  ];
  const edge = { style: 'thin', color: { rgb: '6A7687' } };
  const divider = { style: 'medium', color: { rgb: '344256' } };

  for (let r = 0; r < data.length; r += 1) {
    const isHeader = r < 2;
    const kind = isHeader ? 'header' : payload.rowKinds[r - 2];
    const mergedLabel = !isHeader && payload.rows[r - 2][0] === '';
    if (mergedLabel) {
      merges.push({ s: { r, c: 0 }, e: { r, c: 1 } });
      sheet[XLSX.utils.encode_cell({ r, c: 0 })] = { t: 's', v: payload.rows[r - 2][1] };
      sheet[XLSX.utils.encode_cell({ r, c: 1 })] = { t: 's', v: '' };
    }
    for (let c = 0; c < columnCount; c += 1) {
      const address = XLSX.utils.encode_cell({ r, c });
      const cell = sheet[address] ?? (sheet[address] = { t: 's', v: '' });
      const difference = kind === 'difference' && c >= 2;
      const bold = isHeader || kind === 'actual' || kind === 'calculated' || difference;
      const border = { top: edge, bottom: edge, left: edge, right: c === 1 ? divider : edge };
      if (!isHeader && (kind === 'calculated' || kind === 'shift')) border.top = divider;
      if (Number.isInteger(payload.todayColumn) && c === payload.todayColumn + 2 && r >= 1) {
        border.left = border.right = { style: 'medium', color: { rgb: '7D9DC5' } };
      }
      cell.s = {
        font: { name: 'Arial', sz: isHeader && r === 0 && c === 2 ? 10.5 : 9, bold, color: { rgb: difference ? 'B42335' : '203F6C' } },
        fill: { patternType: 'solid', fgColor: { rgb: isHeader ? 'F3F6FB' : (r - 2) % 2 === 0 ? 'FFFFFF' : 'F2F3F5' } },
        border,
        alignment: { vertical: 'center', horizontal: isHeader || (c === 0 && !mergedLabel) ? 'center' : c < 2 ? 'left' : 'right', wrapText: isHeader },
        numFmt: c >= 2 && !isHeader ? (kind === 'percentage' ? '0.00%' : '#,##0.00') : 'General',
      };
      if (kind === 'percentage' && c >= 2 && cell.t === 'n') cell.v /= 100;
    }
  }
  sheet['!merges'] = merges;
  sheet['!cols'] = [{ wpx: 50 }, { wpx: 210 }, ...dayNumbers.map(() => ({ wpx: 82 }))];
  sheet['!rows'] = data.map((_, index) => ({ hpx: index < 2 ? 36 : 34 }));
  sheet['!margins'] = { left: 0.25, right: 0.25, top: 0.4, bottom: 0.4, header: 0.2, footer: 0.2 };
  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, sheet, payload.sheetName || 'Rekap Output');
  return workbook;
}

export function downloadMonthlyOutputExcel(payload) {
  XLSX.writeFile(buildMonthlyOutputWorkbook(payload), payload.filename, { bookType: 'xlsx', compression: true });
}
