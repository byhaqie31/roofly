<script setup lang="ts">
import Pill from "~/components/ui/Pill.vue";
import { formatAdminDateTime } from "~/utils/adminDate";
import type { AuditAction, AuditEntry } from "~/types/admin";

withDefaults(defineProps<{ entries: AuditEntry[]; showActor?: boolean }>(), { showActor: true });
const { t } = useI18n();

// Note: string-suffix matching ("suspended") is unsafe here — "owner.unsuspended" ends with
// "suspended" too, so tones are an explicit per-action map instead.
type Tone = "neutral" | "active" | "terminated" | "maintenance" | "draft";
const TONE: Record<AuditAction, Tone> = {
  "admin.login": "neutral",
  "admin.invite_sent": "neutral",
  "admin.invite_accepted": "active",
  "admin.permissions_changed": "neutral",
  "admin.disabled": "terminated",
  "admin.enabled": "active",
  "owner.warned": "maintenance",
  "owner.suspended": "terminated",
  "owner.unsuspended": "active",
  "tenant.invite_resent": "neutral",
  "analytics.exported": "neutral",
  "owner.signup": "draft",
};

const fmt = formatAdminDateTime;
const hasDiff = (e: AuditEntry) => Object.keys(e.before).length > 0 || Object.keys(e.after).length > 0;
</script>

<template>
  <ul class="divide-y divide-line-passive">
    <li v-for="e in entries" :key="e.id" class="py-3">
      <div class="flex flex-wrap items-center gap-2">
        <Pill :tone="TONE[e.action]">{{ t(`admin.audit.actions.${e.action}`) }}</Pill>
        <span class="text-micro text-ink-faint tabular-nums">{{ fmt(e.createdAt) }}</span>
        <span v-if="showActor && e.actorName" class="text-micro text-ink-muted">· {{ e.actorName }}</span>
      </div>
      <p class="mt-1 text-body font-medium text-ink">
        {{ e.subjectName ?? "—" }}
        <span v-if="e.reason" class="ml-2 text-caption font-normal text-ink-muted">— {{ e.reason }}</span>
      </p>
      <details v-if="hasDiff(e)" class="mt-1">
        <summary class="cursor-pointer text-micro text-ink-muted">{{ t("admin.audit.details") }}</summary>
        <pre class="mt-1 overflow-x-auto rounded-sm bg-surface-page p-2 text-micro text-ink-muted">{{ JSON.stringify({ before: e.before, after: e.after }, null, 2) }}</pre>
      </details>
    </li>
  </ul>
</template>
