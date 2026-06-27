# ETL de datos reales (cutover)

Importación de los datos históricos reales (3 años) del centro a la base de datos, en
**dos etapas** para separar la limpieza (sucia, offline, revisable) de la carga (determinista,
idempotente, testeada, apta para producción).

```
.xlsx reales  ──(1) normalizar──▶  CSV limpio en fixtures/real/  ──(2) importar──▶  base de datos
   (sucios)        tools/etl/*.py        (gitignored, PII)            app:import-real-data
```

## Etapa 1 — Normalizar (offline)

Scripts Python (solo stdlib, sin dependencias). Leen el `.xlsx` real y emiten un CSV limpio con un
esquema estable. Aquí vive el criterio sobre los datos sucios (códigos LER que Excel convirtió en
fechas, cantidades en "bolsones", fechas en texto, etc.); lo no convertible se preserva como `null`
+ texto en `notes`, nunca se descarta.

```bash
DOC="../ruta/a/documentacion_base"

python3 tools/etl/normalize_consumptions.py \
  "$DOC/00.PLAN/.../CONSUMOS LUZ AGUA GASOIL PAPEL TONER 2023 24 25 26.xlsx" \
  fixtures/real/consumptions.csv

python3 tools/etl/normalize_waste.py \
  "$DOC/01.IMPLEMENTACIÓN (Hacer)/.../Archivo cronológico RESIDUOS.xlsx" \
  fixtures/real/waste.csv

python3 tools/etl/normalize_nc.py \
  "$DOC/03.MEJORA (Actuar).../F.11.0 LISTADO DE CONTROL DE NC.xlsx" \
  fixtures/real/non_conformities.csv

python3 tools/etl/normalize_aspects.py \
  "$DOC/00.PLAN/.../RG-06.01.01 Evaluación Aspectos Ambientales ...xlsx" \
  fixtures/real/aspects.csv   # [año] opcional; por defecto el de la cabecera "Fecha: ... 20XX"

python3 tools/etl/normalize_risks.py \
  "$DOC/00.PLAN/.../F.08.0 GESTIÓN DE RIESGOS Y OPORTUNIDADES ....xlsx" \
  fixtures/real/risks.csv     # procesa todas las hojas de "Determinación" (una por curso) e
                              # IGNORA la hoja "Criterios" (rúbrica ISO 9001 contaminante); el
                              # curso se deriva del nombre de hoja/fichero. [curso] opcional.

python3 tools/etl/normalize_training.py \
  "$DOC/01.IMPLEMENTACIÓN (Hacer)/.../RRHH/F.03.0 PLAN ANUAL DE FORMACIÓN 2023-26.xlsx" \
  fixtures/real/training.csv   # una fila por acción formativa (una hoja por año). Las fechas E/G
                              # vienen como texto sucio ("octubre 2023", "23 al 27/10/23", "a la
                              # semana de su incorporación"): se pasan VERBATIM y la convención de
                              # normalización (mes->día 1, rango->inicio, no normalizable->cuarentena)
                              # vive en el importer PHP, donde está unit-testeada.

python3 tools/etl/normalize_operational_control.py \
  "$DOC/01.IMPLEMENTACIÓN (Hacer)/.../CONTROL OPERACIONAL /CONTROLES INTERNOS" \
  fixtures/real/operational_control.csv   # recorre el DIRECTORIO recursivamente (un .xlsx por mes);
                              # el período se toma del NOMBRE del fichero (el header suele estar
                              # estancado en una plantilla, p. ej. "FEBRERO" en medio 2024); los
                              # desajustes y los meses duplicados (NOV. 2025 vs NOV. 2025(1)) se
                              # avisan por stderr para que el centro los confirme. Una fila por ítem
                              # marcado; los ítems sin marcar no generan fila.
```

(Los normalizadores de requisitos legales, objetivos, proveedores e indicadores siguen el mismo
patrón; ver `tools/etl/normalize_*.py`.)

`fixtures/real/` está en `.gitignore`: los CSV contienen datos reales (PII / NIF del centro) y no
se commitean. `analyze_waste.py` es un perfilador de apoyo (solo lectura), no forma parte del flujo.

## Etapa 2 — Importar (comando)

```bash
# Validar sin escribir (recomendado antes del cutover real):
php bin/console app:import-real-data all --dry-run

# Cargar un dataset o todos:
php bin/console app:import-real-data consumptions
php bin/console app:import-real-data all
```

Propiedades:

- **Idempotente**: se puede re-ejecutar. Clave natural por dataset — consumos `(tipo, año, mes)`,
  residuos hash del contenido de la fila, NC la referencia `NC.<origen>.<año>.<NN>`, aspectos el
  nombre del aspecto y su evaluación por `(aspecto, año)`, riesgos `(tipo, descripción)` con su
  valoración por `(riesgo, curso)`. La significancia del aspecto NO se lee de la hoja: la recalcula
  `AspectSignificanceCalculator`; análogamente, el score y la categoría del riesgo NO se leen de la
  hoja (que incluso se contradice): los recalcula `RiskScoreCalculator` (la regla de la app, fuente
  única de verdad). Control operacional: el catálogo de ítems se siembra desde el texto real de la
  hoja `(sección, etiqueta)`, la inspección por `(año, mes)` y cada respuesta por `(inspección,
  ítem)`; las observaciones por línea se pliegan en la nota de la inspección (`label: observación`).
  Formación (F.03.0) `(año, descripción, destinatarios)`: la hoja no tiene código ni id y la misma
  descripción ("curso iso 14001") se repite en un año para distintos destinatarios, así que el
  destinatario forma parte de la identidad para no fundir acciones distintas; las fechas se
  normalizan con la convención acordada (mes -> día 1, rango -> día de inicio) y una fecha no
  normalizable manda la fila a cuarentena en vez de inventarla.
- **Validado**: cada entidad pasa por el Validator antes de persistir.
- **Cuarentena**: las filas que no validan se escriben en `fixtures/real/<dataset>.rejected.csv`
  con el motivo, y el comando termina con código de error. Nada se descarta en silencio.
- No requiere PhpSpreadsheet: el comando solo lee CSV.
