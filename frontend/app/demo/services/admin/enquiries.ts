import type { AdminEnquiriesService } from "~/services/contracts/admin/enquiries";
import { enquiriesMock } from "~/demo/data/enquiries";
import { paginate } from "~/demo/services/admin/paginate";
import { pushAudit } from "~/demo/data/admin";

// Mirrors Admin\EnquiryController: newest first, q matches name/email/message.
export const demoAdminEnquiries: AdminEnquiriesService = {
  async list(query) {
    let rows = [...enquiriesMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    const q = query.q?.trim().toLowerCase();
    if (q) rows = rows.filter((e) => [e.name, e.email, e.message].some((v) => v.toLowerCase().includes(q)));
    if (query.status) rows = rows.filter((e) => e.status === query.status);
    if (query.type) rows = rows.filter((e) => e.type === query.type);
    const page = paginate(rows, query.page, query.perPage);
    return structuredClone({ data: page.data, meta: { ...page.meta, newCount: enquiriesMock.filter((e) => e.status === "new").length } });
  },

  async update(id, patch) {
    const e = enquiriesMock.find((x) => x.id === id);
    if (!e) throw Object.assign(new Error("Not found"), { statusCode: 404 });
    const before = { status: e.status, adminNote: e.adminNote };
    if (patch.status && patch.status !== e.status) {
      e.status = patch.status;
      e.statusChangedAt = new Date().toISOString();
    }
    if (patch.adminNote !== undefined) e.adminNote = patch.adminNote;
    e.handledByName = useAuthStore().user?.name ?? null;
    pushAudit({ action: "enquiry.updated", actorId: useAuthStore().user?.id ?? null, subjectType: "enquiry", subjectId: id, before, after: { status: e.status, adminNote: e.adminNote }, reason: null });
    return structuredClone(e);
  },
};
