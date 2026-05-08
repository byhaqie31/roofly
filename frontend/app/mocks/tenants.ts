import type { Tenant } from "~/types/tenant";

export const tenantsMock: Tenant[] = [
  {
    id: "t-aminah",
    name: "Aminah Binti Yusof",
    email: "aminah.yusof@example.com",
    phone: "+60 12-345 6789",
    status: "active",
    invitedAt: "2025-08-15T10:00:00Z",
    createdAt: "2025-08-15T10:00:00Z",
    personal: {
      icNumber: "880314-14-5687",
      dateOfBirth: "1988-03-14",
      occupation: "Marketing manager",
      employer: "Petronas",
      monthlyIncome: 1_200_000,        // RM 12,000
      nationality: "Malaysian",
    },
    emergencyContact: {
      name: "Yusof Bin Hamid",
      phone: "+60 19-555 0011",
      relationship: "Father",
    },
  },
  {
    id: "t-arif",
    name: "Arif Hakim",
    email: "arif.hakim@example.com",
    phone: "+60 17-888 1234",
    status: "active",
    invitedAt: "2025-11-02T09:00:00Z",
    createdAt: "2025-11-02T09:00:00Z",
    personal: {
      icNumber: "920701-08-1234",
      dateOfBirth: "1992-07-01",
      occupation: "Café owner",
      nationality: "Malaysian",
    },
  },
  {
    id: "t-li-wei",
    name: "Lim Li Wei",
    email: "limlw@example.com",
    phone: "+60 16-222 3344",
    status: "invited",
    invitedAt: "2026-04-30T14:30:00Z",
    createdAt: "2026-04-30T14:30:00Z",
  },
  {
    id: "t-ravi",
    name: "Ravi Kumar",
    email: "ravik@example.com",
    phone: "+60 13-456 7890",
    status: "moved_out",
    invitedAt: "2024-03-10T08:00:00Z",
    createdAt: "2024-03-10T08:00:00Z",
    personal: {
      occupation: "Software engineer",
      nationality: "Indian",
    },
    emergencyContact: {
      name: "Priya Kumar",
      phone: "+60 13-100 2222",
      relationship: "Spouse",
    },
  },
  // Notice given — anchors upcoming-vacancy widgets on the dashboard.
  {
    id: "t-siti",
    name: "Siti Khadijah Binti Rahim",
    email: "siti.khadijah@example.com",
    phone: "+60 11-2233 4455",
    status: "notice_given",
    invitedAt: "2025-02-05T09:00:00Z",
    createdAt: "2025-02-05T09:00:00Z",
    personal: {
      icNumber: "910420-10-3344",
      dateOfBirth: "1991-04-20",
      occupation: "Teacher",
      employer: "SMK Bukit Bintang",
      monthlyIncome: 600_000,           // RM 6,000
      nationality: "Malaysian",
    },
    emergencyContact: {
      name: "Rahim Bin Hassan",
      phone: "+60 12-987 6543",
      relationship: "Father",
    },
  },
];
