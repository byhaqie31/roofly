import type { Payment } from "~/types/payment";

/**
 * Manual DuitNow claims (spec 2026-10-08). A tenant's "I've paid" is a
 * `pending` transfer payment; "awaiting confirmation" is derived from it,
 * never stored on the invoice. Pure — Vitest-covered.
 */

/** The open claim on an invoice, if any (the API allows at most one). */
export const pendingClaim = (payments: Payment[]): Payment | null =>
  payments.find((p) => p.status === "pending") ?? null;

/**
 * The most recent rejected claim — only while it's still the latest word
 * (no newer pending or successful payment), so the tenant sees why and can
 * claim again.
 */
export const latestRejectedClaim = (payments: Payment[]): Payment | null => {
  const latest = [...payments].sort((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
  return latest?.status === "failed" && latest.rejectionReason ? latest : null;
};

/** `514012344521` → `•••• 4521`. Owners and tenants see full numbers where they need them; lists mask. */
export const maskAccountNumber = (accountNumber: string | null | undefined): string =>
  accountNumber ? `•••• ${accountNumber.slice(-4)}` : "";

/** `YYYY-MM-DD` for today in local time — the default "date paid". */
export const todayIso = (now: Date = new Date()): string => {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};
