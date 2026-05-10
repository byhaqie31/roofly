import type { Property, PropertyType } from "~/types/property";

export type CompletionTab = "details" | "ownership" | "utilities";

/**
 * Required fields per property type per tab. Optional/computed fields
 * (e.g. acquisitionTotal, landSqft on a condo) are intentionally excluded
 * — including them would give condos a permanent ceiling and confuse owners.
 *
 * The mortgage block is treated separately in `tabCompletion` because a
 * cash-purchased property with no mortgage shouldn't be penalised.
 */
export const REQUIRED_FIELDS: Record<
  PropertyType,
  Record<CompletionTab, string[]>
> = {
  condo: {
    details: [
      "yearBuilt",
      "builtUpSqft",
      "bedrooms",
      "bathrooms",
      "furnishing",
    ],
    ownership: [
      "titleType",
      "purchaseDate",
      "purchasePrice",
      "currentMarketValue",
    ],
    utilities: [
      "monthlyMaintenanceFee",
      "quitRentAnnual",
      "assessmentRateAnnual",
    ],
  },
  landed: {
    details: [
      "yearBuilt",
      "builtUpSqft",
      "landSqft",
      "bedrooms",
      "bathrooms",
    ],
    ownership: [
      "titleType",
      "purchaseDate",
      "purchasePrice",
      "currentMarketValue",
    ],
    utilities: ["quitRentAnnual", "assessmentRateAnnual"],
  },
  shoplot: {
    details: ["yearBuilt", "builtUpSqft", "landSqft"],
    ownership: [
      "titleType",
      "purchaseDate",
      "purchasePrice",
      "currentMarketValue",
    ],
    utilities: ["assessmentRateAnnual"],
  },
  room: {
    details: ["bedrooms", "bathrooms"],
    ownership: ["titleType"],
    utilities: [],
  },
};

const isFilled = (v: unknown): boolean => {
  if (v === undefined || v === null || v === "") return false;
  if (Array.isArray(v) && v.length === 0) return false;
  return true;
};

export const tabCompletion = (
  property: Property,
  tab: CompletionTab,
): number => {
  const fields = REQUIRED_FIELDS[property.type][tab];
  if (fields.length === 0) return 100;
  const source =
    tab === "details"
      ? (property as unknown as Record<string, unknown>)
      : tab === "ownership"
        ? ((property.ownership ?? {}) as Record<string, unknown>)
        : ((property.utilities ?? {}) as Record<string, unknown>);
  const filled = fields.filter((f) => isFilled(source[f])).length;
  return Math.round((filled / fields.length) * 100);
};
