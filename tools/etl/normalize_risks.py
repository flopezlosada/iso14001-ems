#!/usr/bin/env python3
"""Normalize the risks-and-opportunities register ("F.08.0") into a clean CSV.

The workbook holds one "Determinación de Riesgos y Oportunidades" sheet per school year plus a
"Criterios de Evaluación" sheet. That criteria sheet carries a GENERIC ISO 9001 rubric (GRAVEDAD/
UTILIDAD, "más de 10 ocurrencias al año", "requisitos del cliente") that contradicts the SGA's own
PC.03.0 procedure (Probability 1-3 × Impact 1-3) — it is template contamination and is IGNORED. We
only read the determination sheets, detected by their "DESCRIPCIÓN | Riesgo / Oportunidad | ..."
header row.

Each data row is one risk/opportunity with its assessment for that year and (optionally) a single
action. Column layout (after the two title rows):

    A DESCRIPCIÓN | B Riesgo/Oportunidad | C Proceso/ÁREA | D Probabilidad(1-3) | E Impacto(1-3) |
    F PxI | G CATEGORIA | H MOTIVO/JUSTIFICACIÓN | I ACCIONES | J RESP. | K PLAZO |
    L EVALUACION | M RESP. | N FECHA

Deliberately dropped: F (PxI) and G (CATEGORIA) are recomputed by the importer from the procedure
bands, never trusted from the sheet (the sheet even disagrees with itself — a 2×2=4 row tagged
TRIVIAL). M (the efficacy reviewer) has no home in the model. The year's school term ("exercise",
e.g. "2024-2025") is derived per sheet from its name, falling back to the file name.

Dates (K PLAZO, N FECHA) arrive as Excel serials or as free text ("ANUAL", "MEJORA CONTINUA"). We
keep a serial as an ISO date and leave free text as-is for the deadline; for FECHA, a serial feeds
evaluated_at while free text is folded into the efficacy note so nothing is lost. This script only
extracts and cleans; the domain rule (score = probability × impact → category) lives in the PHP
importer, where it is unit-tested.

Output schema (one row per risk/opportunity per year):

    exercise,type,process_area,description,probability,impact,justification,
    action,responsible,deadline,efficacy,evaluated_at

Usage: python3 normalize_risks.py <input.xlsx> <output.csv> [exercise]
       (exercise overrides the per-sheet derivation, e.g. "2024-2025")
"""
import csv
import re
import sys
import zipfile

from xlsx import sheet_names, read_sheet, clean_number, excel_serial_to_date

# Header cells that mark a "Determinación" data sheet (matched on the third row, column A/B).
HEADER_DESCRIPTION = 'DESCRIPCIÓN'
HEADER_TYPE = 'Riesgo / Oportunidad'

# Raw "Riesgo / Oportunidad" cell -> RiskOpportunityType value. The sheet uses the plural
# "Oportunidades"; we accept the singular too. Anything else is left blank and rejected downstream.
TYPE_MAP = {
    'riesgo': 'risk',
    'oportunidad': 'opportunity',
    'oportunidades': 'opportunity',
}


def cell(row, i):
    """Trimmed value of column i, or '' when the row is shorter."""
    return row[i].strip() if i < len(row) else ''


def is_data_sheet(rows):
    """Whether a sheet is a determination sheet (carries the risk register header)."""
    for row in rows[:5]:
        if cell(row, 0) == HEADER_DESCRIPTION and cell(row, 1).startswith('Riesgo'):
            return True
    return False


def derive_exercise(sheet_name, file_name):
    """School term "20AA-20BB" from a "AA-BB"/"AA_BB" pattern in the sheet name, else the file name.

    Returns '' when neither carries a recognizable term (the importer then rejects the rows).
    """
    for source in (sheet_name, file_name):
        match = re.search(r'(\d{2})[-_/](\d{2})', source or '')
        if match:
            return f'20{match.group(1)}-20{match.group(2)}'
    return ''


def map_type(raw):
    """RiskOpportunityType value for the raw "Riesgo/Oportunidad" cell, or '' when unrecognized."""
    return TYPE_MAP.get(raw.strip().lower(), '')


def canonical_area(raw):
    """Canonical process-area name, collapsing punctuation/spacing typos onto one value.

    The sheet writes the same area three ways — "RESPO. SGMA", "RESPO.SGMA", "RESPO, SGMA" — so we
    drop the stray dots/commas and collapse runs of whitespace, uppercasing the result. This merges
    those into a single "RESPO SGMA" without touching genuinely distinct areas (TECNICO AMBIENTAL,
    RESPO MANTENIMIENTO, AREA FORMACION). The directora can still rename it from the UI afterwards.
    """
    cleaned = re.sub(r'[.,;]', ' ', raw)
    return re.sub(r'\s+', ' ', cleaned).strip().upper()


def as_date_or_text(raw):
    """Split a date-ish cell into (iso_date, leftover_text).

    An Excel serial becomes an ISO date with no leftover; free text stays as leftover with no date;
    an empty cell yields ('', '').
    """
    raw = raw.strip()
    if raw == '':
        return '', ''
    number = clean_number(raw)
    if number != '':
        return excel_serial_to_date(number), ''
    return '', raw


def fold_efficacy(evaluation, fecha_text):
    """Combine the EVALUACION cell with any non-date FECHA text into one efficacy note.

    The sheet sometimes records the efficacy outcome in the FECHA column ("MEJORA CONTINUA",
    "CERTIFICACION OK") instead of a date; folding it here keeps that information.
    """
    parts = [p for p in (evaluation.strip(), fecha_text.strip()) if p]
    return '. '.join(parts)


def normalize(path, exercise_override):
    with zipfile.ZipFile(path) as z:
        names = sheet_names(z)
    file_name = path.rsplit('/', 1)[-1]

    out = []
    for index, sheet_name in enumerate(names, start=1):
        rows = read_sheet(path, index)
        if not is_data_sheet(rows):
            continue  # skips the "Criterios de Evaluación" sheet (ISO 9001 contamination)
        exercise = exercise_override or derive_exercise(sheet_name, file_name)

        for row in rows:
            description = cell(row, 0)
            raw_type = cell(row, 1)
            # Data rows start once we are past the title/header band: a real description plus a
            # recognizable type. Header rows ("DESCRIPCIÓN") and blank rows fall through.
            type_value = map_type(raw_type)
            if description == '' or type_value == '':
                continue

            deadline_date, deadline_text = as_date_or_text(cell(row, 10))
            evaluated_at, fecha_text = as_date_or_text(cell(row, 13))
            out.append({
                'exercise': exercise,
                'type': type_value,
                'process_area': canonical_area(cell(row, 2)),  # merges "RESPO. SGMA"/"RESPO,SGMA"/...
                'description': description,
                'probability': clean_number(cell(row, 3)),
                'impact': clean_number(cell(row, 4)),
                'justification': cell(row, 7),
                'action': cell(row, 8),
                'responsible': cell(row, 9),
                # A serial deadline is kept as an ISO date; free text ("ANUAL", "DIC") stays verbatim.
                'deadline': deadline_date or deadline_text,
                'efficacy': fold_efficacy(cell(row, 11), fecha_text),
                'evaluated_at': evaluated_at,
            })
    return out


def main():
    if len(sys.argv) not in (3, 4):
        sys.exit(__doc__)
    rows = normalize(sys.argv[1], sys.argv[3] if len(sys.argv) == 4 else None)
    fields = ['exercise', 'type', 'process_area', 'description', 'probability', 'impact',
              'justification', 'action', 'responsible', 'deadline', 'efficacy', 'evaluated_at']
    with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f'wrote {len(rows)} rows -> {sys.argv[2]}')


if __name__ == '__main__':
    main()
