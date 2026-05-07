/**
 * Money formatting per UI-Standards.md § 3.6.
 * Always cents in, formatted RM string out.
 */
export const useMoney = () => {
  const formatRM = (cents: number | null | undefined): string => {
    if (cents == null || Number.isNaN(cents)) return "RM 0.00";
    const ringgit = cents / 100;
    const sign = ringgit < 0 ? "−" : ""; // Unicode minus
    const abs = Math.abs(ringgit).toLocaleString("en-MY", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
    return `${sign}RM ${abs}`;
  };

  return { formatRM };
};
