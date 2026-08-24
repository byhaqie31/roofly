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
  /** Owner-only Google sign-in (GIS ID token). Creates or links by verified email. */
  loginWithGoogle(credential: string): Promise<AuthUser>;
  logout(): Promise<void>;
  /** Boot hydration. Resolves `null` when not signed in — that case never throws. */
  fetchMe(): Promise<AuthUser | null>;

  // ── Admin Portal (spec § 4) — separate login, same session underneath ──
  /** Rejects (throws) for any non-admin or disabled admin. */
  loginAdmin(email: string, password: string): Promise<AuthUser>;
  /** Sets the invited admin's password from the emailed token and logs them in. */
  acceptAdminInvite(token: string, password: string): Promise<AuthUser>;

  // ── Forgot / reset password (spec § 3.4) ──
  /** Always resolves — the API never reveals whether the email exists. */
  forgotPassword(email: string): Promise<void>;
  /** Sets the password from an emailed token and logs the user in. */
  resetPassword(input: { token: string; email: string; password: string }): Promise<AuthUser>;
}
