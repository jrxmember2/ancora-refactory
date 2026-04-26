import json
import sys


def main():
    if len(sys.argv) < 3:
        raise RuntimeError("Uso: write_generic_xlsx.py <input.json> <output.xlsx>")

    input_path = sys.argv[1]
    output_path = sys.argv[2]

    with open(input_path, "r", encoding="utf-8") as fh:
        payload = json.load(fh)

    try:
        import openpyxl
    except Exception as exc:
        raise RuntimeError(f"openpyxl indisponivel: {exc}") from exc

    workbook = openpyxl.Workbook()
    sheet = workbook.active
    sheet.title = str(payload.get("sheet_name") or "Relatorio")[:31]

    headers = list(payload.get("headers") or [])
    rows = list(payload.get("rows") or [])

    if headers:
        sheet.append(headers)

    for row in rows:
        sheet.append(list(row))

    for column in sheet.columns:
        max_len = 0
        col_letter = column[0].column_letter
        for cell in column:
            value = "" if cell.value is None else str(cell.value)
            max_len = max(max_len, len(value))
        sheet.column_dimensions[col_letter].width = min(max(max_len + 2, 12), 48)

    workbook.save(output_path)


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)
