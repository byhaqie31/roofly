import type { AuditEntry, AuditQuery, Paginated } from "~/types/admin";

export interface AdminAuditService {
  list(query: AuditQuery): Promise<Paginated<AuditEntry>>;
  /** Full filtered export as CSV text (caller triggers the download). */
  exportCsv(query: AuditQuery): Promise<string>;
}
