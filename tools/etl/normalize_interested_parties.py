#!/usr/bin/env python3
"""Normalize the interested parties register ("F.04.0 Ident. y Eval. de Partes Interesadas / PPI")
into a clean CSV, one row per (review year, interested party).

The real sheet uses merged cells: the party name (col 0) and its incidents (col 2) appear only on
the first row of the group, and the following rows (empty col 0) carry further needs/expectations
(col 1) for the same party. This script groups those rows back together, joining every need of a
party into a single multi-line text (the entity stores needs/expectations as one TEXT field).

The review year is taken from each input file name ("… PPI 2025.xlsx" -> 2025); if the name has no
year, it falls back to the year found in the sheet's title row. Files with no detectable year are
skipped with a warning (never guessed).

Output schema:

    review_year,name,needs_and_expectations,incidents

Usage: python3 normalize_interested_parties.py <output.csv> <input1.xlsx> [input2.xlsx ...]
"""
import csv
import os
import re
import sys

from xlsx import read_sheet


def detect_year(path, rows):
    """Year from the file name, falling back to the sheet title row; None if undetectable."""
    m = re.search(r'(20\d{2})', os.path.basename(path))
    if m:
        return int(m.group(1))
    for row in rows[:3]:
        for cell in row:
            m = re.search(r'(20\d{2})', cell)
            if m:
                return int(m.group(1))
    return None


def header_index(rows):
    """Index of the header row ('PARTES INTERESADAS' | 'NECESIDADES…' | 'INCIDENCIAS')."""
    for i, row in enumerate(rows):
        joined = ' '.join(row).lower()
        if 'partes interesadas' in joined and 'necesidades' in joined:
            return i
    return 0


def normalize_file(path):
    rows = read_sheet(path, 1)
    year = detect_year(path, rows)
    if year is None:
        sys.stderr.write(f'AVISO: sin año detectable en "{path}", se omite.\n')
        return []

    start = header_index(rows) + 1
    parties = []
    current = None
    for row in rows[start:]:
        name = row[0].strip() if len(row) > 0 else ''
        need = row[1].strip() if len(row) > 1 else ''
        incident = row[2].strip() if len(row) > 2 else ''

        if name != '':
            # New party begins; flush the previous one.
            current = {'review_year': year, 'name': name, 'needs': [], 'incidents': incident}
            parties.append(current)
            if need != '':
                current['needs'].append(need)
        elif current is not None and need != '':
            # Continuation row: another need/expectation of the current party.
            current['needs'].append(need)
            # Some sheets put the incident on a later row of the group; keep the first non-empty.
            if incident != '' and current['incidents'] == '':
                current['incidents'] = incident
        # Fully empty rows act as separators and are ignored.

    out = []
    for p in parties:
        # Emit every party verbatim, even one with no needs/expectations (NOT NULL in the entity):
        # the importer validates each row and sends the invalid ones to its quarantine CSV, so all
        # rejection logic stays in one place (the importer) instead of silently dropping rows here.
        out.append({
            'review_year': p['review_year'],
            'name': p['name'],
            'needs_and_expectations': '\n'.join(p['needs']).strip(),
            'incidents': p['incidents'],
        })
    return out


def main():
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    output, inputs = sys.argv[1], sys.argv[2:]

    rows = []
    for path in inputs:
        rows.extend(normalize_file(path))
    rows.sort(key=lambda r: (r['review_year'], r['name']))

    fields = ['review_year', 'name', 'needs_and_expectations', 'incidents']
    with open(output, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    sys.stderr.write(f'{len(rows)} partes interesadas escritas en {output}.\n')


if __name__ == '__main__':
    main()
