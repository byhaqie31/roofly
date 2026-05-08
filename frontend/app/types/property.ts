export type PropertyType = "condo" | "landed" | "shoplot" | "room";
export type Furnishing = "unfurnished" | "partial" | "fully";
export type TitleType = "freehold" | "leasehold";

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

export interface PropertyDetails {
  purchaseDate?: string;
  purchasePrice?: number;
  monthlyMaintenanceFee?: number;
  quitRentAnnual?: number;
  assessmentRateAnnual?: number;
  insurancePolicyNo?: string;
  insuranceProvider?: string;
  tnbAccountNo?: string;
  waterAccountNo?: string;
  indahWaterAccountNo?: string;
  notes?: string;
  photos?: string[];
}

export interface Property {
  id: string;
  ownerId: string;
  name: string;
  address: string;
  city: string;
  state: string;
  postcode: string;
  type: PropertyType;
  yearBuilt?: number;
  builtUpSqft?: number;
  landSqft?: number;
  bedrooms?: number;
  bathrooms?: number;
  parkingLots?: number;
  furnishing?: Furnishing;
  titleType?: TitleType;
  tenureExpiry?: string;
  strataTitle?: boolean;
  details?: PropertyDetails;
  createdAt: string;
}

export type PropertyInput = Pick<
  Property,
  "name" | "address" | "city" | "state" | "postcode" | "type"
>;

export type PropertyUpdate = Partial<
  Omit<Property, "id" | "ownerId" | "createdAt">
>;
