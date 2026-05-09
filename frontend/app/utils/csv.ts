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

export const downloadCsv = (
  filename: string,
  headers: string[],
  rows: (string | number | null | undefined)[][],
): void => {
  // BOM so Excel opens UTF-8 cleanly.
  const csv = "﻿" + buildCsv(headers, rows);
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};
