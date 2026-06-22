#!/usr/bin/env python3
"""Normalize the supplier control register ("F.12.0 Control de proveedores") into a clean CSV.

Long format: one row per (supplier, year) evaluation, so the importer can upsert each yearly
evaluation idempotently (the entity has a unique (supplier, year)). Year columns are detected from
the "ESTADO 20XX" headers. The incidents sheet is read too, but in the source it is empty.
Output schema:

    supplier_name,product_or_service,year,criterion

Usage: python3 normalize_suppliers.py <input.xlsx> <output.csv>
"""
import csv
import re
import sys

from xlsx import read_sheet

CRITERION_BY_TEXT = [
    (('no apto', 'rechaz', 'no capaz', 'no capac'), 'not_capable'),
    (('prueba', 'provisional', 'en pruebas', 'periodo de prueba'), 'on_trial'),
    (('aprobado', 'apto', 'capaz', 'válido', 'valido'), 'capable'),
]


def map_criterion(raw):
    low = raw.strip().lower()
    if low == '':
        return ''
    for needles, value in CRITERION_BY_TEXT:
        if any(n in low for n in needles):
            return value
    return ''


def year_columns(header):
    """Map column index -> year for every 'ESTADO 20XX' header cell."""
    cols = {}
    for i, cell in enumerate(header):
        m = re.search(r'(20\d{2})', cell)
        if m and 'estado' in cell.lower():
            cols[i] = int(m.group(1))
    return cols


def normalize(path):
    rows = read_sheet(path, 1)
    # The header is the row carrying the yearly "ESTADO 20XX" columns (not the title row, which
    # also mentions "proveedor").
    header_idx = next((i for i, r in enumerate(rows) if year_columns(r)), 1)
    years = year_columns(rows[header_idx])

    out = []
    for row in rows[header_idx + 1:]:
        name = row[0].strip() if row else ''
        if name == '':
            continue
        service = row[1].strip() if len(row) > 1 else ''
        for col, year in years.items():
            criterion = map_criterion(row[col]) if col < len(row) else ''
            if criterion == '':
                continue
            out.append({
                'supplier_name': name,
                'product_or_service': service,
                'year': year,
                'criterion': criterion,
            })
    return out


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows = normalize(sys.argv[1])
    rows.sort(key=lambda r: (r['supplier_name'], r['year']))
    fields = ['supplier_name', 'product_or_service', 'year', 'criterion']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
