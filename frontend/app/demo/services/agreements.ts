import type { Agreement } from "~/types/agreement";
import type {
  AgreementsService,
  AgreementWithRefs,
} from "~/services/contracts/agreements";
import { agreementsMock } from "~/demo/data/agreements";
import { propertiesMock } from "~/demo/data/properties";
import { unitsMock } from "~/demo/data/units";
import { tenantsMock } from "~/demo/data/tenants";
import { resolvePayoutAccount } from "~/demo/services/payoutAccounts";

const hydrate = (a: Agreement): AgreementWithRefs => {
  const unit = unitsMock.find((u) => u.id === a.unitId) ?? null;
  const property = unit
    ? (propertiesMock.find((p) => p.id === unit.propertyId) ?? null)
    : null;
  const tenant = tenantsMock.find((t) => t.id === a.tenantId) ?? null;
  return {
    agreement: structuredClone(a),
    unit: unit ? structuredClone(unit) : null,
    property: property ? structuredClone(property) : null,
    tenant: tenant ? structuredClone(tenant) : null,
    payoutAccount: resolvePayoutAccount(a.payoutAccountId),
  };
};

export const demoAgreements: AgreementsService = {
  async getAgreements() {
    return structuredClone(agreementsMock);
  },

  async getAgreementsWithRefs() {
    return agreementsMock.map(hydrate);
  },

  async getAgreement(id) {
    const found = agreementsMock.find((a) => a.id === id);
    return found ? structuredClone(found) : null;
  },

  async getActiveAgreementForTenant(tenantId) {
    const mine = agreementsMock.filter((a) => a.tenantId === tenantId);
    // Same precedence as the API: active, then awaiting review / agreed, then history.
    const current =
      mine.find((a) => a.status === "active") ??
      mine.find((a) => a.status === "pending_review" || a.status === "accepted") ??
      mine
        .filter((a) => a.status !== "draft")
        .sort((a, b) => b.startDate.localeCompare(a.startDate))[0] ??
      null;
    return current ? hydrate(current) : null;
  },

  async create(input) {
    const created: Agreement = {
      id: crypto.randomUUID(),
      ...input,
      createdAt: new Date().toISOString(),
    };
    agreementsMock.push(created);
    return structuredClone(created);
  },

  async update(id, patch) {
    const idx = agreementsMock.findIndex((a) => a.id === id);
    if (idx === -1) throw new Error(`Agreement ${id} not found`);
    const before = agreementsMock[idx]!;
    let merged: Agreement = { ...before, ...patch };
    // Mirrors the API: a term edit while sent / accepted voids the review.
    const underReview = before.status === "pending_review" || before.status === "accepted";
    const termChanged = TERM_KEYS.some((k) => k in patch && patch[k] !== before[k]);
    if (underReview && termChanged) {
      merged = { ...merged, status: "draft", sentAt: null, acceptedAt: null };
    }
    agreementsMock[idx] = merged;
    return structuredClone(merged);
  },

  async remove(id) {
    const idx = agreementsMock.findIndex((a) => a.id === id);
    if (idx !== -1) agreementsMock.splice(idx, 1);
  },

  async send(id) {
    return transition(id, "draft", (a) => ({ ...a, status: "pending_review", sentAt: new Date().toISOString(), reviewNote: null }));
  },

  async withdraw(id) {
    return transition(id, "pending_review", (a) => ({ ...a, status: "draft", sentAt: null }));
  },

  async acceptForTenant(_tenantId, agreementId) {
    return transition(agreementId, "pending_review", (a) => ({ ...a, status: "accepted", acceptedAt: new Date().toISOString() }));
  },

  async requestChangesForTenant(_tenantId, agreementId, note) {
    return transition(agreementId, "pending_review", (a) => ({
      ...a, status: "draft", sentAt: null, reviewNote: note, changesRequestedAt: new Date().toISOString(),
    }));
  },
};

const TERM_KEYS = ["unitId", "tenantId", "startDate", "endDate", "rentAmount", "depositAmount", "lateFee", "rentDueDay"] as const;

/** Apply a status transition only from the expected state — same 409 rule as the API, as a thrown Error. */
const transition = (id: string, from: Agreement["status"], apply: (a: Agreement) => Agreement): Agreement => {
  const idx = agreementsMock.findIndex((a) => a.id === id);
  if (idx === -1) throw new Error(`Agreement ${id} not found`);
  const current = agreementsMock[idx]!;
  if (current.status !== from) throw new Error(`Agreement is ${current.status}, expected ${from}`);
  const next = apply(current);
  agreementsMock[idx] = next;
  return structuredClone(next);
};
