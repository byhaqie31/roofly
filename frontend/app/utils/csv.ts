/**
 * Tiny client-side CSV download — builds a Blob and triggers a download anchor.
 * No backend / file-storage dependency.
 *
 * Cells are escaped per RFC 4180: wrap in quotes if the cell contains comma,
 * quote, or newline; double internal quotes.
 */

const escapeCell = (raw: unknown): string => {
  const s = raw == null ? "" : String(raw);
  if (/[",\n\r]/.test(s)) {
    return `"${s.replace(/"/g, '""')}"`;
  }
  return s;
};

export const buildCsv = (
  headers: string[],
  rows: (string | number | null | undefined)[][],
): string => {
  const lines = [headers.map(escapeCell).join(",")];
  for (const row of rows) {
    lines.push(row.map(escapeCell).join(","));
  }
  return lines.join("\n");
};

/** Triggers a browser download for ready-made CSV text (BOM-prefixed so Excel opens UTF-8 cleanly). */
export const downloadCsvText = (filename: string, csv: string): void => {
  const blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};

export const downloadCsv = (
  filename: string,
  headers: string[],
  rows: (string | number | null | undefined)[][],
): void => downloadCsvText(filename, buildCsv(headers, rows));
