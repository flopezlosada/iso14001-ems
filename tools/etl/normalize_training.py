#!/usr/bin/env python3
"""Normalize the annual training plan ("F.03.0 PLAN ANUAL DE FORMACIÓN") into a clean CSV.

The workbook holds one sheet per school year (2023, 2024, 2025, 2026), each with the same table:

    A DESCRIPCIÓN DEL CURSO | B EXT/INT | C PROFESIONALES/PUESTOS ... A LOS QUE VA DIRIGIDO |
    D OBJETIVOS | E FECHA PREVISTA DE EJECUCIÓN | F METODOLOGÍA DE FORMACIÓN |
    G FECHA REAL DE EJECUCIÓN | H EVALUACIÓN DE LA EFICACIA DE LA ACCIÓN FORMATIVA

Following the same philosophy as the other normalizers (see normalize_risks.py): this script ONLY
extracts and cleans. The two date columns (E, G) are notoriously dirty — they hold free text such
as "octubre 2023", "23 al 27/10/23" or "A LA SEMANA DE SU INCORPORACIÓN" — but turning that text
into a calendar date is a DOMAIN CONVENTION the centre must confirm (month -> first day, range ->
start day, non-normalizable -> quarantine). That rule therefore lives in the PHP importer, where it
is unit-tested; here the date cells are passed through VERBATIM. The only mechanical conversion done
here is an Excel date serial (a bare number like "45303.0") to an ISO date, which is unambiguous.

Output schema (one row per training action):

    plan_year,description,type,target_audience,objectives,planned_date,methodology,
    actual_date,efficacy_evaluation

Usage: python3 normalize_training.py <input.xlsx> <output.csv>
"""
import csv
import re
import sys
import zipfile

from xlsx import sheet_names, read_sheet, clean_number, excel_serial_to_date

# Column layout of the training table (0-based), once we are past the title/header band.
COL_DESCRIPTION = 0
COL_TYPE = 1
COL_AUDIENCE = 2
COL_OBJECTIVES = 3
COL_PLANNED_DATE = 4
COL_METHODOLOGY = 5
COL_ACTUAL_DATE = 6
COL_EFFICACY = 7

# Smallest value treated as an Excel date serial (~1982); guards against reading a stray small
# number (e.g. a bare "2023") as a date.
MIN_DATE_SERIAL = 30000


def cell(row, i):
    """Trimmed value of column i, or '' when the row is shorter."""
    return row[i].strip() if i < len(row) else ''


def is_header_row(row):
    """Whether a row is the table header (its first cell starts the "DESCRIPCIÓN" title)."""
    return cell(row, COL_DESCRIPTION).upper().startswith('DESCRIP')


def derive_year(rows):
    """Plan year for a sheet, taken from its "AÑO 20XX" title (or any 4-digit year), else ''.

    Returns '' when the sheet carries no recognizable year; the importer then rejects those rows
    instead of guessing a year.
    """
    for row in rows[:5]:  # the "AÑO 20XX" title lives in the header band, never in the data rows
        for value in row:
            match = re.search(r'\b(20\d{2})\b', value or '')
            if match:
                return match.group(1)
    return ''


def date_cell(raw):
    """Pass a date cell through verbatim, converting only an unambiguous Excel serial to ISO.

    Free text ("octubre 2023", "23 al 27/10/23", "A LA SEMANA DE SU INCORPORACIÓN") is left exactly
    as written: the PHP importer applies the agreed normalization convention or sends the row to
    quarantine. Nothing is invented or dropped here.
    """
    raw = raw.strip()
    if raw == '':
        return ''
    if clean_number(raw) != '':
        try:
            if float(raw) >= MIN_DATE_SERIAL:
                return excel_serial_to_date(raw)
        except ValueError:
            pass
    return raw


def normalize(path):
    with zipfile.ZipFile(path) as z:
        names = sheet_names(z)

    out = []
    for index in range(1, len(names) + 1):
        rows = read_sheet(path, index)
        year = derive_year(rows)

        seen_header = False
        for row in rows:
            if is_header_row(row):
                seen_header = True
                continue
            if not seen_header:
                continue  # still in the title band (logo, "AÑO 20XX")
            description = cell(row, COL_DESCRIPTION)
            if description == '':
                continue  # blank separator row
            out.append({
                'plan_year': year,
                'description': description,
                'type': cell(row, COL_TYPE).lower(),  # "int"/"ext"/"int/ext"; the importer maps it
                'target_audience': cell(row, COL_AUDIENCE),
                'objectives': cell(row, COL_OBJECTIVES),
                'planned_date': date_cell(cell(row, COL_PLANNED_DATE)),  # verbatim; PHP normalizes
                'methodology': cell(row, COL_METHODOLOGY),
                'actual_date': date_cell(cell(row, COL_ACTUAL_DATE)),    # verbatim; PHP normalizes
                'efficacy_evaluation': cell(row, COL_EFFICACY),
            })
    return out


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows = normalize(sys.argv[1])
    fields = ['plan_year', 'description', 'type', 'target_audience', 'objectives',
              'planned_date', 'methodology', 'actual_date', 'efficacy_evaluation']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
