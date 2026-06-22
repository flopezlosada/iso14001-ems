#!/usr/bin/env python3
"""One-off profiling of the chronological waste register, to size the data-quality problems before
writing the real normalizer. Prints counts and a few examples per category. Read-only.

Usage: python3 analyze_waste.py <input.xlsx>
"""
import re
import sys
from collections import Counter

from xlsx import read_sheet

LER_RE = re.compile(r'^(\d{6})(-\d+)?$')


def classify_ler(raw):
    haz = '*' in raw
    v = raw.replace('*', '').replace(' ', '').strip()
    if v.endswith('.0'):
        v = v[:-2]
    if v == '':
        return 'empty', haz
    if LER_RE.match(v):
        return 'valid', haz
    return 'corrupted', haz  # e.g. Excel turned the code into a date serial


def classify_qty(raw):
    if raw.strip() == '':
        return 'empty'
    try:
        float(raw)
        return 'numeric'
    except ValueError:
        return 'text'  # e.g. "43 bolsones"


def classify_date(raw):
    s = raw.strip()
    if s == '':
        return 'empty'
    try:
        float(s)
        return 'serial'
    except ValueError:
        pass
    if re.search(r'\d{2}/\d{2}/\d{4}.*-.*\d{2}/\d{2}/\d{4}', s):
        return 'range'
    if re.match(r'^\d{2}/\d{2}/\d{4}$', s):
        return 'dmy'
    return 'text'  # e.g. "Junio 2024 (curso 23-24)"


def main():
    rows = read_sheet(sys.argv[1], 1)
    ler, qty, date, mgr = Counter(), Counter(), Counter(), Counter()
    haz_count = 0
    total = 0
    examples = {'corrupted': [], 'qty_text': [], 'date_range': [], 'date_text': []}
    for r in rows[2:]:  # rows 1-2 are title + header
        if not any(c.strip() for c in r):
            continue
        total += 1
        lc, haz = classify_ler(r[0] if len(r) > 0 else '')
        ler[lc] += 1
        if haz:
            haz_count += 1
        qc = classify_qty(r[4] if len(r) > 4 else '')
        qty[qc] += 1
        dc = classify_date(r[3] if len(r) > 3 else '')
        date[dc] += 1
        mgr['present' if (len(r) > 14 and r[14].strip()) else 'empty'] += 1
        if lc == 'corrupted' and len(examples['corrupted']) < 6:
            examples['corrupted'].append(f'{r[0]!r} ({r[1][:30]})')
        if qc == 'text' and len(examples['qty_text']) < 6:
            examples['qty_text'].append(f'{r[4]!r} ({r[1][:30]})')
        if dc == 'range' and len(examples['date_range']) < 4:
            examples['date_range'].append(repr(r[3]))
        if dc == 'text' and len(examples['date_text']) < 4:
            examples['date_text'].append(repr(r[3]))

    print(f'TOTAL data rows: {total}')
    print(f'LER:      {dict(ler)}  (hazardous marked: {haz_count})')
    print(f'QUANTITY: {dict(qty)}')
    print(f'DATE:     {dict(date)}')
    print(f'MANAGER:  {dict(mgr)}')
    for k, v in examples.items():
        print(f'  ex {k}: {v}')


if __name__ == '__main__':
    main()
