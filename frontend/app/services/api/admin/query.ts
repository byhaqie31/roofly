/** Drop undefined/false/empty so the query string only carries active filters; true → 1 for boolean flags. */
export const cleanQuery = (q: Record<string, unknown>): Record<string, unknown> =>
  Object.fromEntries(
    Object.entries(q)
      .filter(([, v]) => v !== undefined && v !== "" && v !== false)
      .map(([k, v]) => [k, v === true ? 1 : v]),
  );
