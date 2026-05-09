<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import { useToast } from "~/composables/useToast";
import { TICKET_TRANSITIONS } from "~/types/ticket";
import type {
  Ticket,
  TicketComment,
  TicketPriority,
  TicketStatus,
} from "~/types/ticket";
import type { TicketWithRefs } from "~/services/useTickets";

definePageMeta({ layout: "owner" });

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { show } = useToast();
const { public: { features } } = useRuntimeConfig();
const photosEnabled = features.documents;

const data = ref<TicketWithRefs | null>(null);
const loading = ref(true);
const submittingComment = ref(false);
const transitioning = ref(false);
const newComment = ref("");

const load = async () => {
  loading.value = true;
  try {
    data.value = await useTickets().getWithRefs(route.params.id as string);
  } finally {
    loading.value = false;
  }
};

onMounted(load);

useHead({
  title: () => data.value?.ticket.title ?? t("owner.nav.maintenance"),
});

const priorityTone = (p: TicketPriority) => {
  switch (p) {
    case "low": return "low";
    case "medium": return "medium";
    case "high": return "high";
    case "urgent": return "overdue";
  }
};

const statusTone = (s: TicketStatus) => {
  switch (s) {
    case "new": return "pending";
    case "in_progress": return "active";
    case "resolved": return "paid";
    case "reopened": return "overdue";
  }
};

const allowedTransitions = computed<TicketStatus[]>(() =>
  data.value ? TICKET_TRANSITIONS[data.value.ticket.status] : [],
);

const onTransition = async (next: TicketStatus) => {
  if (!data.value) return;
  transitioning.value = true;
  try {
    const updated = await useTickets().transitionStatus(
      data.value.ticket.id,
      next,
    );
    data.value = { ...data.value, ticket: updated };
    show(
      t("owner.tickets.detail.statusToast", {
        status: t(`owner.tickets.status.${next}`),
      }),
      "success",
    );
  } finally {
    transitioning.value = false;
  }
};

const onSubmitComment = async () => {
  if (!data.value || !newComment.value.trim()) return;
  submittingComment.value = true;
  try {
    const created: TicketComment = await useTickets().addComment({
      ticketId: data.value.ticket.id,
      authorId: "owner-1",
      authorRole: "owner",
      body: newComment.value.trim(),
    });
    data.value.comments.push(created);
    newComment.value = "";
    show(t("owner.tickets.detail.commentToast"), "success");
  } finally {
    submittingComment.value = false;
  }
};

const formatDateTime = (iso: string) => {
  const d = new Date(iso);
  return d.toLocaleString("en-MY", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};
</script>

<template>
  <div>
    <NuxtLink
      to="/owner/maintenance"
      class="mb-6 inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"
    >
      <Icon name="ArrowLeft" :size="14" />
      {{ t("owner.tickets.detail.back") }}
    </NuxtLink>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <Card v-else-if="!data" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("owner.tickets.detail.notFound") }}
      </p>
    </Card>

    <template v-else>
      <header class="mb-6">
        <div class="mb-3 flex flex-wrap items-center gap-2">
          <Pill :tone="priorityTone(data.ticket.priority)">
            {{ t(`owner.tickets.priority.${data.ticket.priority}`) }}
          </Pill>
          <Pill :tone="statusTone(data.ticket.status)">
            {{ t(`owner.tickets.status.${data.ticket.status}`) }}
          </Pill>
          <span class="text-caption text-ink-muted">
            {{ t(`owner.tickets.category.${data.ticket.category}`) }}
          </span>
        </div>
        <h1 class="text-display-sub font-semibold tracking-snug text-ink">
          {{ data.ticket.title }}
        </h1>
        <p class="mt-2 text-caption text-ink-muted">
          <Icon name="MapPin" :size="12" class="mr-1 inline" />
          <NuxtLink
            v-if="data.property"
            :to="`/owner/properties/${data.property.id}`"
            class="underline-offset-2 hover:underline"
          >
            {{ data.property.name }}
          </NuxtLink>
          <span v-else>—</span>
          · {{ data.unit?.label ?? "—" }} ·
          <Icon
            :name="data.ticket.reporterRole === 'tenant' ? 'User' : 'Building2'"
            :size="12"
            class="mr-1 inline"
          />
          <NuxtLink
            v-if="data.reporter"
            :to="`/owner/tenants/${data.reporter.id}`"
            class="underline-offset-2 hover:underline"
          >
            {{ data.reporter.name }}
          </NuxtLink>
          <span v-else>{{ t("owner.tickets.byOwner") }}</span>
          · {{ formatDateTime(data.ticket.createdAt) }}
        </p>
      </header>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
          <Card padding="loose">
            <h2 class="mb-2 text-caption font-semibold uppercase tracking-wide text-ink-muted">
              {{ t("owner.tickets.detail.description") }}
            </h2>
            <p class="whitespace-pre-line text-body text-ink">
              {{ data.ticket.description }}
            </p>
          </Card>

          <Card padding="loose">
            <header class="mb-4 flex items-center justify-between">
              <h2 class="text-card-title font-semibold text-ink">
                {{ t("owner.tickets.detail.comments") }}
              </h2>
              <span class="text-caption tabular-nums text-ink-faint">
                {{ data.comments.length }}
              </span>
            </header>

            <ul
              v-if="data.comments.length > 0"
              class="space-y-4"
            >
              <li
                v-for="c in data.comments"
                :key="c.id"
                class="flex gap-3"
              >
                <div
                  :class="[
                    'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-caption font-semibold',
                    c.authorRole === 'owner'
                      ? 'bg-ink text-surface-raised'
                      : 'bg-status-active-soft text-status-active',
                  ]"
                >
                  <Icon
                    :name="c.authorRole === 'owner' ? 'Building2' : 'User'"
                    :size="14"
                  />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-caption text-ink-muted">
                    {{
                      c.authorRole === "owner"
                        ? t("owner.tickets.byOwner")
                        : (data.reporter?.name ?? t("owner.tickets.detail.tenant"))
                    }}
                    <span class="text-ink-faint">
                      · {{ formatDateTime(c.createdAt) }}
                    </span>
                  </p>
                  <p class="mt-1 whitespace-pre-line text-body text-ink">
                    {{ c.body }}
                  </p>
                </div>
              </li>
            </ul>

            <p
              v-else
              class="py-4 text-center text-caption text-ink-muted"
            >
              {{ t("owner.tickets.detail.noComments") }}
            </p>

            <form
              class="mt-6 space-y-3 border-t border-line-passive pt-4"
              @submit.prevent="onSubmitComment"
            >
              <label
                class="block text-caption font-normal text-ink-strong"
              >
                {{ t("owner.tickets.detail.addComment") }}
              </label>
              <textarea
                v-model="newComment"
                rows="3"
                :placeholder="t('owner.tickets.detail.commentPlaceholder')"
                class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body text-ink outline-none transition focus:border-line-interactive focus:shadow-focus"
              />
              <div class="flex justify-end">
                <Button
                  type="submit"
                  variant="primary"
                  size="sm"
                  :loading="submittingComment"
                  :disabled="!newComment.trim()"
                >
                  {{ t("owner.tickets.detail.postComment") }}
                </Button>
              </div>
            </form>
          </Card>
        </div>

        <aside class="space-y-6">
          <Card padding="loose">
            <h2 class="mb-3 text-caption font-semibold uppercase tracking-wide text-ink-muted">
              {{ t("owner.tickets.detail.statusActions") }}
            </h2>
            <p class="mb-4 text-caption text-ink-muted">
              {{ t("owner.tickets.detail.statusHelp") }}
            </p>
            <div class="flex flex-col gap-2">
              <Button
                v-for="next in allowedTransitions"
                :key="next"
                variant="ghost"
                size="md"
                :loading="transitioning"
                @click="onTransition(next)"
              >
                <Icon name="ArrowRight" :size="14" class="mr-1.5" />
                {{ t(`owner.tickets.detail.transitions.${next}`) }}
              </Button>
              <p
                v-if="allowedTransitions.length === 0"
                class="text-caption text-ink-muted"
              >
                {{ t("owner.tickets.detail.noTransitions") }}
              </p>
            </div>
          </Card>

          <Card padding="loose">
            <h2 class="mb-3 text-caption font-semibold uppercase tracking-wide text-ink-muted">
              {{ t("owner.tickets.detail.photos.title") }}
            </h2>
            <EmptyState
              v-if="photosEnabled"
              icon="Camera"
              :title="t('owner.tickets.detail.photos.placeholderTitle')"
              :description="t('owner.tickets.detail.photos.placeholderHelp')"
            />
            <p
              v-else
              class="text-caption text-ink-muted"
            >
              {{ t("owner.tickets.detail.photos.help") }}
            </p>
          </Card>
        </aside>
      </div>
    </template>
  </div>
</template>
