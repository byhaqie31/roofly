<script setup lang="ts">
import { ref } from "vue";
import type {
  NotificationEvent,
  NotificationPreferences,
  OwnerAccount,
} from "~/types/owner";
import { useToast } from "~/composables/useToast";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import Pill from "~/components/ui/Pill.vue";

const props = defineProps<{ account: OwnerAccount }>();
const emit = defineEmits<{ saved: [account: OwnerAccount] }>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

// Local working copy — only persisted on submit.
const events = ref<NotificationPreferences["events"]>({
  ...props.account.notifications.events,
});

type EventIcon = "Calendar" | "FileText" | "Wallet" | "Wrench" | "UserPlus";

const eventList: { key: NotificationEvent; iconName: EventIcon }[] = [
  { key: "rent_reminder", iconName: "Calendar" },
  { key: "agreement_expiry", iconName: "FileText" },
  { key: "payment_received", iconName: "Wallet" },
  { key: "ticket_update", iconName: "Wrench" },
  { key: "invite_accepted", iconName: "UserPlus" },
];

const onSubmit = async () => {
  submitting.value = true;
  try {
    const updated = await useOwnerSettings().updateNotifications({
      events: { ...events.value },
    });
    emit("saved", updated);
    show(t("common.savedToast"), "success");
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <form class="space-y-8" @submit.prevent="onSubmit">
    <section class="space-y-2">
      <header
        class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3"
      >
        <div>
          <h2 class="text-card-title font-semibold text-ink">
            {{ t("owner.settings.notifications.channelTitle") }}
          </h2>
          <p class="mt-1 text-caption text-ink-muted">
            {{ t("owner.settings.notifications.channelHelp") }}
          </p>
        </div>
        <Pill tone="draft" class="self-end shrink-0 sm:self-start">
          {{ t("owner.settings.notifications.channelStatus.comingSoon") }}
        </Pill>
      </header>
    </section>

    <section class="space-y-4 border-t border-line-passive pt-6">
      <header>
        <h2 class="text-card-title font-semibold text-ink">
          {{ t("owner.settings.notifications.eventsTitle") }}
        </h2>
        <p class="mt-1 text-caption text-ink-muted">
          {{ t("owner.settings.notifications.eventsHelp") }}
        </p>
      </header>

      <ul class="divide-y divide-line-passive">
        <li
          v-for="ev in eventList"
          :key="ev.key"
          class="flex items-center justify-between gap-4 py-3"
        >
          <div class="flex min-w-0 items-center gap-3">
            <span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-surface-page text-ink-muted"
            >
              <Icon :name="ev.iconName" :size="16" />
            </span>
            <div class="min-w-0">
              <p class="text-body text-ink">
                {{ t(`owner.settings.notifications.events.${ev.key}.title`) }}
              </p>
              <p class="text-caption text-ink-muted">
                {{ t(`owner.settings.notifications.events.${ev.key}.help`) }}
              </p>
            </div>
          </div>
          <label class="inline-flex shrink-0 cursor-pointer items-center">
            <input
              v-model="events[ev.key]"
              type="checkbox"
              class="peer sr-only"
            />
            <span
              class="relative h-5 w-9 rounded-full bg-line-passive transition peer-checked:bg-ink peer-focus-visible:shadow-focus"
            >
              <span
                class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-surface-raised transition peer-checked:translate-x-4"
                :class="events[ev.key] ? 'translate-x-4' : ''"
              />
            </span>
          </label>
        </li>
      </ul>
    </section>

    <div class="flex justify-end">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.settings.save") }}
      </Button>
    </div>
  </form>
</template>
