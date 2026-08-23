import type { AdminDashboardService } from "~/services/contracts/admin/dashboard";
import type { AdminAttentionItem, AdminDashboardData } from "~/types/admin";
import { adminOwnersMock, adminTenantsMock, adminPropertiesMock } from "~/demo/data/admin";

const ymKey = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
const DAY_MS = 86_400_000;

/** Demo mirror of backend Admin\DashboardController — keep in lock-step. */
const build = (): AdminDashboardData => {
  const now = new Date();
  const months: string[] = [];
  for (let i = 11; i >= 0; i--) months.push(ymKey(new Date(now.getFullYear(), now.getMonth() - i, 1)));

  const countBy = (dates: (string | null)[]) =>
    months.map((m) => dates.filter((d) => d && ymKey(new Date(d)) === m).length);

  const acceptance = months.map((m) => {
    const invited = adminTenantsMock.filter((t) => t.invitedAt && ymKey(new Date(t.invitedAt)) === m);
    if (invited.length === 0) return 0;
    return Math.round((invited.filter((t) => t.acceptedAt).length / invited.length) * 100);
  });

  // Synthetic but stable invoice counts for the chart (demo has no platform-wide invoice data).
  const invoicesIssued = months.map((_, i) => 18 + i * 2);
  const invoicesPaid = months.map((_, i) => 14 + i * 2 - (i % 3));

  const unitsTotal = adminOwnersMock.reduce((s, o) => s + o.counts.units, 0);
  const unitsOccupied = adminOwnersMock.reduce((s, o) => s + o.counts.unitsOccupied, 0);

  const attention: AdminAttentionItem[] = [];
  const push = (kind: AdminAttentionItem["kind"], ownerId: string, meta: string) => {
    const o = adminOwnersMock.find((x) => x.id === ownerId)!;
    attention.push({ kind, ownerId, ownerName: o.name, meta, link: `/admin/owners/${ownerId}` });
  };
  adminOwnersMock.forEach((o) => {
    if (o.unitsCap !== null && o.unitsUsed > o.unitsCap) push("over_cap", o.id, `${o.unitsUsed}/${o.unitsCap}`);
    if (o.counts.invoicesOverdue >= 3) push("overdue_3plus", o.id, `${o.counts.invoicesOverdue} overdue`);
    const stale = adminTenantsMock.filter((t) => t.ownerId === o.id && t.status === "invited" && t.invitedAt && now.getTime() - new Date(t.invitedAt).getTime() > 7 * DAY_MS).length;
    if (stale > 0) push("invite_stale_7d", o.id, `${stale} pending`);
    const ageDays = Math.floor((now.getTime() - new Date(o.createdAt).getTime()) / DAY_MS);
    if ((adminPropertiesMock[o.id]?.length ?? 0) === 0 && ageDays > 7) push("no_property_7d", o.id, `${ageDays}d`);
    if (o.status === "suspended") push("suspended", o.id, (o.suspendedAt ?? "").slice(0, 10));
  });

  return {
    tiles: {
      owners: { total: adminOwnersMock.length, active: adminOwnersMock.filter((o) => o.status === "active").length, suspended: adminOwnersMock.filter((o) => o.status === "suspended").length },
      tenants: { total: adminTenantsMock.length, invitedPending: adminTenantsMock.filter((t) => t.status === "invited").length },
      properties: Object.values(adminPropertiesMock).reduce((s, p) => s + p.length, 0),
      units: { total: unitsTotal, occupiedPct: unitsTotal ? Math.round((unitsOccupied / unitsTotal) * 100) : 0 },
      agreementsActive: adminOwnersMock.reduce((s, o) => s + o.counts.agreementsActive, 0),
      agreementsExpiring30d: adminOwnersMock.reduce((s, o) => s + o.counts.agreementsExpiring30d, 0),
      supportOpen: 0,
    },
    series: {
      months,
      ownerSignups: countBy(adminOwnersMock.map((o) => o.createdAt)),
      invoicesIssued,
      invoicesPaid,
      inviteAcceptanceRate: acceptance,
    },
    attention,
  };
};

export const demoAdminDashboard: AdminDashboardService = {
  async getDashboard() {
    return build();
  },
};
