import type { OwnerPurpose } from "~/types/auth";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import type { Agreement } from "~/types/agreement";
import { tabCompletion } from "~/utils/propertyCompletion";

/**
 * Getting-started checklist (spec 2026-08-23 § 6). Pure: computed from the
 * owner's real data every time — nothing is stored per step, so it can never
 * drift from reality. Steps after `add_property` stay visible but disabled
 * until a property exists, so the owner sees the whole path.
 */
export type ChecklistKey =
  | "add_property"
  | "fill_ownership"
  | "fill_utilities"
  | "add_unit"
  | "invite_tenant"
  | "create_agreement";

export interface ChecklistStep {
  key: ChecklistKey;
  done: boolean;
  /** False while the step can't be acted on yet (no property). */
  enabled: boolean;
  to: string;
  propertyId?: string;
}

export interface ChecklistInput {
  purposes: OwnerPurpose[];
  properties: Property[];
  units: Unit[];
  tenants: Tenant[];
  agreements: Agreement[];
}

const RENTAL_ONLY: ChecklistKey[] = ["add_unit", "invite_tenant", "create_agreement"];
const ORDER: ChecklistKey[] = ["add_property", "fill_ownership", "fill_utilities", ...RENTAL_ONLY];

export const buildChecklist = (input: ChecklistInput): ChecklistStep[] => {
  const hasRental = input.purposes.includes("rental") || input.purposes.length === 0;
  const hasProperty = input.properties.length > 0;
  const rental = input.properties.filter((p) => p.purpose === "rental");

  const firstIncomplete = (tab: "ownership" | "utilities") =>
    input.properties.find((p) => tabCompletion(p, tab) < 100);

  const propertyStep = (key: ChecklistKey, tab: "ownership" | "utilities"): ChecklistStep => {
    const target = firstIncomplete(tab);
    return {
      key,
      done: hasProperty && target === undefined,
      enabled: hasProperty,
      to: target ? `/owner/properties/${target.id}?tab=${tab}` : "/owner/properties",
      propertyId: target?.id,
    };
  };

  const unitless = rental.find((p) => !input.units.some((u) => u.propertyId === p.id));
  const steps: Record<ChecklistKey, ChecklistStep> = {
    add_property: { key: "add_property", done: hasProperty, enabled: true, to: "/owner/properties?add=1" },
    fill_ownership: propertyStep("fill_ownership", "ownership"),
    fill_utilities: propertyStep("fill_utilities", "utilities"),
    add_unit: {
      key: "add_unit",
      // ANY rule (unlike fill_ownership/fill_utilities, which are ALL rules): this is a
      // getting-started nudge, not a completeness audit — one unit on any rental property
      // means the owner has learned how units work.
      done: rental.length > 0 && rental.some((p) => input.units.some((u) => u.propertyId === p.id)),
      enabled: rental.length > 0,
      to: unitless ? `/owner/properties/${unitless.id}?tab=overview` : "/owner/properties",
      propertyId: unitless?.id,
    },
    invite_tenant: { key: "invite_tenant", done: input.tenants.length > 0, enabled: hasProperty, to: "/owner/tenants?invite=1" },
    create_agreement: {
      key: "create_agreement",
      done: input.agreements.some((a) => a.status === "active"),
      enabled: hasProperty,
      to: "/owner/agreements/new",
    },
  };

  return ORDER.filter((k) => hasRental || !RENTAL_ONLY.includes(k)).map((k) => steps[k]);
};
