#!/usr/bin/env python3
"""Normalize the monthly operational-control checklist ("RG-08.01.01") into a clean CSV.

Each workbook is one month's inspection (a single "CHECK LIST" sheet): a header row carrying
"Fecha de realización: <MONTH> <YEAR> Realizado por: <who>", then section headings in upper case
(CONSUMO DE AGUA, CONSUMO ENERGÉTICO, ...) each followed by its checklist items. For every item,
an "X" sits in the CONFORME column or in the NO CONFORME column, with an optional free-text remark.

Real-data quirks handled here, where they can be reviewed offline:

  * The header is often a stale template. Half of 2024's files ("ABRIL", "MARZO", "MAYO"...) all
    carry the header "FEBRERO 2024" — the date row was never updated when the template was copied.
    The FILE NAME is therefore the reliable period (its month varies correctly); the header is used
    only to cross-check (mismatches reported on stderr) and to read "Realizado por".
  * Two files can claim the same period with DIFFERENT contents (NOV. 2025 vs NOV. 2025(1)). We keep
    the more complete one (more marked items; ties broken towards the name without a "(1)" suffix)
    and report the discarded duplicate on stderr for the centre to settle. Nothing is guessed away.
  * Some months leave items unmarked: those produce no row (the model treats a missing answer as
    "not assessed"), rather than inventing a result.

This script only extracts and cleans; the upsert rules (catalogue by (section, label), inspection by
(year, month), answer by (check, item)) live in the PHP OperationalControlImporter, where they are
unit-tested.

Output schema (one row per checked item per month):

    year,month,performed_by,section,item_label,result,observation

Usage: python3 normalize_operational_control.py <input_dir> <output.csv>
       (input_dir is scanned recursively for *.xlsx)
"""
import csv
import glob
import os
import re
import sys
import unicodedata

from xlsx import read_sheet

# Spanish month name (and the abbreviations the files use) -> month number.
MONTHS = {
    'ENERO': 1, 'ENE': 1,
    'FEBRERO': 2, 'FEB': 2,
    'MARZO': 3, 'MAR': 3,
    'ABRIL': 4, 'ABR': 4,
    'MAYO': 5, 'MAY': 5,
    'JUNIO': 6, 'JUN': 6,
    'JULIO': 7, 'JUL': 7,
    'AGOSTO': 8, 'AGO': 8,
    'SEPTIEMBRE': 9, 'SEPT': 9, 'SEP': 9,
    'OCTUBRE': 10, 'OCT': 10,
    'NOVIEMBRE': 11, 'NOV': 11,
    'DICIEMBRE': 12, 'DIC': 12,
}

# Section heading keyword (on the upper-cased, accent-stripped text) -> OperationalControlSection.
SECTION_KEYWORDS = [
    ('AGUA', 'water'),
    ('ENERG', 'energy'),
    ('PAPEL', 'paper'),
    ('TINTA', 'ink'),
    ('VERTIDO', 'discharge'),
    ('EMISION', 'emissions'),
    ('RAEE', 'weee'),
    ('OFICINA', 'office_waste'),
]


def strip_accents(text):
    """Upper-cased text with diacritics removed, for accent-insensitive matching."""
    nfkd = unicodedata.normalize('NFKD', text)
    return ''.join(c for c in nfkd if not unicodedata.combining(c)).upper()


def cell(row, i):
    """Trimmed value of column i, or '' when the row is shorter."""
    return row[i].strip() if i < len(row) else ''


def section_of(text):
    """The section value for an upper-case heading, or None when the text is not a section heading."""
    flat = strip_accents(text)
    for keyword, value in SECTION_KEYWORDS:
        if keyword in flat:
            return value
    return None


def period_of(text):
    """The (year, month) found in a piece of text, each None when absent."""
    flat = strip_accents(text)
    year_match = re.search(r'\b(20\d{2})\b', flat)
    year = int(year_match.group(1)) if year_match else None
    month = None
    # Longest names first so SEPTIEMBRE wins over SEP, etc.
    for name in sorted(MONTHS, key=len, reverse=True):
        if re.search(r'\b' + name + r'\b', flat):
            month = MONTHS[name]
            break
    return year, month


def parse_header(rows):
    """Extract (header_year, header_month, performed_by) from the header cell, or (None, None, '').

    The header text sits in a single cell ("Fecha de realización: <MONTH> <YEAR> Realizado por:
    <who>"), so we locate that cell and parse it in isolation — scanning a blob of the opening rows
    would swallow section/item text into "performed_by".
    """
    header_cell = ''
    for row in rows[:5]:
        for value in row:
            flat = strip_accents(value)
            if 'FECHA DE REALIZACION' in flat or 'REALIZADO POR' in flat:
                header_cell = value
                break
        if header_cell:
            break

    year, month = period_of(header_cell)
    performed_by = ''
    who = re.search(r'realizado por\s*:?\s*(.+?)\s*$', header_cell, re.IGNORECASE)
    if who:
        performed_by = who.group(1).strip()

    return year, month, performed_by


def parse_file(path):
    """Parse one workbook into a dict {year, month, performed_by, rows:[...]}, or None if unparseable.

    The file name is the reliable period (names vary correctly while the header is often left stuck
    on a template month, e.g. "FEBRERO" across half of 2024); the header is used only to cross-check
    and to read "Realizado por". Mismatches are reported on stderr, never silently resolved.
    """
    rows = read_sheet(path, 1)
    file_name = os.path.basename(path)
    header_year, header_month, performed_by = parse_header(rows)
    name_year, name_month = period_of(file_name)

    year = name_year if name_year is not None else header_year
    month = name_month if name_month is not None else header_month

    if year is None or month is None:
        sys.stderr.write(f'WARN: skipping "{file_name}": no readable period.\n')
        return None

    if (header_year, header_month) != (None, None) and (header_year, header_month) != (year, month):
        sys.stderr.write(
            f'WARN: "{file_name}" name says {year}-{month:02d} but the header says '
            f'{header_year}-{header_month if header_month else "??"} (stale template); using the name.\n')
    if not performed_by:
        performed_by = 'Resp. SGMA'
        sys.stderr.write(f'WARN: "{file_name}": no "Realizado por"; defaulting to "Resp. SGMA".\n')

    items = []
    section = None
    for row in rows:
        label = cell(row, 0)
        conforme = cell(row, 1) == 'X'
        non_conforme = cell(row, 2) == 'X'
        if label == '':
            continue
        heading = section_of(label)
        # A section heading carries no mark; an item that happens to contain a keyword would carry one.
        if heading is not None and not conforme and not non_conforme:
            section = heading
            continue
        if section is None:
            continue  # an item before any section heading: skip, nothing to attach it to
        if not conforme and not non_conforme:
            continue  # unmarked item this month -> no answer
        items.append({
            'section': section,
            'item_label': label,
            'result': 'conforme' if conforme else 'non_conforme',
            'observation': cell(row, 3),
        })

    return {'year': year, 'month': month, 'performed_by': performed_by, 'file_name': file_name, 'items': items}


def pick_winner(existing, candidate):
    """Of two parses for the same period, keep the more complete one (ties favour the non-"(1)" name)."""
    if len(candidate['items']) != len(existing['items']):
        return candidate if len(candidate['items']) > len(existing['items']) else existing
    # Tie on completeness: prefer the file without a "(1)" duplicate marker.
    cand_dup = '(1)' in candidate['file_name']
    exist_dup = '(1)' in existing['file_name']
    if cand_dup != exist_dup:
        return existing if cand_dup else candidate
    return existing


def normalize(input_dir):
    paths = sorted(glob.glob(os.path.join(input_dir, '**', '*.xlsx'), recursive=True))
    by_period = {}
    for path in paths:
        parsed = parse_file(path)
        if parsed is None:
            continue
        key = (parsed['year'], parsed['month'])
        if key in by_period:
            winner = pick_winner(by_period[key], parsed)
            loser = parsed if winner is by_period[key] else by_period[key]
            sys.stderr.write(
                f'WARN: duplicate period {key[0]}-{key[1]:02d}: "{parsed["file_name"]}" vs '
                f'"{by_period[key]["file_name"]}"; keeping "{winner["file_name"]}", '
                f'discarding "{loser["file_name"]}". CONFIRM with the centre.\n')
            by_period[key] = winner
        else:
            by_period[key] = parsed

    out = []
    for (year, month) in sorted(by_period):
        parsed = by_period[(year, month)]
        for item in parsed['items']:
            out.append({
                'year': year,
                'month': month,
                'performed_by': parsed['performed_by'],
                'section': item['section'],
                'item_label': item['item_label'],
                'result': item['result'],
                'observation': item['observation'],
            })
    return out


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows = normalize(sys.argv[1])
    fields = ['year', 'month', 'performed_by', 'section', 'item_label', 'result', 'observation']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
