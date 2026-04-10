#!/usr/bin/env python3
import json
import os
import sys
from datetime import date, datetime


def stringify(value):
    if value is None:
        return ""
    if isinstance(value, (datetime, date)):
        return value.isoformat()
    if isinstance(value, float):
        if value.is_integer():
            return str(int(value))
        return ("%.15g" % value).strip()
    return str(value).strip()


def load_xlsx(path):
    import openpyxl

    wb = openpyxl.load_workbook(path, data_only=True, read_only=True)
    ws = wb[wb.sheetnames[0]]
    rows = []
    for row in ws.iter_rows(values_only=True):
        rows.append([stringify(cell) for cell in row])
    return ws.title, rows


def load_xls(path):
    import xlrd

    book = xlrd.open_workbook(path)
    sheet = book.sheet_by_index(0)
    rows = []
    for r in range(sheet.nrows):
        current = []
        for c in range(sheet.ncols):
            cell = sheet.cell(r, c)
            value = cell.value
            if cell.ctype == xlrd.XL_CELL_DATE:
                try:
                    dt = xlrd.xldate_as_datetime(value, book.datemode)
                    value = dt.date().isoformat()
                except Exception:
                    value = value
            current.append(stringify(value))
        rows.append(current)
    return sheet.name, rows


def detect_headers(rows):
    for idx, row in enumerate(rows[:10]):
        non_empty = [str(v).strip() for v in row if str(v).strip() != ""]
        if len(non_empty) >= 4:
            return idx, row
    return 0, rows[0] if rows else []


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Arquivo não informado."}))
        sys.exit(1)

    path = sys.argv[1]
    ext = os.path.splitext(path)[1].lower()

    try:
        if ext == '.xlsx':
            sheet_name, rows = load_xlsx(path)
        elif ext == '.xls':
            sheet_name, rows = load_xls(path)
        else:
            raise ValueError('Extensão não suportada')
    except Exception as exc:
        print(json.dumps({"error": str(exc)}))
        sys.exit(2)

    header_idx, header = detect_headers(rows)
    data_rows = rows[header_idx + 1:] if rows else []

    print(json.dumps({
        "sheet_name": sheet_name,
        "header_row_index": header_idx + 1,
        "headers": [str(item).strip() for item in header],
        "rows": data_rows,
    }, ensure_ascii=False))


if __name__ == '__main__':
    main()
