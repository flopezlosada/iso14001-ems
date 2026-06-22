#!/usr/bin/env python3
"""Normalize the environmental-aspects register ("RG-06.01.01") into a clean CSV.

The workbook's evaluation sheet stacks three blocks under marker rows in column A:

    AA DIRECTOS                         -> type "direct"  (frequency / intensity / hazard)
    AA INDIRECTOS (ciclo de vida)       -> type "indirect" (capacity of influence)
    AA SITUACIONES DE NO NORMALIDAD     -> type "abnormal" (probability / control / severity)

Within the direct block, column A also carries the category marker (CONSUMOS / EMISIONES /
RESIDUOS / VERTIDOS), merged so only the first row of each group is populated; we carry it forward.

This script only extracts and classifies; it deliberately leaves the domain rules (mapping a raw
2/4/6 to a score level, deciding whether a row was actually evaluated, computing significance) to
the importer, where they are unit-tested in PHP. Output schema (one row per aspect):

    type,category,name,unit,associated_impact,year,frequency,intensity,hazard,probability,control,severity,influence,notes

Usage: python3 normalize_aspects.py <input.xlsx> <output.csv> [year]
"""
import csv
import re
import sys
import zipfile

from xlsx import read_sheet, clean_number

# Section markers in column A, matched by prefix (the cells carry trailing text, e.g. parentheses).
SECTION_DIRECT = 'AA DIRECTOS'
SECTION_INDIRECT = 'AA INDIRECTOS'
SECTION_ABNORMAL = 'AA SITUACIONES'

# Direct-aspect category markers (column A) -> DirectAspectCategory enum value.
DIRECT_CATEGORIES = {
    'CONSUMOS': 'consumption',
    'EMISIONES': 'emission',
    'RESIDUOS': 'waste',
    'VERTIDOS': 'discharge',
}

# Column A values that are headers/flow markers, never an aspect.
NON_ASPECT_MARKERS = {'ENTRADAS', 'SALIDAS', 'ASPECTO AMBIENTAL', 'UNIDAD DE MEDIDA'}


def cell(row, i):
    """Trimmed value of column i, or '' when the row is shorter."""
    return row[i].strip() if i < len(row) else ''


def find_year(rows, override):
    """Evaluation year: the CLI override if given, else the first 20xx found in the header band."""
    if override:
        return override
    for row in rows[:4]:
        for value in row:
            match = re.search(r'(20\d{2})', value or '')
            if match:
                return match.group(1)
    sys.exit('No se encontró el año (cabecera "Fecha: ... 20XX"); pásalo como 3.er argumento.')


def normalize(path, year_override):
    rows = read_sheet(path, 1)
    year = find_year(rows, year_override)

    out = []
    mode = None
    category = ''
    for row in rows:
        marker = cell(row, 0)
        if marker.startswith(SECTION_DIRECT):
            mode, category = 'direct', ''
            continue
        if marker.startswith(SECTION_INDIRECT):
            mode, category = 'indirect', ''
            continue
        if marker.startswith(SECTION_ABNORMAL):
            mode, category = 'abnormal', ''
            continue
        if mode is None:
            continue
        if marker in DIRECT_CATEGORIES:
            category = DIRECT_CATEGORIES[marker]

        if mode == 'indirect':
            name = marker  # the indirect block keeps the aspect name in column A
            if name == '' or name in NON_ASPECT_MARKERS or name.startswith('AA '):
                continue
            out.append({
                'type': 'indirect', 'category': '', 'name': name, 'unit': '',
                'associated_impact': cell(row, 4), 'year': year,
                'frequency': '', 'intensity': '', 'hazard': '',
                'probability': '', 'control': '', 'severity': '',
                'influence': clean_number(cell(row, 8)),
                'notes': cell(row, 2),  # the indirect block uses column C as a short description
            })
            continue

        # direct / abnormal share the layout: name in C, scores in F/G/H, observations in K.
        name = cell(row, 2)
        if name == '' or name in NON_ASPECT_MARKERS:
            continue
        a, b, c = clean_number(cell(row, 5)), clean_number(cell(row, 6)), clean_number(cell(row, 7))
        common = {
            'type': mode, 'category': category if mode == 'direct' else '', 'name': name,
            'unit': cell(row, 3), 'associated_impact': cell(row, 4), 'year': year,
            'influence': '', 'notes': cell(row, 10),
        }
        if mode == 'direct':
            out.append({**common, 'frequency': a, 'intensity': b, 'hazard': c,
                        'probability': '', 'control': '', 'severity': ''})
        else:
            out.append({**common, 'frequency': '', 'intensity': '', 'hazard': '',
                        'probability': a, 'control': b, 'severity': c})
    return out


def main():
    if len(sys.argv) not in (3, 4):
        sys.exit(__doc__)
    rows = normalize(sys.argv[1], sys.argv[3] if len(sys.argv) == 4 else None)
    fields = ['type', 'category', 'name', 'unit', 'associated_impact', 'year',
              'frequency', 'intensity', 'hazard', 'probability', 'control', 'severity',
              'influence', 'notes']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
