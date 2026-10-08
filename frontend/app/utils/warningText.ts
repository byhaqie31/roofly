import type { WarnTemplate } from "~/types/admin";

/**
 * Owner payment-warning notice text (spec § 8). Mirrors backend
 * `App\Notifications\OwnerWarning::text` exactly — keep both in sync.
 */
export const warningText = (template: WarnTemplate, suspendOn: string, extraLine?: string | null): string => {
  const body: Record<WarnTemplate, string> = {
    payment_overdue: `Your Roofly subscription payment is overdue; your account will be suspended on ${suspendOn} unless settled.`,
  };
  return extraLine ? `${body[template]}\n\n${extraLine}` : body[template];
};
