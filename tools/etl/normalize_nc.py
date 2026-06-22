#!/usr/bin/env python3
"""Normalize the non-conformities register ("F.11.0 LISTADO DE CONTROL DE NC") into a clean CSV.

One sheet per year, one row per non-conformity, each carrying a single corrective action (columns
6-10). We map the centre's year-less code (e.g. "NC.AE.01") to the entity's parts: origin from the
prefix (AE -> external_audit, AI -> internal_audit), sequence from the number, year from the opening
date. The reference itself is rebuilt by the importer to keep one source of truth for its format.

Output schema (one corrective action per non-conformity):

    origin,origin_detail,year,sequence,iso_clause,description,root_cause,status,opened_at,closed_at,action_description,action_efficacy

Usage: python3 normalize_nc.py <input.xlsx> <output.csv>
"""
import csv
import re
import sys

from xlsx import read_sheet, sheet_names, excel_serial_to_date
import zipfile

# Columns (0-based) of the register, from its header row.
COL = {'code': 0, 'opened': 1, 'origin': 2, 'responsible': 3, 'description': 4,
       'root_cause': 5, 'action': 6, 'action_resp': 7, 'plazo': 8, 'closed': 9, 'efficacy': 10}

ORIGIN_BY_PREFIX = {'AE': 'external_audit', 'AI': 'internal_audit'}
CODE_RE = re.compile(r'^NC\.([A-Z]+)\.0*(\d+)', re.IGNORECASE)


def parse_serial(raw):
    """Excel serial -> ISO date, or '' for non-numeric (e.g. 'Realizado', 'pdte.')."""
    s = raw.strip()
    try:
        float(s)
        return excel_serial_to_date(s)
    except ValueError:
        return ''


def parse_efficacy(raw):
    s = raw.strip().lower().rstrip('.')
    if s == 'ok':
        return 'ok'
    if s in ('no ok', 'no_ok', 'not ok'):
        return 'not_ok'
    return ''  # pending


def cell(row, key):
    idx = COL[key]
    return row[idx].strip() if idx < len(row) else ''


def normalize(path):
    out, skipped = [], []
    for idx in range(1, len(sheet_names(zipfile.ZipFile(path))) + 1):
        for row in read_sheet(path, idx)[2:]:  # rows 1-2 are title + header
            code = cell(row, 'code')
            m = CODE_RE.match(code)
            if not m:
                if any(c.strip() for c in row):
                    skipped.append(f'fila sin código NC válido: {code!r}')
                continue

            origin = ORIGIN_BY_PREFIX.get(m.group(1).upper(), 'internal')
            sequence = int(m.group(2))
            opened = parse_serial(cell(row, 'opened'))
            if opened == '':
                skipped.append(f'{code}: fecha de apertura no parseable ({cell(row, "opened")!r})')
                continue
            year = int(opened[:4])

            efficacy = parse_efficacy(cell(row, 'efficacy'))
            closed = parse_serial(cell(row, 'closed'))
            # Closed only when the action was reviewed effective and there is a real closing date;
            # otherwise it is still under treatment (an action is always defined for audit findings).
            status = 'closed' if (efficacy == 'ok' and closed != '') else 'in_treatment'
            if status != 'closed':
                closed = ''

            action = cell(row, 'action')
            plazo = cell(row, 'plazo')
            if plazo:
                action = f'{action} [Plazo: {plazo}]'.strip()

            out.append({
                'origin': origin,
                'origin_detail': cell(row, 'origin'),
                'year': year,
                'sequence': sequence,
                'iso_clause': '',
                'description': cell(row, 'description'),
                'root_cause': cell(row, 'root_cause'),
                'status': status,
                'opened_at': opened,
                'closed_at': closed,
                'action_description': action,
                'action_efficacy': efficacy,
            })
    return out, skipped


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows, skipped = normalize(sys.argv[1])
    rows.sort(key=lambda r: (r['origin'], r['year'], r['sequence']))
    fields = ['origin', 'origin_detail', 'year', 'sequence', 'iso_clause', 'description',
              'root_cause', 'status', 'opened_at', 'closed_at', 'action_description', 'action_efficacy']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')
    for s in skipped:
        print(f'  skip: {s}', file=sys.stderr)


if __name__ == '__main__':
    main()
