#!/usr/bin/env python3
"""ETL etapa 1 (documentos redactados): convierte los .docx del centro (política, manual y
procedimientos) a HTML limpio en fixtures/real/documents/<CODIGO>.html, usando pandoc.

Es el equivalente, para los documentos de texto, de los normalize_*.py (que producen CSV): aquí la
"fuente sucia" son los .docx y la salida limpia es el cuerpo HTML que el comando de importación
(app:import-documents) carga en la revisión inicial de cada documento.

El membrete/logos (imágenes) NO se conserva aquí: lo añade la plantilla del PDF. La conversión los
extrae a un .media aparte que el importador ignora (y el saneado HTML del importador descarta <img>).

Solo stdlib + el binario `pandoc` (instalado en local para el cutover; no en el hosting). El HTML de
salida va a fixtures/real/ (gitignored: contiene el nombre del centro). Reproducible: dados los .docx,
regenera el contenido idéntico, así nada depende de una conversión manual irrepetible.

Uso:
    python3 tools/etl/convert_documents.py <dir_documentacion_base> [<out_dir=fixtures/real/documents>]
"""

from __future__ import annotations

import datetime
import re
import subprocess
import sys
from pathlib import Path

# Códigos de los 16 documentos redactados. Los códigos ISO NO son PII (el nombre del centro sí, por
# eso no se hardcodea ninguna ruta: los .docx se localizan buscando por código bajo el dir base).
PROCEDURE_CODES = [
    "PC.01.0", "PC.02.0", "PC.03.0", "PC.04.0", "PC.05.0", "PC.09.0", "PC.10.0", "PC-06.03",
    "PG-06.01", "PG-06.04", "PG-07.01", "PG-08.01", "PG-08.02", "PG-09.03.00",
]


def _date_in_name(p: Path) -> datetime.date:
    """The dd.mm.yyyy date embedded in a filename, or the minimum date if it has none. Parsed
    properly (not lexicographically: '25.06.2024' must sort BEFORE '01.01.2025')."""
    match = re.search(r"(\d{2})\.(\d{2})\.(\d{4})", p.name)
    if match is None:
        return datetime.date.min
    return datetime.date(int(match.group(3)), int(match.group(2)), int(match.group(1)))


def newest_docx_by_prefix(base: Path, prefix: str) -> Path | None:
    """The .docx whose filename starts with `prefix`; if several, the one with the latest date in
    its name (the most recent revision)."""
    candidates = [p for p in base.rglob("*.docx") if p.name.startswith(prefix)]
    return max(candidates, key=_date_in_name) if candidates else None


def find_policy(base: Path) -> Path | None:
    """The environmental policy .docx (named by title, not by code)."""
    candidates = [p for p in base.rglob("*.docx") if re.search(r"ol[ií]tica\s+medioambiental", p.name, re.I)]
    return candidates[0] if candidates else None


def convert(src: Path, out_html: Path, media_dir: Path) -> None:
    subprocess.run(
        ["pandoc", str(src), "-f", "docx", "-t", "html", "--wrap=none",
         f"--extract-media={media_dir}", "-o", str(out_html)],
        check=True,
    )


def main(argv: list[str]) -> int:
    if len(argv) < 2:
        print(__doc__)
        return 2

    base = Path(argv[1])
    if not base.is_dir():
        print(f"No existe el directorio de documentación: {base}", file=sys.stderr)
        return 1

    out = Path(argv[2]) if len(argv) > 2 else Path("fixtures/real/documents")
    out.mkdir(parents=True, exist_ok=True)
    media = out / ".media"

    mapping: dict[str, Path | None] = {
        "PA.01.0": find_policy(base),
        "MA-04.01.01": newest_docx_by_prefix(base, "MA-04.01.01"),
    }
    for code in PROCEDURE_CODES:
        mapping[code] = newest_docx_by_prefix(base, code)

    converted, missing = 0, []
    for code, src in mapping.items():
        if src is None or not src.exists():
            missing.append(code)
            continue
        convert(src, out / f"{code}.html", media)
        converted += 1

    print(f"Convertidos {converted}/{len(mapping)} documentos a {out}/")
    if missing:
        print("FALTAN (no encontrados bajo el dir base): " + ", ".join(missing), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
