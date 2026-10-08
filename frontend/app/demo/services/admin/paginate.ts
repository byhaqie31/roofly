import type { Paginated } from "~/types/admin";

/** Shared in-memory pagination wrapper for the admin demo adapters. */
export const paginate = <T>(rows: T[], page = 1, perPage = 20): Paginated<T> => ({
  data: rows.slice((page - 1) * perPage, page * perPage),
  meta: { page, perPage, total: rows.length, lastPage: Math.max(1, Math.ceil(rows.length / perPage)) },
});
