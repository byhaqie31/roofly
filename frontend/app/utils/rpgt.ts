/**
 * Real Property Gains Tax (RPGT) — Malaysian residents, individual category.
 * Brackets reflect the post-2024 schedule for citizens / PRs:
 *   Year 1-2: 30%, Year 3: 20%, Year 4: 15%, Year 5: 10%, Year 6+: 5%.
 *
 * Out of scope: non-citizen / foreigner / company brackets (different rates),
 * disposal-side costs (agent commission, sale legal fees) reducing net gain,
 * and renovation receipts that count as allowable expenses.
 *
 * This is a snapshot estimate. Real filings should consult LHDN.
 */

export const rpgtRate = (yearsHeld: number): number => {
  if (yearsHeld < 0) return 0;
  if (yearsHeld < 3) return 0.3;
  if (yearsHeld < 4) return 0.2;
  if (yearsHeld < 5) return 0.15;
  if (yearsHeld < 6) return 0.1;
  return 0.05;
};

export const yearsHeld = (purchaseIso: string, asOf = new Date()): number => {
  const purchase = new Date(purchaseIso);
  if (Number.isNaN(purchase.getTime())) return 0;
  const ms = asOf.getTime() - purchase.getTime();
  return Math.max(0, ms / (365.25 * 24 * 60 * 60 * 1000));
};

export interface CapitalGainsSnapshot {
  acquisitionCost: number;     // sen
  gain: number;                // sen
  ratePct: number;             // 0.30 = 30%
  rpgt: number;                // sen
  net: number;                 // sen
  yearsHeld: number;
}

export const computeCapitalGains = (params: {
  purchasePrice?: number;
  stampDuty?: number;
  legalFees?: number;
  currentMarketValue?: number;
  purchaseDate?: string;
}): CapitalGainsSnapshot | null => {
  const purchase = params.purchasePrice;
  const market = params.currentMarketValue;
  if (purchase == null || market == null || !params.purchaseDate) return null;
  const acquisitionCost =
    purchase + (params.stampDuty ?? 0) + (params.legalFees ?? 0);
  const gain = market - acquisitionCost;
  const years = yearsHeld(params.purchaseDate);
  const ratePct = rpgtRate(years);
  const rpgt = Math.max(0, Math.round(gain * ratePct));
  const net = gain - rpgt;
  return {
    acquisitionCost,
    gain,
    ratePct,
    rpgt,
    net,
    yearsHeld: years,
  };
};
