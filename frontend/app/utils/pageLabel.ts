// Turns the path the Help & feedback button was opened on into a name an admin
// can read at a glance ("Owner app · Payments"). Computed at send time and stored
// with the enquiry, so the admin page and the alert email show the same thing.
// English only — it's read in the admin shell. Pure, Vitest-covered.

const OWNER: Array<[RegExp, string]> = [
  [/^\/owner\/?$/, "Dashboard"],
  [/^\/owner\/onboarding$/, "Onboarding"],
  [/^\/owner\/properties$/, "Properties"],
  [/^\/owner\/properties\/[^/]+$/, "Property details"],
  [/^\/owner\/tenants$/, "Tenants"],
  [/^\/owner\/tenants\/[^/]+$/, "Tenant details"],
  [/^\/owner\/agreements$/, "Agreements"],
  [/^\/owner\/agreements\/new$/, "New agreement"],
  [/^\/owner\/agreements\/[^/]+$/, "Agreement details"],
  [/^\/owner\/payments$/, "Payments"],
  [/^\/owner\/maintenance$/, "Maintenance board"],
  [/^\/owner\/maintenance\/[^/]+$/, "Maintenance ticket"],
  [/^\/owner\/reports$/, "Reports"],
  [/^\/owner\/settings$/, "Settings"],
];

const TENANT: Array<[RegExp, string]> = [
  [/^\/tenant\/?$/, "Home"],
  [/^\/tenant\/onboarding$/, "Onboarding"],
  [/^\/tenant\/agreement$/, "Agreement"],
  [/^\/tenant\/payments$/, "Payments"],
  [/^\/tenant\/profile$/, "Profile"],
  [/^\/tenant\/tickets$/, "Issues"],
  [/^\/tenant\/tickets\/[^/]+$/, "Issue details"],
];

export const pageLabelFor = (fullPath: string): string => {
  const path = fullPath.split(/[?#]/)[0]!.replace(/\/+$/, "") || "/";
  const [app, table] = path.startsWith("/owner") ? ["Owner app", OWNER] : path.startsWith("/tenant") ? ["Tenant app", TENANT] : [null, []];
  if (!app) return path;
  const hit = table.find(([re]) => re.test(path));
  return `${app} · ${hit ? hit[1] : path}`;
};
