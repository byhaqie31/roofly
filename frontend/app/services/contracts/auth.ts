import type { AuthUser } from "~/types/auth";

export interface RegisterPayload {
  name: string;
  email: string;
  phone: string;
  password: string;
  visitorId?: string;
}

export interface AuthAdapter {
  login(email: string, password: string): Promise<AuthUser>;
  register(payload: RegisterPayload): Promise<AuthUser>;
  logout(): Promise<void>;
  /** Boot hydration. Resolves `null` when not signed in — that case never throws. */
  fetchMe(): Promise<AuthUser | null>;

  // ── Admin Portal (spec § 4) — separate login, same session underneath ──
  /** Rejects (throws) for any non-admin or disabled admin. */
  loginAdmin(email: string, password: string): Promise<AuthUser>;
  /** Sets the invited admin's password from the emailed token and logs them in. */
  acceptAdminInvite(token: string, password: string): Promise<AuthUser>;
}
