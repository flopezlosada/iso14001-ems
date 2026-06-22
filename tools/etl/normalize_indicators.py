#!/usr/bin/env python3
"""Normalize the indicators register ("F.09.0 INDICADORES") into a clean CSV.

The workbook has one "Medidas YYYY" sheet per year, each a matrix of indicators (rows) by month
(columns). We unpivot it to one row per (indicator, year, month) numeric measurement, plus one
metadata-only row per indicator so indicators with only qualitative readings ("NINGUNA", "TODOS
LOS ALUMNOS" — which the numeric measurement entity cannot hold) are still created. Output schema:

    name,process,periodicity,reference_value,measurement_description,year,month,value

Usage: python3 normalize_indicators.py <input.xlsx> <output.csv>
"""
import csv
import re
import sys

from xlsx import read_sheet, sheet_names, clean_number
import zipfile

MONTHS = {'enero': 1, 'febrero': 2, 'marzo': 3, 'abril': 4, 'mayo': 5, 'junio': 6,
          'julio': 7, 'agosto': 8, 'septiembre': 9, 'octubre': 10, 'noviembre': 11, 'diciembre': 12}

PROCESS_BY_TEXT = [
    ('planific', 'planning'),
    ('recurso', 'resources'),
    ('desempeñ', 'performance_evaluation'),
    ('desempen', 'performance_evaluation'),
    ('evaluación del des', 'performance_evaluation'),
    ('mejora', 'improvement'),
]


def map_process(raw):
    low = raw.strip().lower()
    for needle, value in PROCESS_BY_TEXT:
        if needle in low:
            return value
    return ''


def map_periodicity(raw):
    low = raw.strip().lower()
    if 'mensual' in low:
        return 'monthly'
    if 'trimestral' in low:
        return 'quarterly'
    if 'semestral' in low or 'bianual' in low:
        return 'biannual'
    if 'anual' in low:
        return 'annual'
    return 'monthly'


def month_columns(header):
    cols = {}
    for i, cell in enumerate(header):
        key = cell.strip().lower()
        if key in MONTHS:
            cols[i] = MONTHS[key]
    return cols


def normalize(path):
    indicators = {}  # name -> metadata dict
    measurements = []
    for idx, sheet in enumerate(sheet_names(zipfile.ZipFile(path)), start=1):
        ym = re.search(r'medidas\s*(20\d{2})', sheet.lower())
        if not ym:
            continue  # skip "Seguimiento" sheets
        year = int(ym.group(1))
        rows = read_sheet(path, idx)
        # Header is the row carrying the month columns (not the title, which also says "indicadores").
        header_idx = next((i for i, r in enumerate(rows) if month_columns(r)), 1)
        months = month_columns(rows[header_idx])
        for row in rows[header_idx + 1:]:
            name = row[1].strip() if len(row) > 1 else ''
            if name == '':
                continue
            meta = {
                'name': name,
                'process': map_process(row[0] if row else ''),
                'periodicity': map_periodicity(row[3] if len(row) > 3 else ''),
                'reference_value': clean_number(row[4]) or (row[4].strip() if len(row) > 4 else ''),
                'measurement_description': row[2].strip() if len(row) > 2 else '',
            }
            indicators.setdefault(name, meta)
            for col, month in months.items():
                value = clean_number(row[col]) if col < len(row) else ''
                if value == '':
                    continue  # blank, dash or qualitative reading -> not a numeric measurement
                measurements.append({**meta, 'year': year, 'month': month, 'value': value})
    # one metadata-only row per indicator (year/month/value empty) so all indicators are created
    meta_rows = [{**m, 'year': '', 'month': '', 'value': ''} for m in indicators.values()]
    return meta_rows + measurements


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows = normalize(sys.argv[1])
    fields = ['name', 'process', 'periodicity', 'reference_value', 'measurement_description', 'year', 'month', 'value']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
