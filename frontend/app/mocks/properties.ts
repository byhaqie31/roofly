import type { Property } from "~/types/property";

export const propertiesMock: Property[] = [
  {
    id: "11111111-1111-1111-1111-111111111111",
    ownerId: "owner-1",
    name: "Suria KLCC #12-3A",
    internalLabel: "KLCC-A",
    type: "condo",
    notes: "Master bedroom AC serviced 2025-11.",
    address: "Jalan Ampang, Lot 241",
    city: "Kuala Lumpur",
    state: "W.P. Kuala Lumpur",
    postcode: "50088",
    yearBuilt: 2008,
    builtUpSqft: 1100,
    bedrooms: 3,
    bathrooms: 2,
    parkingLots: 1,
    furnishing: "fully",
    ownership: {
      titleType: "freehold",
      titleNumber: "PN 12345",
      lotNumber: "Lot 241",
      strataTitle: true,
      masterTitle: false,
      purchaseDate: "2018-03-14",
      purchasePrice: 85_000_000,         // RM 850,000
      stampDuty: 2_100_000,              // RM 21,000
      legalFees: 850_000,                // RM 8,500
      currentMarketValue: 115_000_000,   // RM 1,150,000
      lastValuedAt: "2026-01-15",
      valuationSource: "bank",
      mortgage: {
        bank: "Maybank",
        loanAmount: 68_000_000,           // RM 680,000
        outstandingBalance: 41_250_000,   // RM 412,500
        monthlyInstalment: 215_000,       // RM 2,150
        tenureYears: 30,
        maturityDate: "2048-03-31",
        interestRatePct: 4.25,
      },
      coOwners: [
        { name: "Ahmad Baihaqie", sharePct: 50 },
        { name: "Fatimah Yusof", sharePct: 50 },
      ],
    },
    utilities: {
      monthlyMaintenanceFee: 32_000,      // RM 320
      sinkingFund: 5_000,                 // RM 50
      quitRentAnnual: 9_500,              // RM 95
      assessmentRateAnnual: 48_000,       // RM 480
      buildingInsuranceAnnual: 72_000,    // RM 720
      tnbAccountNo: "9876543210",
      waterAccountNo: "1234567890",
      indahWaterAccountNo: "IWK-789",
      internetAccountNo: "TM-ABC123",
      managementCorpName: "Suria KLCC Management",
      managementCorpPhone: "03-2382 2828",
    },
    createdAt: "2026-01-12T09:00:00Z",
  },
  {
    id: "22222222-2222-2222-2222-222222222222",
    ownerId: "owner-1",
    name: "TTDI Terrace",
    type: "landed",
    address: "12, Jalan Burhanuddin Helmi 2",
    city: "Kuala Lumpur",
    state: "W.P. Kuala Lumpur",
    postcode: "60000",
    yearBuilt: 1995,
    builtUpSqft: 2400,
    landSqft: 1800,
    bedrooms: 4,
    bathrooms: 3,
    parkingLots: 2,
    furnishing: "partial",
    ownership: {
      titleType: "leasehold",
      tenureExpiry: "2090-12-31",
      strataTitle: false,
      purchaseDate: "2018-06-14",
      purchasePrice: 125_000_000,         // RM 1,250,000
      stampDuty: 3_750_000,
      legalFees: 1_200_000,
      currentMarketValue: 145_000_000,
      lastValuedAt: "2025-12-01",
      valuationSource: "agent",
    },
    utilities: {
      quitRentAnnual: 12_000,
      tnbAccountNo: "5544332211",
    },
    createdAt: "2026-02-03T10:30:00Z",
  },
  {
    id: "33333333-3333-3333-3333-333333333333",
    ownerId: "owner-1",
    name: "Wangsa Walk Shoplot G-12",
    type: "shoplot",
    address: "Lot 12, Jalan Wangsa Delima",
    city: "Kuala Lumpur",
    state: "W.P. Kuala Lumpur",
    postcode: "53300",
    yearBuilt: 2005,
    builtUpSqft: 1200,
    landSqft: 600,
    parkingLots: 1,
    furnishing: "unfurnished",
    ownership: {
      titleType: "freehold",
      strataTitle: false,
    },
    utilities: {
      assessmentRateAnnual: 360_000,
    },
    createdAt: "2026-03-18T14:00:00Z",
  },
  {
    id: "44444444-4444-4444-4444-444444444444",
    ownerId: "owner-1",
    name: "USJ 9 Spare Room",
    type: "room",
    address: "32, Jalan USJ 9/2",
    city: "Subang Jaya",
    state: "Selangor",
    postcode: "47620",
    createdAt: "2026-04-22T08:15:00Z",
  },
];
