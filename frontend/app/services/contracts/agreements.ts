import type {
  Agreement,
  AgreementInput,
  AgreementUpdate,
} from "~/types/agreement";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";

export interface AgreementWithRefs {
  agreement: Agreement;
  unit: Unit | null;
  property: Property | null;
  tenant: Tenant | null;
}

export interface AgreementsService {
  getAgreements(): Promise<Agreement[]>;
  getAgreementsWithRefs(): Promise<AgreementWithRefs[]>;
  getAgreement(id: string): Promise<Agreement | null>;
  /**
   * Tenant-shell scope: the tenant's *current* agreement — active, else the
   * most recent non-draft. API: `/me/agreement` (server knows who's asking).
   */
  getActiveAgreementForTenant(
    tenantId: string,
  ): Promise<AgreementWithRefs | null>;
  create(input: AgreementInput): Promise<Agreement>;
  /** Term edits while `pending_review`/`accepted` come back as `draft` — check the response status. */
  update(id: string, patch: AgreementUpdate): Promise<Agreement>;
  remove(id: string): Promise<void>;

  // ── Review flow (spec 2026-10-07 agreement-review) ──
  /** draft → pending_review; the tenant is emailed. */
  send(id: string): Promise<Agreement>;
  /** pending_review → draft without an answer. */
  withdraw(id: string): Promise<Agreement>;
  /** Tenant: pending_review → accepted. `tenantId` is only used by demo. */
  acceptForTenant(tenantId: string, agreementId: string): Promise<Agreement>;
  /** Tenant: pending_review → draft with a note the owner sees. */
  requestChangesForTenant(tenantId: string, agreementId: string, note: string): Promise<Agreement>;
}
