import type { AuthUser, OwnerPurpose } from "~/types/auth";
import type { AuthAdapter } from "~/services/contracts/auth";
import { ADMIN_PERMISSIONS, type AdminPermission } from "~/types/admin";

/**
 * Demo auth. No backend: the email prefix decides the role, and the user is
 * persisted to localStorage so a refresh keeps the demo session (there is no
 * session cookie in demo). Never touches the network.
 *
 * A demo tenant signs in as the richest seeded tenant (Aminah — active
 * agreement at Suria KLCC, paid + outstanding invoices, open + resolved
 * issues) so every tenant surface has data. `useTenantSession` binds to the
 * same id, so the two cannot drift.
 */
export const DEMO_TENANT_ID = "t-aminah";
/** Matches `demo/data/owner.ts` profile.id so settings hydrates for the demo owner. */
const DEMO_OWNER_ID = "stub-owner";

const STORAGE_KEY = "roofly_auth";

const persist = (user: AuthUser | null) => {
  if (!import.meta.client) return;
  try {
    if (user) localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
    else localStorage.removeItem(STORAGE_KEY);
  } catch {
    // Quota / private mode — non-fatal in demo.
  }
};

const restore = (): AuthUser | null => {
  if (!import.meta.client) return null;
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as AuthUser) : null;
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }
};

const OWNER_DEFAULTS = {
  hasPassword: true,
  avatarUrl: null,
  // The stock demo owner is "seasoned": onboarded long ago, checklist dismissed
  // so the curated dashboard/tour is untouched. The Google-demo path resets both.
  onboardedAt: "2026-01-12T09:00:00Z",
  purposes: ["rental"] as OwnerPurpose[],
  checklistDismissedAt: "2026-01-12T09:05:00Z",
};
const NON_OWNER_DEFAULTS = {
  hasPassword: true,
  avatarUrl: null,
  onboardedAt: null,
  purposes: [] as OwnerPurpose[],
  checklistDismissedAt: null,
};

/** Lets demo services mutate the persisted demo user (onboarding, checklist). */
export const demoSession = {
  current: (): AuthUser | null => restore(),
  update(patch: Partial<AuthUser>): AuthUser {
    const current = restore();
    if (!current) throw new Error("No demo session to update");
    const next = { ...current, ...patch };
    persist(next);
    return next;
  },
};

export const DEMO_SUPER_ADMIN_ID = "a-super";
export const DEMO_OPS_ADMIN_ID = "a-ops";

/** Operations preset — mirrors backend AdminPermissions::operationsPreset(). */
export const DEMO_OPS_PRESET: AdminPermission[] = [
  "dashboard.view", "owners.view", "tenants.view", "analytics.view", "owners.warn",
  "owners.suspend", "support.manage", "broadcast.send",
];

const isAdminEmail = (email: string) =>
  email.startsWith("admin") || email.startsWith("ops");

const customerUserFor = (email: string): AuthUser =>
  email.startsWith("tenant")
    ? {
        id: DEMO_TENANT_ID,
        name: "Aminah Binti Yusof",
        email,
        phone: "+60 12-345 6789",
        role: "tenant",
        permissions: [],
        isSuperAdmin: false,
        ...NON_OWNER_DEFAULTS,
      }
    : {
        id: DEMO_OWNER_ID,
        name: "Cik Aminah",
        email,
        phone: null,
        role: "owner",
        permissions: [],
        isSuperAdmin: false,
        ...OWNER_DEFAULTS,
      };

const adminUserFor = (email: string): AuthUser =>
  email.startsWith("admin")
    ? {
        id: DEMO_SUPER_ADMIN_ID,
        name: "Baihaqie (super-admin)",
        email,
        phone: null,
        role: "admin",
        permissions: [...ADMIN_PERMISSIONS],
        isSuperAdmin: true,
        ...NON_OWNER_DEFAULTS,
      }
    : {
        id: DEMO_OPS_ADMIN_ID,
        name: "Ops Admin",
        email,
        phone: null,
        role: "admin",
        permissions: DEMO_OPS_PRESET,
        isSuperAdmin: false,
        ...NON_OWNER_DEFAULTS,
      };

const delay = () => new Promise((r) => setTimeout(r, 300));

export const demoAuth: AuthAdapter = {
  async login(email) {
    await delay();
    // The customer form is never a back door into the admin (spec § 4).
    if (isAdminEmail(email)) throw new Error("Invalid credentials");
    const user = customerUserFor(email);
    persist(user);
    return user;
  },

  async register(payload) {
    await delay();
    const user: AuthUser = {
      id: DEMO_OWNER_ID,
      name: payload.name,
      email: payload.email,
      phone: payload.phone,
      role: "owner",
      permissions: [],
      isSuperAdmin: false,
      ...OWNER_DEFAULTS,
    };
    persist(user);
    return user;
  },

  async loginWithGoogle() {
    await delay();
    const user: AuthUser = {
      id: DEMO_OWNER_ID,
      name: "Cik Aminah",
      email: "aminah.google@roofly.my",
      phone: null,
      role: "owner",
      permissions: [],
      isSuperAdmin: false,
      // Google-only account, fresh onboarding — deliberately not `...OWNER_DEFAULTS`
      // (every one of those fields is overridden here anyway).
      hasPassword: false,
      avatarUrl: null,
      onboardedAt: null,
      purposes: [],
      checklistDismissedAt: null,
    };
    persist(user);
    return user;
  },

  async logout() {
    persist(null);
  },

  async fetchMe() {
    return restore();
  },

  async loginAdmin(email) {
    await delay();
    if (!isAdminEmail(email)) throw new Error("Invalid credentials");
    const user = adminUserFor(email);
    persist(user);
    return user;
  },

  async acceptAdminInvite(_token) {
    await delay();
    const user = adminUserFor("ops@roofly.my");
    persist(user);
    return user;
  },

  async forgotPassword() {
    await delay();
  },

  async resetPassword({ email }) {
    await delay();
    const user = customerUserFor(email);
    persist(user);
    return user;
  },
};
