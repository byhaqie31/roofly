<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import { useToast } from "~/composables/useToast";
import type { TicketComment, TicketPriority, TicketStatus } from "~/types/ticket";
import type { TicketWithRefs } from "~/services/useTickets";

definePageMeta({ layout: "tenant" });
const route = useRoute();
const { t } = useI18n();
const { show } = useToast();
const { tenantId } = useTenantSession();

const data = ref<TicketWithRefs | null>(null);
const loading = ref(true);
const newComment = ref("");
const submitting = ref(false);

onMounted(async () => {
  try {
    const found = await useTickets().getTicketWithRefsForTenant(
      route.params.id as string,
    );
    // Tenant scope: only show issues this tenant reported.
    data.value =
      found && found.ticket.reporterId === tenantId.value ? found : null;
  } finally {
    loading.value = false;
  }
});

useHead({
  title: () => data.value?.ticket.title ?? t("tenant.nav.tickets"),
});

const priorityTone = (p: TicketPriority) =>
  p === "urgent" ? "overdue" : p;

const statusTone = (s: TicketStatus) => {
  switch (s) {
    case "new":
      return "pending";
    case "in_progress":
      return "active";
    case "resolved":
      return "paid";
    case "reopened":
      return "overdue";
  }
};

const onSubmitComment = async () => {
  if (!data.value || !newComment.value.trim() || !tenantId.value) return;
  submitting.value = true;
  try {
    const created: TicketComment = await useTickets().addCommentForTenant({
      ticketId: data.value.ticket.id,
      authorId: tenantId.value,
      authorRole: "tenant",
      body: newComment.value.trim(),
    });
    data.value.comments.push(created);
    newComment.value = "";
    show(t("tenant.tickets.detail.commentToast"), "success");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    submitting.value = false;
  }
};

const formatDateTime = (iso: string) =>
  new Date(iso).toLocaleString("en-MY", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
</script>

<template>
  <div>
    <NuxtLink
      to="/tenant/tickets"
      class="mb-6 inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"
    >
      <Icon name="ArrowLeft" :size="14" />
      {{ t("tenant.tickets.detail.back") }}
    </NuxtLink>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <Card v-else-if="!data" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("tenant.tickets.detail.notFound") }}
      </p>
    </Card>

    <template v-else>
      <header class="mb-6">
        <div class="mb-3 flex flex-wrap items-center gap-2">
          <Pill :tone="statusTone(data.ticket.status)">
            {{ t(`tenant.tickets.status.${data.ticket.status}`) }}
          </Pill>
          <Pill :tone="priorityTone(data.ticket.priority)">
            {{ t(`tenant.tickets.priority.${data.ticket.priority}`) }}
          </Pill>
          <span class="text-caption text-ink-muted">
            {{ t(`tenant.tickets.category.${data.ticket.category}`) }}
          </span>
        </div>
        <h1 class="text-display-sub font-semibold tracking-snug text-ink">
          {{ data.ticket.title }}
        </h1>
        <p class="mt-2 text-caption text-ink-muted">
          <Icon name="Building2" :size="12" class="mr-1 inline" />
          {{ data.property?.name ?? "—" }} · {{ data.unit?.label ?? "—" }}
          <span class="text-ink-faint tabular-nums">
            · {{ t("tenant.tickets.detail.reportedOn", { date: formatDateTime(data.ticket.createdAt) }) }}
          </span>
        </p>
      </header>

      <div class="space-y-4 sm:space-y-6">
        <Card padding="loose">
          <h2 class="mb-2 text-caption font-semibold uppercase tracking-wide text-ink-muted">
            {{ t("tenant.tickets.detail.description") }}
          </h2>
          <p class="whitespace-pre-line text-body text-ink">
            {{ data.ticket.description }}
          </p>
        </Card>

        <div
          class="flex items-start gap-2 rounded-md border border-line-passive bg-surface-page p-3 text-caption text-ink-muted"
        >
          <Icon name="Info" :size="14" class="mt-0.5 shrink-0" />
          {{ t("tenant.tickets.detail.statusNote") }}
        </div>

        <Card padding="loose">
          <header class="mb-4 flex items-center justify-between">
            <h2 class="text-card-title font-semibold text-ink">
              {{ t("tenant.tickets.detail.comments") }}
            </h2>
            <span class="text-caption tabular-nums text-ink-faint">
              {{ data.comments.length }}
            </span>
          </header>

          <ul v-if="data.comments.length > 0" class="space-y-4">
            <li v-for="c in data.comments" :key="c.id" class="flex gap-3">
              <div
                :class="[
                  'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-caption font-semibold',
                  c.authorRole === 'tenant'
                    ? 'bg-status-active-soft text-status-active'
                    : 'bg-ink text-surface-raised',
                ]"
              >
                <Icon
                  :name="c.authorRole === 'tenant' ? 'User' : 'Building2'"
                  :size="14"
                />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-caption text-ink-muted">
                  {{
                    c.authorRole === "tenant"
                      ? t("tenant.tickets.detail.you")
                      : t("tenant.tickets.detail.landlord")
                  }}
                  <span class="text-ink-faint tabular-nums">
                    · {{ formatDateTime(c.createdAt) }}
                  </span>
                </p>
                <p class="mt-1 whitespace-pre-line text-body text-ink">
                  {{ c.body }}
                </p>
              </div>
            </li>
          </ul>

          <p v-else class="py-4 text-center text-caption text-ink-muted">
            {{ t("tenant.tickets.detail.noComments") }}
          </p>

          <form
            class="mt-6 space-y-3 border-t border-line-passive pt-4"
            @submit.prevent="onSubmitComment"
          >
            <label class="block text-caption font-normal text-ink-strong">
              {{ t("tenant.tickets.detail.addComment") }}
            </label>
            <textarea
              v-model="newComment"
              rows="3"
              :placeholder="t('tenant.tickets.detail.commentPlaceholder')"
              class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body text-ink outline-none transition focus:border-line-interactive focus:shadow-focus"
            />
            <div class="flex justify-end">
              <Button
                type="submit"
                variant="primary"
                size="sm"
                :loading="submitting"
                :disabled="!newComment.trim()"
              >
                {{ t("tenant.tickets.detail.post") }}
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </template>
  </div>
</template>
