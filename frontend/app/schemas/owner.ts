import { z } from "zod";

const phoneSchema = z
  .string()
  .regex(/^[\d\s+\-()]{8,20}$/, "Enter a valid phone number");

export const ownerProfileFormSchema = z.object({
  name: z.string().min(2).max(80),
  phone: phoneSchema,
  businessName: z
    .union([z.literal(""), z.string().max(120)])
    .optional(),
});

export const ownerPreferencesFormSchema = z.object({
  locale: z.enum(["en", "ms"]),
  theme: z.enum(["light", "dark", "system"]),
});

export const notificationEventSchema = z.enum([
  "rent_reminder",
  "agreement_expiry",
  "payment_received",
  "ticket_update",
  "invite_accepted",
]);

export const notificationChannelSchema = z.enum(["email", "whatsapp", "in_app"]);

export type OwnerProfileFormDto = z.infer<typeof ownerProfileFormSchema>;
export type OwnerPreferencesFormDto = z.infer<typeof ownerPreferencesFormSchema>;
