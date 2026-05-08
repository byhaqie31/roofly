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
  },
  {
    id: "t-arif",
    name: "Arif Hakim",
    email: "arif.hakim@example.com",
    phone: "+60 17-888 1234",
    status: "active",
    invitedAt: "2025-11-02T09:00:00Z",
    createdAt: "2025-11-02T09:00:00Z",
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
  },
];
