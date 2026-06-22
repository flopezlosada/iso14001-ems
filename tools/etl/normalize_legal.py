#!/usr/bin/env python3
"""Normalize the legal requirements register ("Requisitos Legales") into a clean CSV.

The register is a flat list keyed by RL-NN. Columns are loosely filled (the review date appears in
the "evaluación" or the "fecha" column depending on the row), so the date is picked up from either.
Compliance status is not reliably recorded in the source, so it is left to the entity default
(pending). Output schema:

    reference,sequence,legal_provision,scope,environmental_vector,specific_requirement,compliance_evidence,evaluation_frequency,last_reviewed_on,next_review_on

Usage: python3 normalize_legal.py <input.xlsx> <output.csv>
"""
import csv
import re
import sys

from xlsx import read_sheet, excel_serial_to_date

COL = {'ref': 0, 'provision': 1, 'scope': 2, 'vector': 3, 'requirement': 4,
       'evidence': 5, 'frequency': 6, 'evaluation': 7, 'last_review': 8, 'next_review': 9}

SCOPE_BY_TEXT = [
    (('europe', 'comunitar', 'ue', 'une-en'), 'european'),
    (('estatal', 'nacional', 'ley ', 'real decreto', 'rd '), 'national'),
    (('comunidad de madrid', 'cam', 'autonóm', 'autonom', 'regional'), 'regional'),
    (('municipal', 'local', 'ordenanza', 'ayuntamiento'), 'local'),
]


def serial_to_date(raw):
    s = raw.strip()
    try:
        float(s)
        return excel_serial_to_date(s)
    except ValueError:
        return ''


def map_scope(raw):
    low = raw.lower()
    for needles, value in SCOPE_BY_TEXT:
        if any(n in low for n in needles):
            return value
    return ''  # unknown -> importer quarantines (scope is required)


def map_frequency(raw):
    low = raw.lower()
    if 'mensual' in low:
        return 'monthly'
    if 'trimestral' in low:
        return 'quarterly'
    if 'semestral' in low or 'bianual' in low:
        return 'biannual'
    if 'anual' in low:
        return 'annual'
    return ''


def cell(row, key):
    idx = COL[key]
    return row[idx].strip() if idx < len(row) else ''


def normalize(path):
    out = []
    for row in read_sheet(path, 1)[2:]:  # rows 1-2 are title + header
        ref_raw = cell(row, 'ref')
        m = re.match(r'^RL-?\s*0*(\d+)', ref_raw, re.IGNORECASE)
        if not m:
            continue
        sequence = int(m.group(1))
        reference = f'RL-{sequence:02d}'

        # The review date lands in the evaluation or the fecha column depending on the row.
        last_review = serial_to_date(cell(row, 'evaluation')) or serial_to_date(cell(row, 'last_review'))

        out.append({
            'reference': reference,
            'sequence': sequence,
            'legal_provision': cell(row, 'provision'),
            'scope': map_scope(cell(row, 'scope')),
            'environmental_vector': cell(row, 'vector'),
            'specific_requirement': cell(row, 'requirement'),
            'compliance_evidence': cell(row, 'evidence'),
            'evaluation_frequency': map_frequency(cell(row, 'frequency')),
            'last_reviewed_on': last_review,
            'next_review_on': serial_to_date(cell(row, 'next_review')),
        })
    return out


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    rows = normalize(sys.argv[1])
    rows.sort(key=lambda r: r['sequence'])
    fields = ['reference', 'sequence', 'legal_provision', 'scope', 'environmental_vector',
              'specific_requirement', 'compliance_evidence', 'evaluation_frequency',
              'last_reviewed_on', 'next_review_on']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
