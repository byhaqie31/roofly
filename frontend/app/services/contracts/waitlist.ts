/**
 * Coming-soon waitlist capture. One method: hand over the email a visitor typed.
 * `website` is the form's honeypot — humans never see the field, bots fill it;
 * the API adapter forwards it so the backend can drop the row silently.
 */
export interface WaitlistSignup {
  email: string;
  website?: string;
}

export interface WaitlistService {
  join(input: WaitlistSignup): Promise<void>;
}
