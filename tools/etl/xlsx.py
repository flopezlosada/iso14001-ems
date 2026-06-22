"""Minimal stdlib-only XLSX reader for the real-data ETL.

We deliberately avoid heavyweight dependencies (openpyxl / PhpSpreadsheet): the production
import command reads the *clean CSV* this layer produces, so the messy xlsx parsing lives here,
offline, where it can be reviewed. Numbers come back as their raw XML text (e.g. "44958.0"),
strings are resolved through the shared-strings table.
"""
import zipfile
import re
from xml.etree import ElementTree as ET

NS = '{http://schemas.openxmlformats.org/spreadsheetml/2006/main}'


def _col_row(ref):
    m = re.match(r'([A-Z]+)(\d+)', ref)
    col, row = m.group(1), int(m.group(2))
    n = 0
    for c in col:
        n = n * 26 + (ord(c) - 64)
    return n - 1, row - 1


def _shared_strings(z):
    try:
        x = ET.fromstring(z.read('xl/sharedStrings.xml'))
    except KeyError:
        return []
    return [''.join(t.text or '' for t in si.iter(f'{NS}t')) for si in x.findall(f'{NS}si')]


def sheet_names(z):
    wb = ET.fromstring(z.read('xl/workbook.xml'))
    return [s.get('name') for s in wb.iter(f'{NS}sheet')]


def read_sheet(path, sheet_index):
    """Return the sheet (1-based index) as a list of rows, each a list of trimmed cell strings."""
    z = zipfile.ZipFile(path)
    shared = _shared_strings(z)
    x = ET.fromstring(z.read(f'xl/worksheets/sheet{sheet_index}.xml'))
    grid = {}
    max_r = max_c = 0
    for c in x.iter(f'{NS}c'):
        ref = c.get('r')
        if ref is None:
            continue
        t = c.get('t')
        v = c.find(f'{NS}v')
        inline = c.find(f'{NS}is')
        if t == 's' and v is not None:
            val = shared[int(v.text)]
        elif t == 'inlineStr' and inline is not None:
            val = ''.join(tt.text or '' for tt in inline.iter(f'{NS}t'))
        elif v is not None:
            val = v.text
        else:
            val = ''
        ci, ri = _col_row(ref)
        grid[(ri, ci)] = (val or '').replace('\n', ' ').strip()
        max_r, max_c = max(max_r, ri), max(max_c, ci)
    return [[grid.get((r, c), '') for c in range(max_c + 1)] for r in range(max_r + 1)]


def count_sheets(z):
    return len(sheet_names(z))


def excel_serial_to_date(serial):
    """Convert an Excel date serial (e.g. "45303.0") to an ISO date string (YYYY-MM-DD).

    Excel's day 0 is 1899-12-30 (it wrongly treats 1900 as a leap year).
    """
    from datetime import date, timedelta
    return (date(1899, 12, 30) + timedelta(days=int(float(serial)))).isoformat()


def clean_number(raw):
    """Excel often stores integers as "4380.0"; return a tidy decimal string or '' if not numeric."""
    if raw is None or raw == '':
        return ''
    try:
        f = float(raw)
    except ValueError:
        return ''
    if f == int(f):
        return str(int(f))
    return repr(round(f, 6)).rstrip('0').rstrip('.')
