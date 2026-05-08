<script setup lang="ts">
import { ref } from "vue";
import type {
  NotificationEvent,
  NotificationPreferences,
  OwnerAccount,
} from "~/types/owner";
import { useToast } from "~/composables/useToast";
import Card from "~/components/ui/Card.vue";
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
  <form class="space-y-6" @submit.prevent="onSubmit">
    <Card padding="loose" tone="flat">
      <header class="mb-4 flex items-center gap-3">
        <Pill tone="draft">
          {{ t("owner.settings.notifications.channelStatus.phase4") }}
        </Pill>
        <div>
          <h2 class="text-card-title font-semibold text-ink">
            {{ t("owner.settings.notifications.channelTitle") }}
          </h2>
          <p class="mt-1 text-caption text-ink-muted">
            {{ t("owner.settings.notifications.channelHelp") }}
          </p>
        </div>
      </header>
    </Card>

    <Card padding="loose" tone="flat">
      <header class="mb-4">
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
          <div class="flex items-center gap-3">
            <span
              class="flex h-9 w-9 items-center justify-center rounded-md bg-surface-page text-ink-muted"
            >
              <Icon :name="ev.iconName" :size="16" />
            </span>
            <div>
              <p class="text-body text-ink">
                {{ t(`owner.settings.notifications.events.${ev.key}.title`) }}
              </p>
              <p class="text-caption text-ink-muted">
                {{ t(`owner.settings.notifications.events.${ev.key}.help`) }}
              </p>
            </div>
          </div>
          <label class="inline-flex cursor-pointer items-center">
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
    </Card>

    <div class="flex justify-end">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.settings.save") }}
      </Button>
    </div>
  </form>
</template>
