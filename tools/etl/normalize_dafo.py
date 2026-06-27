#!/usr/bin/env python3
"""Normalize the DAFO analysis ("F.06.0 Análisis DAFO") into a clean CSV, one row per school year.

The source is a Word document (.docx), not an Excel sheet: a 2x2 table whose four content cells are
Debilidades / Amenazas / Fortalezas / Oportunidades (weaknesses / threats / strengths /
opportunities). Each cell holds several bullets; this script joins the bullets of a cell into a
single multi-line text (the entity stores each quadrant as one TEXT field).

The school year is derived from the document's "Fecha: … 20XX" line: a DAFO dated October 20XX
belongs to the school year "20XX-20XX+1". Documents with no detectable date are skipped with a
warning (never guessed).

Output schema:

    school_year,weaknesses,threats,strengths,opportunities

Usage: python3 normalize_dafo.py <output.csv> <input1.docx> [input2.docx ...]
"""
import csv
import html
import re
import sys
import zipfile

# Index of each quadrant cell within the document's <w:tc> table cells (cells 0/1 and 4/5 are the
# coloured header cells "DEBILIDADES"/"AMENAZAS"/"FORTALEZAS"/"OPORTUNIDADES").
QUADRANT_CELLS = {'weaknesses': 2, 'threats': 3, 'strengths': 6, 'opportunities': 7}


def cell_bullets(cell_xml):
    """Joins the non-empty paragraphs (bullets) of a table cell into one multi-line string."""
    bullets = []
    for para in re.split(r'</w:p>', cell_xml):
        text = ''.join(re.findall(r'<w:t\b[^>]*>(.*?)</w:t>', para, re.S))
        text = re.sub(r'\s+', ' ', html.unescape(text)).strip()
        if text:
            bullets.append(text)
    return '\n'.join(bullets)


def school_year(document_xml):
    """School year 'YYYY-YYYY+1' from the 'Fecha: … 20XX' line, or None if undetectable."""
    text = ' '.join(re.findall(r'<w:t\b[^>]*>(.*?)</w:t>', document_xml, re.S))
    # Anchor to the "Fecha: … 20XX" label so a stray 20XX elsewhere in the document is not mistaken
    # for the date (covers "Fecha: octubre 2025", "Fecha: 15/10/2025", etc.).
    m = re.search(r'Fecha[^0-9]{0,30}(20\d{2})', text, re.IGNORECASE)
    if not m:
        return None
    start = int(m.group(1))
    return f'{start}-{start + 1}'


def normalize_file(path):
    with zipfile.ZipFile(path) as z:
        xml = z.read('word/document.xml').decode('utf-8', 'ignore')

    year = school_year(xml)
    if year is None:
        sys.stderr.write(f'AVISO: sin año detectable en "{path}", se omite.\n')
        return None

    cells = re.findall(r'<w:tc>.*?</w:tc>', xml, re.S)
    # A 2x2 DAFO table has exactly 8 cells (4 coloured headers + 4 quadrant contents). Any other
    # count means the structure changed (merged cells, extra rows) and the fixed quadrant indices
    # would silently point at the wrong cell: omit with a diagnosable warning instead.
    if 8 != len(cells):
        sys.stderr.write(f'AVISO: estructura DAFO inesperada en "{path}" ({len(cells)} celdas, se esperaban 8), se omite.\n')
        return None

    row = {'school_year': year}
    for quadrant, index in QUADRANT_CELLS.items():
        row[quadrant] = cell_bullets(cells[index])
    return row


def main():
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    output, inputs = sys.argv[1], sys.argv[2:]

    rows = [r for r in (normalize_file(p) for p in inputs) if r is not None]
    rows.sort(key=lambda r: r['school_year'])

    fields = ['school_year', 'weaknesses', 'threats', 'strengths', 'opportunities']
    with open(output, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    sys.stderr.write(f'{len(rows)} análisis DAFO escritos en {output}.\n')


if __name__ == '__main__':
    main()
