/**
 * Fake platform for the admin shell (spec § 9): 4 owners (free / starter /
 * pro, one suspended, one over cap), their tenants (some invited-pending),
 * 2 admins, ~30 audit rows. Demo-only — never imported by services/api/**.
 * Owner "o-aminah" is the same person as the owner-shell demo account, so
 * the two shells tell one story.
 */
import type {
  AdminOwner, AdminPropertySummary, AdminTenant, AdminUser, AuditEntry, AuditAction,
} from "~/types/admin";
import { DEMO_OPS_ADMIN_ID, DEMO_OPS_PRESET, DEMO_SUPER_ADMIN_ID } from "~/demo/auth";

const daysAgo = (n: number) => new Date(Date.now() - n * 86_400_000).toISOString();
const dateOnly = (iso: string) => iso.slice(0, 10);

export const adminOwnersMock: AdminOwner[] = [
  {
    id: "o-aminah", name: "Cik Aminah", email: "aminah@roofly.my", phone: "+60 12-345 6789",
    businessName: "Aminah Properties", planTier: "free", unitsUsed: 8, unitsCap: 2,
    status: "active", suspendedAt: null, suspensionReason: null,
    createdAt: daysAgo(400), lastActiveAt: daysAgo(0),
    counts: { properties: 5, units: 8, unitsOccupied: 4, tenants: 5, agreementsActive: 3, agreementsExpiring30d: 1, invoicesOverdue: 1, ticketsOpen: 4 },
  },
  {
    id: "o-farid", name: "Farid Kamal", email: "farid@kamalhomes.my", phone: "+60 13-222 8899",
    businessName: "Kamal Homes", planTier: "starter", unitsUsed: 4, unitsCap: 5,
    status: "active", suspendedAt: null, suspensionReason: null,
    createdAt: daysAgo(210), lastActiveAt: daysAgo(2),
    counts: { properties: 2, units: 4, unitsOccupied: 4, tenants: 4, agreementsActive: 4, agreementsExpiring30d: 0, invoicesOverdue: 3, ticketsOpen: 1 },
  },
  {
    id: "o-mei", name: "Tan Mei Ling", email: "meiling@tanrealty.my", phone: "+60 16-777 1122",
    businessName: "Tan Realty", planTier: "pro", unitsUsed: 14, unitsCap: 25,
    status: "suspended", suspendedAt: daysAgo(5), suspensionReason: "Subscription unpaid for two billing cycles after warning.",
    createdAt: daysAgo(320), lastActiveAt: daysAgo(6),
    counts: { properties: 2, units: 14, unitsOccupied: 11, tenants: 3, agreementsActive: 11, agreementsExpiring30d: 2, invoicesOverdue: 0, ticketsOpen: 2 },
  },
  {
    id: "o-raj", name: "Rajesh Pillai", email: "rajesh.pillai@gmail.com", phone: null,
    businessName: null, planTier: "free", unitsUsed: 0, unitsCap: 2,
    status: "active", suspendedAt: null, suspensionReason: null,
    createdAt: daysAgo(12), lastActiveAt: daysAgo(11),
    counts: { properties: 0, units: 0, unitsOccupied: 0, tenants: 0, agreementsActive: 0, agreementsExpiring30d: 0, invoicesOverdue: 0, ticketsOpen: 0 },
  },
];

/** ownerId → properties (summary tier only) */
export const adminPropertiesMock: Record<string, AdminPropertySummary[]> = {
  "o-aminah": [
    { id: "p-1", name: "Suria KLCC Residences", address: { line: "Jalan Ampang", postcode: "50450", city: "Kuala Lumpur", state: "Kuala Lumpur" }, type: "condo", unitsTotal: 1, unitsOccupied: 1, createdAt: daysAgo(390) },
    { id: "p-2", name: "TTDI Terrace", address: { line: "Jalan Datuk Sulaiman", postcode: "60000", city: "Kuala Lumpur", state: "Kuala Lumpur" }, type: "landed", unitsTotal: 1, unitsOccupied: 1, createdAt: daysAgo(380) },
    { id: "p-3", name: "Wangsa Maju Flats", address: { line: "Jalan Wangsa Delima", postcode: "53300", city: "Kuala Lumpur", state: "Kuala Lumpur" }, type: "condo", unitsTotal: 2, unitsOccupied: 1, createdAt: daysAgo(300) },
    { id: "p-4", name: "USJ Shoplot", address: { line: "Jalan USJ 10/1", postcode: "47620", city: "Subang Jaya", state: "Selangor" }, type: "shoplot", unitsTotal: 1, unitsOccupied: 0, createdAt: daysAgo(250) },
    { id: "p-5", name: "Subang Rooms", address: { line: "Jalan SS15/4", postcode: "47500", city: "Subang Jaya", state: "Selangor" }, type: "room", unitsTotal: 3, unitsOccupied: 1, createdAt: daysAgo(200) },
  ],
  "o-farid": [
    { id: "p-f1", name: "Cyberjaya Studio Block", address: { line: "Persiaran Multimedia", postcode: "63000", city: "Cyberjaya", state: "Selangor" }, type: "condo", unitsTotal: 3, unitsOccupied: 3, createdAt: daysAgo(200) },
    { id: "p-f2", name: "Kajang Semi-D", address: { line: "Jalan Reko", postcode: "43000", city: "Kajang", state: "Selangor" }, type: "landed", unitsTotal: 1, unitsOccupied: 1, createdAt: daysAgo(150) },
  ],
  "o-mei": [
    { id: "p-m1", name: "Gurney Heights", address: { line: "Gurney Drive", postcode: "10250", city: "George Town", state: "Pulau Pinang" }, type: "condo", unitsTotal: 6, unitsOccupied: 5, createdAt: daysAgo(310) },
    { id: "p-m2", name: "Bayan Lepas Rooms", address: { line: "Jalan Tun Dr Awang", postcode: "11900", city: "Bayan Lepas", state: "Pulau Pinang" }, type: "room", unitsTotal: 8, unitsOccupied: 6, createdAt: daysAgo(280) },
  ],
  "o-raj": [],
};

export const adminTenantsMock: AdminTenant[] = [
  { id: "t-aminah", name: "Aminah Binti Yusof", email: "aminah.yusof@example.com", phone: "+60 12-345 6789", status: "active", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "Suria KLCC Residences", unitLabel: "A-12-3", invitedAt: daysAgo(370), acceptedAt: daysAgo(369), createdAt: daysAgo(370) },
  { id: "t-arif", name: "Arif Hakim", email: "arif.hakim@example.com", phone: "+60 17-888 1234", status: "active", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "Wangsa Maju Flats", unitLabel: "B-3-2", invitedAt: daysAgo(290), acceptedAt: daysAgo(288), createdAt: daysAgo(290) },
  { id: "t-li-wei", name: "Lim Li Wei", email: "limlw@example.com", phone: "+60 16-222 3344", status: "invited", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "TTDI Terrace", unitLabel: "Main", invitedAt: daysAgo(9), acceptedAt: null, createdAt: daysAgo(9) },
  { id: "t-ravi", name: "Ravi Kumar", email: "ravik@example.com", phone: "+60 13-456 7890", status: "moved_out", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "USJ Shoplot", unitLabel: "G-1", invitedAt: daysAgo(800), acceptedAt: daysAgo(799), createdAt: daysAgo(800) },
  { id: "t-siti", name: "Siti Khadijah Binti Rahim", email: "siti.khadijah@example.com", phone: "+60 11-2233 4455", status: "notice_given", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "Subang Rooms", unitLabel: "Master", invitedAt: daysAgo(560), acceptedAt: daysAgo(559), createdAt: daysAgo(560) },
  { id: "t-f1", name: "Nurul Izzah", email: "nurul.izzah@example.com", phone: "+60 19-100 2000", status: "active", ownerId: "o-farid", ownerName: "Farid Kamal", propertyName: "Cyberjaya Studio Block", unitLabel: "3-01", invitedAt: daysAgo(180), acceptedAt: daysAgo(179), createdAt: daysAgo(180) },
  { id: "t-f2", name: "Daniel Wong", email: "daniel.wong@example.com", phone: "+60 12-900 1234", status: "active", ownerId: "o-farid", ownerName: "Farid Kamal", propertyName: "Cyberjaya Studio Block", unitLabel: "3-02", invitedAt: daysAgo(170), acceptedAt: daysAgo(168), createdAt: daysAgo(170) },
  { id: "t-f3", name: "Priya Nair", email: "priya.nair@example.com", phone: "+60 14-333 4444", status: "active", ownerId: "o-farid", ownerName: "Farid Kamal", propertyName: "Cyberjaya Studio Block", unitLabel: "3-03", invitedAt: daysAgo(120), acceptedAt: daysAgo(119), createdAt: daysAgo(120) },
  { id: "t-f4", name: "Hafiz Rahman", email: "hafiz.r@example.com", phone: "+60 11-555 6666", status: "active", ownerId: "o-farid", ownerName: "Farid Kamal", propertyName: "Kajang Semi-D", unitLabel: "Main", invitedAt: daysAgo(140), acceptedAt: daysAgo(139), createdAt: daysAgo(140) },
  { id: "t-m1", name: "Chong Wei Jie", email: "chong.wj@example.com", phone: "+60 12-111 2222", status: "active", ownerId: "o-mei", ownerName: "Tan Mei Ling", propertyName: "Gurney Heights", unitLabel: "12-A", invitedAt: daysAgo(300), acceptedAt: daysAgo(299), createdAt: daysAgo(300) },
  { id: "t-m2", name: "Sarah Abdullah", email: "sarah.abd@example.com", phone: "+60 13-999 8888", status: "invited", ownerId: "o-mei", ownerName: "Tan Mei Ling", propertyName: "Bayan Lepas Rooms", unitLabel: "R-4", invitedAt: daysAgo(3), acceptedAt: null, createdAt: daysAgo(3) },
  { id: "t-m3", name: "Kevin Ooi", email: "kevin.ooi@example.com", phone: "+60 16-123 4567", status: "invited", ownerId: "o-mei", ownerName: "Tan Mei Ling", propertyName: "Bayan Lepas Rooms", unitLabel: "R-5", invitedAt: daysAgo(15), acceptedAt: null, createdAt: daysAgo(15) },
];

export const adminUsersMock: AdminUser[] = [
  { id: DEMO_SUPER_ADMIN_ID, name: "Baihaqie (super-admin)", email: "admin@roofly.my", permissions: [], isSuperAdmin: true, status: "active", lastActiveAt: daysAgo(0), createdAt: daysAgo(60) },
  { id: DEMO_OPS_ADMIN_ID, name: "Ops Admin", email: "ops@roofly.my", permissions: DEMO_OPS_PRESET, isSuperAdmin: false, status: "active", lastActiveAt: daysAgo(1), createdAt: daysAgo(45) },
];

type Seed = [daysBack: number, actorId: string, action: AuditAction, subjectId: string | null, extra?: Partial<AuditEntry>];

const actorName = (id: string) => adminUsersMock.find((a) => a.id === id)?.name ?? null;
const subjectName = (id: string | null) =>
  id === null ? null
    : adminOwnersMock.find((o) => o.id === id)?.name
      ?? adminTenantsMock.find((t) => t.id === id)?.name
      ?? adminUsersMock.find((a) => a.id === id)?.name
      ?? null;

const seeds: Seed[] = [
  [45, DEMO_SUPER_ADMIN_ID, "admin.invite_sent", DEMO_OPS_ADMIN_ID, { after: { permissions: DEMO_OPS_PRESET, isSuperAdmin: false } }],
  [44, DEMO_OPS_ADMIN_ID, "admin.invite_accepted", DEMO_OPS_ADMIN_ID],
  [44, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [40, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [33, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [30, DEMO_OPS_ADMIN_ID, "owner.warned", "o-mei", { after: { template: "payment_overdue", suspendOn: dateOnly(daysAgo(16)), extraLine: null, text: `Your Roofly subscription payment is overdue; your account will be suspended on ${dateOnly(daysAgo(16))} unless settled.` } }],
  [28, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [25, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [22, DEMO_OPS_ADMIN_ID, "tenant.invite_resent", "t-m3", { before: { invitedAt: daysAgo(29) }, after: { invitedAt: daysAgo(15) } }],
  [20, DEMO_SUPER_ADMIN_ID, "admin.permissions_changed", DEMO_OPS_ADMIN_ID, { before: { permissions: DEMO_OPS_PRESET.filter((k) => k !== "broadcast.send"), isSuperAdmin: false }, after: { permissions: DEMO_OPS_PRESET, isSuperAdmin: false } }],
  [18, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [16, DEMO_OPS_ADMIN_ID, "owner.warned", "o-mei", { after: { template: "payment_overdue", suspendOn: dateOnly(daysAgo(5)), extraLine: "Final notice.", text: `Your Roofly subscription payment is overdue; your account will be suspended on ${dateOnly(daysAgo(5))} unless settled.\n\nFinal notice.` } }],
  [15, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [14, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [12, DEMO_OPS_ADMIN_ID, "owner.warned", "o-farid", { after: { template: "payment_overdue", suspendOn: dateOnly(daysAgo(-2)), extraLine: null, text: `Your Roofly subscription payment is overdue; your account will be suspended on ${dateOnly(daysAgo(-2))} unless settled.` } }],
  [11, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [10, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [9, DEMO_OPS_ADMIN_ID, "tenant.invite_resent", "t-li-wei", { before: { invitedAt: daysAgo(20) }, after: { invitedAt: daysAgo(9) } }],
  [8, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [7, DEMO_OPS_ADMIN_ID, "owner.suspended", "o-farid", { before: { status: "active" }, after: { status: "suspended" }, reason: "Subscription unpaid after two warnings." }],
  [6, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [6, DEMO_SUPER_ADMIN_ID, "owner.unsuspended", "o-farid", { before: { status: "suspended", suspensionReason: "Subscription unpaid after two warnings." }, after: { status: "active" } }],
  [5, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [5, DEMO_OPS_ADMIN_ID, "owner.suspended", "o-mei", { before: { status: "active" }, after: { status: "suspended" }, reason: "Subscription unpaid for two billing cycles after warning." }],
  [4, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [3, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [2, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [1, DEMO_OPS_ADMIN_ID, "tenant.invite_resent", "t-m2", { before: { invitedAt: daysAgo(10) }, after: { invitedAt: daysAgo(3) } }],
  [1, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [0, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
];

export const auditMock: AuditEntry[] = seeds.map(([days, actorId, action, subjectId, extra], i) => ({
  id: `audit-${String(i + 1).padStart(3, "0")}`,
  action,
  actorId,
  actorName: actorName(actorId),
  subjectType: subjectId ? "user" : null,
  subjectId,
  subjectName: subjectName(subjectId),
  before: {},
  after: {},
  reason: null,
  ip: "203.0.113.42",
  createdAt: daysAgo(days),
  ...extra,
}));

/** Append a new demo audit row (demo adapters call this after every write). */
export const pushAudit = (entry: Omit<AuditEntry, "id" | "createdAt" | "ip" | "actorName" | "subjectName">): AuditEntry => {
  const row: AuditEntry = {
    ...entry,
    id: `audit-${String(auditMock.length + 1).padStart(3, "0")}`,
    actorName: entry.actorId ? actorName(entry.actorId) : null,
    subjectName: subjectName(entry.subjectId),
    ip: "203.0.113.42",
    createdAt: new Date().toISOString(),
  };
  auditMock.push(row);
  return row;
};
