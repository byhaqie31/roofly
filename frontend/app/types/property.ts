export type PropertyType = "condo" | "landed" | "shoplot" | "room";
export type Furnishing = "unfurnished" | "partial" | "fully";
export type TitleType = "freehold" | "leasehold";
export type ValuationSource = "bank" | "agent" | "self";

export const MY_STATES = [
  "Johor",
  "Kedah",
  "Kelantan",
  "Melaka",
  "Negeri Sembilan",
  "Pahang",
  "Perak",
  "Perlis",
  "Pulau Pinang",
  "Sabah",
  "Sarawak",
  "Selangor",
  "Terengganu",
  "W.P. Kuala Lumpur",
  "W.P. Labuan",
  "W.P. Putrajaya",
] as const;
export type MalaysianState = (typeof MY_STATES)[number];

export interface PropertyMortgage {
  bank?: string;
  loanAmount?: number;          // sen
  outstandingBalance?: number;  // sen
  monthlyInstalment?: number;   // sen
  tenureYears?: number;
  maturityDate?: string;        // ISO date
  interestRatePct?: number;     // 4.25 = 4.25%
}

export interface PropertyCoOwner {
  name: string;
  sharePct: number;             // 0-100
}

export interface PropertyOwnership {
  // Title
  titleType?: TitleType;
  titleNumber?: string;
  lotNumber?: string;
  tenureExpiry?: string;        // ISO date — only when leasehold
  strataTitle?: boolean;
  masterTitle?: boolean;
  // Acquisition
  purchaseDate?: string;
  purchasePrice?: number;       // sen
  stampDuty?: number;           // sen
  legalFees?: number;           // sen
  // Valuation
  currentMarketValue?: number;  // sen
  lastValuedAt?: string;
  valuationSource?: ValuationSource;
  // Mortgage
  mortgage?: PropertyMortgage;
  // Co-owners
  coOwners?: PropertyCoOwner[];
}

export interface PropertyUtilities {
  // Recurring fees
  monthlyMaintenanceFee?: number;     // sen
  sinkingFund?: number;               // sen / month
  quitRentAnnual?: number;            // sen
  assessmentRateAnnual?: number;      // sen
  buildingInsuranceAnnual?: number;   // sen
  // Service accounts
  tnbAccountNo?: string;
  waterAccountNo?: string;
  indahWaterAccountNo?: string;
  internetAccountNo?: string;
  managementCorpName?: string;
  managementCorpPhone?: string;
}

export interface Property {
  // Identity
  id: string;
  ownerId: string;
  name: string;
  internalLabel?: string;
  type: PropertyType;
  notes?: string;
  // Location
  address: string;
  city: string;
  state: string;
  postcode: string;
  // Specifications
  yearBuilt?: number;
  builtUpSqft?: number;
  landSqft?: number;
  bedrooms?: number;
  bathrooms?: number;
  parkingLots?: number;
  furnishing?: Furnishing;
  // JSON sub-objects
  ownership?: PropertyOwnership;
  utilities?: PropertyUtilities;
  // Server-assigned
  createdAt: string;
}

// Add Property modal — Tier 1 only
export type PropertyInput = Pick<
  Property,
  "name" | "address" | "city" | "state" | "postcode" | "type"
>;

export type PropertyUpdate = Partial<
  Omit<Property, "id" | "ownerId" | "createdAt">
>;
