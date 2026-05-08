import type {
  Agreement,
  AgreementInput,
  AgreementUpdate,
} from "~/types/agreement";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import { agreementsMock } from "~/mocks/agreements";
import { propertiesMock } from "~/mocks/properties";
import { unitsMock } from "~/mocks/units";
import { tenantsMock } from "~/mocks/tenants";

export interface AgreementWithRefs {
  agreement: Agreement;
  unit: Unit | null;
  property: Property | null;
  tenant: Tenant | null;
}

const hydrate = (a: Agreement): AgreementWithRefs => {
  const unit = unitsMock.find((u) => u.id === a.unitId) ?? null;
  const property = unit
    ? (propertiesMock.find((p) => p.id === unit.propertyId) ?? null)
    : null;
  const tenant = tenantsMock.find((t) => t.id === a.tenantId) ?? null;
  return {
    agreement: structuredClone(a),
    unit: unit ? structuredClone(unit) : null,
    property: property ? structuredClone(property) : null,
    tenant: tenant ? structuredClone(tenant) : null,
  };
};

export const useAgreements = () => {
  const { public: { useMock } } = useRuntimeConfig();

  const list = async (): Promise<Agreement[]> => {
    if (useMock) return structuredClone(agreementsMock);
    const { request } = useApi();
    return request<Agreement[]>("/agreements");
  };

  const listWithRefs = async (): Promise<AgreementWithRefs[]> => {
    if (useMock) return agreementsMock.map(hydrate);
    const { request } = useApi();
    return request<AgreementWithRefs[]>("/agreements?expand=unit,property,tenant");
  };

  const get = async (id: string): Promise<Agreement | null> => {
    if (useMock) {
      const found = agreementsMock.find((a) => a.id === id);
      return found ? structuredClone(found) : null;
    }
    const { request } = useApi();
    return request<Agreement>(`/agreements/${id}`);
  };

  const create = async (input: AgreementInput): Promise<Agreement> => {
    if (useMock) {
      const created: Agreement = {
        id: crypto.randomUUID(),
        ...input,
        createdAt: new Date().toISOString(),
      };
      agreementsMock.push(created);
      return structuredClone(created);
    }
    const { request } = useApi();
    return request<Agreement>("/agreements", { method: "POST", body: input });
  };

  const update = async (
    id: string,
    patch: AgreementUpdate,
  ): Promise<Agreement> => {
    if (useMock) {
      const idx = agreementsMock.findIndex((a) => a.id === id);
      if (idx === -1) throw new Error(`Agreement ${id} not found`);
      const merged: Agreement = { ...agreementsMock[idx]!, ...patch };
      agreementsMock[idx] = merged;
      return structuredClone(merged);
    }
    const { request } = useApi();
    return request<Agreement>(`/agreements/${id}`, {
      method: "PATCH",
      body: patch,
    });
  };

  const remove = async (id: string): Promise<void> => {
    if (useMock) {
      const idx = agreementsMock.findIndex((a) => a.id === id);
      if (idx !== -1) agreementsMock.splice(idx, 1);
      return;
    }
    const { request } = useApi();
    await request(`/agreements/${id}`, { method: "DELETE" });
  };

  return { list, listWithRefs, get, create, update, remove };
};
