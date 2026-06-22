#!/usr/bin/env python3
"""Normalize the "CONSUMOS LUZ AGUA GASOIL PAPEL TONER" workbook into a clean CSV.

The workbook holds one sheet per year, each a month x utility matrix. We unpivot it to one row
per (type, year, month) with a quantity and (when applicable) a cost — exactly the shape of the
ConsumptionReading entity. Output schema:

    type,period_year,period_month,quantity,cost,notes

Usage:
    python3 normalize_consumptions.py <input.xlsx> <output.csv>
"""
import csv
import re
import sys
import zipfile

from xlsx import read_sheet, sheet_names, clean_number

# Spanish month name (any casing, accents irrelevant here) -> month number.
MONTHS = {
    'ENERO': 1, 'FEBRERO': 2, 'MARZO': 3, 'ABRIL': 4, 'MAYO': 5, 'JUNIO': 6,
    'JULIO': 7, 'AGOSTO': 8, 'SEPTIEMBRE': 9, 'OCTUBRE': 10, 'NOVIEMBRE': 11, 'DICIEMBRE': 12,
}

# Workbook label -> ConsumptionType enum value.
TYPE_BY_LABEL = {'GASOIL': 'gasoil', 'LUZ': 'electricity', 'AGUA': 'water', 'PAPEL': 'paper', 'TONER': 'toner'}
COST_TOKENS = ('EURO',)  # a column whose header mentions euros is the cost column


def detect_columns(rows):
    """Map column index -> (type, 'quantity'|'cost') by scanning for the utility header row."""
    for row in rows[:8]:
        joined = ' '.join(row).upper()
        if 'GASOIL' in joined and ('LITROS' in joined or 'EUROS' in joined):
            mapping = {}
            for ci, cell in enumerate(row):
                up = cell.upper()
                label = next((lbl for lbl in TYPE_BY_LABEL if lbl in up), None)
                if not label:
                    continue
                metric = 'cost' if any(tok in up for tok in COST_TOKENS) else 'quantity'
                mapping[ci] = (TYPE_BY_LABEL[label], metric)
            return mapping
    return {}


def detect_year(name, rows):
    m = re.search(r'(20\d{2})', name)
    if m:
        return int(m.group(1))
    for row in rows[:6]:
        for cell in row:
            m = re.search(r'^(20\d{2})(\.0)?$', cell.strip())
            if m:
                return int(m.group(1))
    return None


def normalize(path):
    out, skipped = [], []
    for idx, name in enumerate(sheet_names(__import__('zipfile').ZipFile(path)), start=1):
        rows = read_sheet(path, idx)
        cols = detect_columns(rows)
        year = detect_year(name, rows)
        if not cols or not year:
            skipped.append(f'sheet "{name}": cols={bool(cols)} year={year}')
            continue
        for row in rows:
            month = next((MONTHS[c.upper()] for c in row if c.upper() in MONTHS), None)
            if month is None:
                continue
            # group the mapped cells of this row by type
            by_type = {}
            for ci, (ctype, metric) in cols.items():
                if ci < len(row):
                    by_type.setdefault(ctype, {})[metric] = clean_number(row[ci])
            for ctype, vals in by_type.items():
                qty, cost = vals.get('quantity', ''), vals.get('cost', '')
                if qty == '' and cost == '':
                    continue
                if qty == '':
                    skipped.append(f'{ctype} {year}-{month:02d}: cost without quantity (cost={cost})')
                    continue
                # toner records no cost (entity rule)
                if ctype == 'toner':
                    cost = ''
                out.append({'type': ctype, 'period_year': year, 'period_month': month,
                            'quantity': qty, 'cost': cost, 'notes': ''})
    return out, skipped


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows, skipped = normalize(sys.argv[1])
    rows.sort(key=lambda r: (r['type'], r['period_year'], r['period_month']))
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=['type', 'period_year', 'period_month', 'quantity', 'cost', 'notes'])
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')
    for s in skipped:
        print(f'  skip: {s}', file=sys.stderr)


if __name__ == '__main__':
    main()
