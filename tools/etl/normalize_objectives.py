#!/usr/bin/env python3
"""Normalize the general objectives register ("F.07.01 LISTADO DE OBJETIVOS GENERALES") into a CSV.

One row per objective. The sheet tracks compliance across yearly revisions (several CUMPLIMIENTO
columns); the status is taken from the latest filled revision. The target period ("FECHA") is free
text (a month range or a date) and is kept verbatim.

The centre redacts the objectives anew each course and restarts the numbering ("OBJ.01" appears
once per year), so the per-course code alone is NOT a stable key across years. We emit it as
`source_code` together with the `school_year` derived from the file/folder name ("OBJETIVOS 23-24",
"...GENERALES_24_25") so the importer can upsert by (school_year, source_code) and keep the three
courses side by side. The globally unique reference (OBJ-NN) is assigned by the importer, not here.
Output schema:

    source_code,school_year,description,target_period,status

Usage: python3 normalize_objectives.py <input.xlsx> <output.csv> [school_year]
       (school_year overrides the path-derived course, e.g. "2023-2024")
"""
import csv
import os
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


def derive_school_year(path, override):
    """School term "20AA-20BB" from an explicit override, else from a "AA-BB"/"AA_BB" pattern in the
    file name or its parent folder ("OBJETIVOS 23-24"). Returns '' when none carries a recognizable
    term, in which case the importer rejects the rows rather than guessing the course.
    """
    if override:
        return override.replace('/', '-').replace('_', '-')
    file_name = os.path.basename(path)
    parent = os.path.basename(os.path.dirname(path))
    for source in (file_name, parent):
        match = re.search(r'(\d{2})[-_/](\d{2})', source or '')
        if match:
            return f'20{match.group(1)}-20{match.group(2)}'
    return ''


def normalize(path, school_year):
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
            'source_code': f'OBJ.{sequence:02d}',
            'school_year': school_year,
            'description': description,
            'target_period': period(row[1] if len(row) > 1 else ''),
            'status': map_status(row[4:7] if len(row) > 4 else []),
        })
    return out


def main():
    if len(sys.argv) not in (3, 4):
        sys.exit(__doc__)
    school_year = derive_school_year(sys.argv[1], sys.argv[3] if len(sys.argv) == 4 else None)
    if school_year == '':
        print(f'WARN: could not derive the school year from "{sys.argv[1]}"; rows will carry an empty '
              'course and be rejected on import. Pass it explicitly as the 3rd argument.', file=sys.stderr)
    rows = normalize(sys.argv[1], school_year)
    rows.sort(key=lambda r: r['source_code'])
    fields = ['source_code', 'school_year', 'description', 'target_period', 'status']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
