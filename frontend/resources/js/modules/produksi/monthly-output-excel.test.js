import assert from 'node:assert/strict';
import test from 'node:test';
import XLSX from 'xlsx-js-style';
import { buildMonthlyOutputWorkbook } from './monthly-output-excel.js';

function report(days = 31) {
  return {
    monthLabel: 'Agustus 2026', sheetName: 'Rekap Output', todayColumn: 0,
    headers: ['SHIFT', 'INDIKATOR', ...Array.from({ length: days }, (_, i) => i + 1)],
    rowKinds: ['normal', 'actual', 'percentage', 'difference', 'shift'],
    rows: [
      ['', 'TARGET OUTPUT (MT)', 1.2, ...Array(days - 1).fill(null)],
      ['', 'AKTUAL OUTPUT (MT)', 0.39, ...Array(days - 1).fill(null)],
      ['', 'Achievement (%)', 32.5, ...Array(days - 1).fill(null)],
      ['', 'Selisih Aktual − Plan (MT)', -0.81, ...Array(days - 1).fill(null)],
      ['1', 'Output/Shift (MT)', 0.27, ...Array(days - 1).fill(0)],
    ],
  };
}

test('monthly workbook matches merged headers, zebra stripes and number formatting', () => {
  const sheet = buildMonthlyOutputWorkbook(report()).Sheets['Rekap Output'];
  assert.equal(sheet['!ref'], 'A1:AG7');
  assert.equal(sheet.C1.v, 'Agustus 2026');
  assert.equal(sheet.AG2.v, 31);
  assert.equal(sheet.A3.v, 'TARGET OUTPUT (MT)');
  assert.equal(sheet.C3.s.fill.fgColor.rgb, 'FFFFFF');
  assert.equal(sheet.A4.s.fill.fgColor.rgb, 'F2F3F5');
  assert.equal(sheet.C4.s.fill.fgColor.rgb, 'F2F3F5');
  assert.equal(sheet.C5.s.fill.fgColor.rgb, 'FFFFFF');
  assert.equal(sheet.C5.v, 0.325);
  assert.equal(sheet.C5.s.numFmt, '0.00%');
  assert.equal(sheet.C3.s.numFmt, '#,##0.00');
  assert.equal(sheet.C6.s.font.color.rgb, 'B42335');
  assert.equal(sheet.C6.s.border.bottom.style, 'thin');
  assert.equal(sheet.D3.v, '—');
  assert.equal(sheet.A7.v, '1');
  assert.equal(sheet.B7.v, 'Output/Shift (MT)');
  assert.deepEqual(sheet['!merges'][2], { s: { r: 0, c: 2 }, e: { r: 0, c: 32 } });
  assert.equal(sheet['!cols'][1].wpx, 210);
});

test('serialized XLSX preserves merged cells, fills and numeric percentages', () => {
  const data = XLSX.write(buildMonthlyOutputWorkbook(report()), { type: 'buffer', bookType: 'xlsx', compression: true });
  const sheet = XLSX.read(data, { type: 'buffer', cellStyles: true }).Sheets['Rekap Output'];
  assert.equal(sheet.C3.t, 'n');
  assert.equal(sheet.C3.v, 1.2);
  assert.equal(sheet.C5.v, 0.325);
  assert.equal(sheet.C5.w, '32.50%');
  assert.equal(sheet.C4.s.fgColor.rgb, 'F2F3F5');
  assert.equal(sheet.C3.s.fgColor.rgb, 'FFFFFF');
  assert.equal(sheet['!merges'].length, 7);
});

test('February export ends at the selected month length without extra columns', () => {
  const sheet = buildMonthlyOutputWorkbook(report(28)).Sheets['Rekap Output'];
  assert.equal(sheet['!ref'], 'A1:AD7');
  assert.equal(sheet.AD2.v, 28);
  assert.equal(sheet.AE2, undefined);
});
