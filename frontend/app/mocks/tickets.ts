import type { Ticket, TicketComment } from "~/types/ticket";

export const ticketsMock: Ticket[] = [
  // NEW · urgent — power tripped after storm
  {
    id: "tk-power-trip",
    unitId: "u-wangsa-1",
    reporterId: "t-arif",
    reporterRole: "tenant",
    category: "electrical",
    priority: "urgent",
    title: "Power tripped repeatedly after storm",
    description:
      "Last night's storm seemed to take out the main panel — kept tripping when the AC starts. Whole shop without power for 30 mins this morning. Need someone to look ASAP, fridge is at risk.",
    status: "new",
    createdAt: "2026-05-08T07:14:00Z",
    updatedAt: "2026-05-08T07:14:00Z",
  },
  // NEW · medium — owner-logged preventive
  {
    id: "tk-repaint",
    unitId: "u-subang-room3",
    reporterId: "owner-1",
    reporterRole: "owner",
    category: "other",
    priority: "medium",
    title: "Schedule repaint before next tenant",
    description:
      "Back room is vacant. Walls have nail holes from previous tenant + minor scuffs. Want to repaint and patch before listing again.",
    status: "new",
    createdAt: "2026-05-05T03:00:00Z",
    updatedAt: "2026-05-05T03:00:00Z",
  },

  // IN_PROGRESS · high — bathroom drain
  {
    id: "tk-bath-drain",
    unitId: "u-suria-1",
    reporterId: "t-aminah",
    reporterRole: "tenant",
    category: "plumbing",
    priority: "high",
    title: "Master bathroom drain very slow",
    description:
      "Standing water after every shower, takes ~15 mins to drain. Tried plunger, no improvement. Smells starting to build up.",
    status: "in_progress",
    createdAt: "2026-05-04T13:22:00Z",
    updatedAt: "2026-05-06T09:00:00Z",
  },
  // IN_PROGRESS · high — termites
  {
    id: "tk-termites",
    unitId: "u-subang-master",
    reporterId: "t-siti",
    reporterRole: "tenant",
    category: "pest",
    priority: "high",
    title: "Termites in window frame, exterminator booked",
    description:
      "Found mud tunnels along the master bedroom window frame. Wood is soft to touch. Sent photos to landlord. Termite specialist scheduled for next Wednesday.",
    status: "in_progress",
    createdAt: "2026-04-30T05:45:00Z",
    updatedAt: "2026-05-07T11:30:00Z",
  },

  // RESOLVED · low — kitchen tap drip
  {
    id: "tk-tap-drip",
    unitId: "u-suria-1",
    reporterId: "t-aminah",
    reporterRole: "tenant",
    category: "plumbing",
    priority: "low",
    title: "Kitchen tap dripping",
    description:
      "Cold side of the kitchen mixer drips constantly even when fully closed. Probably worn cartridge.",
    status: "resolved",
    createdAt: "2026-04-12T10:00:00Z",
    updatedAt: "2026-04-18T14:00:00Z",
    resolvedAt: "2026-04-18T14:00:00Z",
  },
  // RESOLVED · medium — sticky lock
  {
    id: "tk-shop-lock",
    unitId: "u-wangsa-1",
    reporterId: "t-arif",
    reporterRole: "tenant",
    category: "other",
    priority: "medium",
    title: "Front shop door lock sticky",
    description:
      "Key turns hard, especially in the morning. Worried about getting locked out at closing.",
    status: "resolved",
    createdAt: "2026-03-22T09:30:00Z",
    updatedAt: "2026-04-02T16:20:00Z",
    resolvedAt: "2026-04-02T16:20:00Z",
  },

  // REOPENED · high — water heater
  {
    id: "tk-water-heater",
    unitId: "u-subang-master",
    reporterId: "t-siti",
    reporterRole: "tenant",
    category: "appliance",
    priority: "high",
    title: "Water heater intermittent again",
    description:
      "Was fixed a month ago (replaced heating element). Started cutting out again last week — only lukewarm water in the morning, sometimes cold mid-shower. Likely related to the same circuit.",
    status: "reopened",
    createdAt: "2026-03-15T08:00:00Z",
    updatedAt: "2026-05-06T19:40:00Z",
  },
];

export const ticketCommentsMock: TicketComment[] = [
  // bath-drain — plumber scheduled, tenant confirmed
  {
    id: "tc-bath-1",
    ticketId: "tk-bath-drain",
    authorId: "owner-1",
    authorRole: "owner",
    body: "Plumber booked for Thursday morning, between 9am and 11am. Please be home or leave the spare key with the guard.",
    createdAt: "2026-05-05T02:30:00Z",
  },
  {
    id: "tc-bath-2",
    ticketId: "tk-bath-drain",
    authorId: "t-aminah",
    authorRole: "tenant",
    body: "Confirmed, I'll be home until noon. Thanks.",
    createdAt: "2026-05-05T03:08:00Z",
  },
  {
    id: "tc-bath-3",
    ticketId: "tk-bath-drain",
    authorId: "owner-1",
    authorRole: "owner",
    body: "Plumber found a small clog near the trap. Cleared and tested — drains fine now. Will close once you confirm it's still good after a few days.",
    createdAt: "2026-05-06T09:00:00Z",
  },

  // termites — owner ack + plan
  {
    id: "tc-term-1",
    ticketId: "tk-termites",
    authorId: "owner-1",
    authorRole: "owner",
    body: "Got the photos, thanks. Booked Anti-Pest Sdn Bhd for Wed 14 May. They'll do a full perimeter check + treat the affected frame.",
    createdAt: "2026-05-01T01:00:00Z",
  },
  {
    id: "tc-term-2",
    ticketId: "tk-termites",
    authorId: "t-siti",
    authorRole: "tenant",
    body: "Noted. I'll be moving out end of May anyway, so please keep me updated if it affects the deposit.",
    createdAt: "2026-05-07T11:30:00Z",
  },

  // water-heater — original resolution then regression
  {
    id: "tc-heat-1",
    ticketId: "tk-water-heater",
    authorId: "owner-1",
    authorRole: "owner",
    body: "Replaced the lower heating element last month — issue was a corroded element draining heat to the housing.",
    createdAt: "2026-03-20T05:00:00Z",
  },
  {
    id: "tc-heat-2",
    ticketId: "tk-water-heater",
    authorId: "t-siti",
    authorRole: "tenant",
    body: "Reopening — water is lukewarm again starting last week. Same symptoms as before.",
    createdAt: "2026-05-06T19:40:00Z",
  },
];
