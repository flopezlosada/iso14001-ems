#!/usr/bin/env python3
"""Normalize the general objectives register ("F.07.01 LISTADO DE OBJETIVOS GENERALES") into a CSV.

One row per objective. The sheet tracks compliance across yearly revisions (several CUMPLIMIENTO
columns); the status is taken from the latest filled revision. The target period ("FECHA") is free
text (a month range or a date) and is kept verbatim. Output schema:

    reference,sequence,description,target_period,status

Usage: python3 normalize_objectives.py <input.xlsx> <output.csv>
"""
import csv
import re
import sys

from xlsx import read_sheet, excel_serial_to_date

STATUS_BY_TEXT = [
    ('no aplica', 'not_applicable'),
    ('no cumplido', 'not_achieved'),
    ('no conseguido', 'not_achieved'),
    ('cumplido', 'achieved'),
    ('conseguido', 'achieved'),
    ('logrado', 'achieved'),
    ('en curso', 'in_progress'),
]


def map_status(values):
    """Status from the latest non-empty compliance cell; defaults to in_progress."""
    for cell in reversed(values):
        low = cell.strip().lower()
        if low == '':
            continue
        for needle, value in STATUS_BY_TEXT:
            if needle in low:
                return value
    return 'in_progress'


def period(raw):
    s = raw.strip()
    if s == '':
        return ''
    try:
        float(s)
        return excel_serial_to_date(s)
    except ValueError:
        return s


def normalize(path):
    out = []
    for row in read_sheet(path, 1):
        first = row[0].strip() if row else ''
        m = re.match(r'^(\d+)(\.0)?$', first)
        if not m:
            continue
        sequence = int(m.group(1))
        description = row[3].strip() if len(row) > 3 else ''
        if description == '':
            continue
        out.append({
            'reference': f'OBJ.{sequence:02d}',
            'sequence': sequence,
            'description': description,
            'target_period': period(row[1] if len(row) > 1 else ''),
            'status': map_status(row[4:7] if len(row) > 4 else []),
        })
    return out


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows = normalize(sys.argv[1])
    rows.sort(key=lambda r: r['sequence'])
    fields = ['reference', 'sequence', 'description', 'target_period', 'status']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
