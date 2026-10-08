import type { TicketsService } from "~/services/contracts/tickets";
import { demoTickets } from "~/demo/services/tickets";
import { apiTickets } from "~/services/api/tickets";

export type { TicketWithRefs } from "~/services/contracts/tickets";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const useTickets = (): TicketsService =>
  useEnv().useMock ? demoTickets : apiTickets;
