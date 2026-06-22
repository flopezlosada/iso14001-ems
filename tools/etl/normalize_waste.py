#!/usr/bin/env python3
"""Normalize the chronological waste register ("Archivo cronológico RESIDUOS") into a clean CSV.

The real register is messy: ~80% of LER codes were mangled by Excel into date serials, some amounts
are in non-weight units ("43 bolsones"), and pick-up dates appear as Excel serials, dd/mm/yyyy,
ranges or free text. We keep every row (the WasteRecord entity now allows the affected fields to be
null) and preserve the original wording of anything non-structured in the notes column. Output:

    ler_code,description,quantity_kg,pickup_date,manager,hazardous,notes

Usage: python3 normalize_waste.py <input.xlsx> <output.csv>
"""
import csv
import re
import sys

from xlsx import read_sheet, clean_number, excel_serial_to_date

LER_RE = re.compile(r'^(\d{6})(-\d+)?$')
RANGE_RE = re.compile(r'(\d{2}/\d{2}/\d{4}).*-.*(\d{2}/\d{2}/\d{4})')
DMY_RE = re.compile(r'^(\d{2})/(\d{2})/(\d{4})$')

# Columns of the register (0-based), from its header row.
COL = {'ler': 0, 'desc': 1, 'start': 2, 'pickup': 3, 'qty': 4, 'dcs': 5,
       'manager': 14, 'operation': 17, 'hp': 18, 'type': 19, 'comments': 20}


def parse_ler(raw):
    """Return (clean_code_or_empty, hazardous_bool, note_or_empty)."""
    haz = '*' in raw
    v = raw.replace('*', '').replace(' ', '').strip()
    if v.endswith('.0'):
        v = v[:-2]
    if v == '':
        return '', haz, ''
    m = LER_RE.match(v)
    if m:
        suffix = m.group(2) or ''
        note = f'subtipo LER: {m.group(1)}{suffix}' if suffix else ''
        return m.group(1), haz, note
    return '', haz, f'código LER original ilegible: {raw.strip()}'


def parse_quantity(raw):
    """Return (kg_or_empty, note_or_empty)."""
    s = raw.strip()
    if s == '':
        return '', ''
    n = clean_number(s)
    if n != '':
        return n, ''
    return '', f'cantidad original: {s}'


def parse_pickup(raw):
    """Return (iso_date_or_empty, note_or_empty)."""
    s = raw.strip()
    if s == '':
        return '', ''
    try:
        float(s)
        return excel_serial_to_date(s), ''
    except ValueError:
        pass
    m = DMY_RE.match(s)
    if m:
        return f'{m.group(3)}-{m.group(2)}-{m.group(1)}', ''
    m = RANGE_RE.search(s)
    if m:
        d, mo, y = m.group(1).split('/')
        return f'{y}-{mo}-{d}', f'periodo original: {s}'
    return '', f'fecha original: {s}'


def cell(row, key):
    idx = COL[key]
    return row[idx].strip() if idx < len(row) else ''


def normalize(path):
    out = []
    for row in read_sheet(path, 1)[2:]:  # rows 1-2 are title + header
        if not any(c.strip() for c in row):
            continue
        ler, haz, ler_note = parse_ler(cell(row, 'ler'))
        qty, qty_note = parse_quantity(cell(row, 'qty'))
        pickup, pickup_note = parse_pickup(cell(row, 'pickup'))
        if cell(row, 'hp'):
            haz = True

        notes = [n for n in (ler_note, qty_note, pickup_note) if n]
        for key, label in (('start', 'inicio almacenamiento'), ('dcs', 'DCS'),
                           ('operation', 'operación'), ('hp', 'HP'), ('type', 'tipo'),
                           ('comments', 'comentarios')):
            val = cell(row, key)
            if val:
                if key == 'start':
                    try:
                        val = excel_serial_to_date(val)
                    except ValueError:
                        pass
                notes.append(f'{label}: {val}')

        out.append({
            'ler_code': ler,
            'description': cell(row, 'desc'),
            'quantity_kg': qty,
            'pickup_date': pickup,
            'manager': cell(row, 'manager'),
            'hazardous': '1' if haz else '0',
            'notes': ' | '.join(notes),
        })
    return out


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows = normalize(sys.argv[1])
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=['ler_code', 'description', 'quantity_kg', 'pickup_date', 'manager', 'hazardous', 'notes'])
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
