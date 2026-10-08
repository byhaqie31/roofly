<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { PayoutAccount } from "~/types/payout";
import { bankLabel } from "~/config/banks";
import { maskAccountNumber } from "~/utils/paymentClaim";
import { useToast } from "~/composables/useToast";
import Button from "~/components/ui/Button.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Modal from "~/components/ui/Modal.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import PayoutAccountFormModal from "~/components/owner/PayoutAccountFormModal.vue";

/**
 * Settings → Payouts (spec 2026-10-08 § 6). Manual DuitNow / bank transfer
 * accounts are live; online payments through a gateway show as Coming soon.
 */
const { t } = useI18n();
const { show } = useToast();

const accounts = ref<PayoutAccount[]>([]);
const loading = ref(true);
const formOpen = ref(false);
const editing = ref<PayoutAccount | null>(null);
const removing = ref<PayoutAccount | null>(null);
const busyId = ref<string | null>(null);

const load = async () => {
  accounts.value = await usePayoutAccounts().list();
};

onMounted(async () => {
  try {
    await load();
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    loading.value = false;
  }
});

const openAdd = () => {
  editing.value = null;
  formOpen.value = true;
};

const openEdit = (account: PayoutAccount) => {
  editing.value = account;
  formOpen.value = true;
};

const onSetDefault = async (account: PayoutAccount) => {
  busyId.value = account.id;
  try {
    accounts.value = await usePayoutAccounts().setDefault(account.id);
    show(t("owner.settings.payouts.defaultToast", { label: account.label }), "success");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    busyId.value = null;
  }
};

const onConfirmRemove = async () => {
  const account = removing.value;
  if (!account) return;
  busyId.value = account.id;
  try {
    await usePayoutAccounts().remove(account.id);
    await load();
    show(t("owner.settings.payouts.deletedToast"), "success");
    removing.value = null;
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    busyId.value = null;
  }
};
</script>

<template>
  <div class="space-y-8">
    <section class="space-y-4">
      <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 class="text-card-title font-semibold text-ink">
            {{ t("owner.settings.payouts.title") }}
          </h2>
          <p class="mt-1 text-caption text-ink-muted">
            {{ t("owner.settings.payouts.help") }}
          </p>
        </div>
        <Button v-if="accounts.length > 0" variant="ghost" size="sm" class="self-start" @click="openAdd">
          <Icon name="Plus" :size="14" />
          {{ t("owner.settings.payouts.add") }}
        </Button>
      </header>

      <p v-if="loading" class="text-caption text-ink-muted">{{ t("common.loading") }}</p>

      <div v-else-if="accounts.length === 0" class="rounded-md border border-dashed border-line-passive">
        <EmptyState
          icon="Landmark"
          :title="t('owner.settings.payouts.emptyTitle')"
          :description="t('owner.settings.payouts.emptyHelp')"
        />
        <div class="-mt-8 flex justify-center pb-8">
          <Button variant="primary" @click="openAdd">
            {{ t("owner.settings.payouts.add") }}
          </Button>
        </div>
      </div>

      <ul v-else class="space-y-3">
        <li
          v-for="account in accounts"
          :key="account.id"
          class="rounded-md border border-line-passive p-4"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-body font-semibold text-ink">{{ account.label }}</span>
                <Pill v-if="account.isDefault" tone="active">
                  {{ t("owner.settings.payouts.default") }}
                </Pill>
              </div>
              <p class="text-caption text-ink-muted">
                {{ bankLabel(account.bank) }} · {{ account.accountHolderName }}
              </p>
              <p v-if="account.accountNumber" class="text-caption tabular-nums text-ink">
                {{ maskAccountNumber(account.accountNumber) }}
              </p>
              <p v-if="account.duitnowId" class="text-caption text-ink">
                {{ t("owner.settings.payouts.duitnowLine", {
                  type: t(`owner.settings.payouts.duitnowTypes.${account.duitnowIdType}`),
                  id: account.duitnowId,
                }) }}
              </p>
              <p v-if="account.agreementCount > 0" class="text-micro text-ink-faint">
                {{ t("owner.settings.payouts.usedBy", { count: account.agreementCount }) }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-2 self-start">
              <Button
                v-if="!account.isDefault"
                variant="ghost"
                size="sm"
                :loading="busyId === account.id"
                @click="onSetDefault(account)"
              >
                {{ t("owner.settings.payouts.setDefault") }}
              </Button>
              <Button variant="ghost" size="sm" @click="openEdit(account)">
                {{ t("owner.settings.payouts.edit") }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                :aria-label="t('owner.settings.payouts.delete')"
                :title="t('owner.settings.payouts.delete')"
                @click="removing = account"
              >
                <Icon name="Trash2" :size="14" />
              </Button>
            </div>
          </div>
        </li>
      </ul>
    </section>

    <section class="space-y-3 border-t border-line-passive pt-6">
      <header class="flex flex-wrap items-center gap-2">
        <h2 class="text-card-title font-semibold text-ink">
          {{ t("owner.settings.payouts.online.title") }}
        </h2>
        <Pill tone="neutral">{{ t("owner.settings.payouts.online.comingSoon") }}</Pill>
      </header>
      <p class="text-caption text-ink-muted">
        {{ t("owner.settings.payouts.online.help") }}
      </p>
      <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <li class="flex items-center gap-3 rounded-md border border-line-passive p-3 text-caption text-ink-muted">
          <Icon name="Landmark" :size="18" class="text-ink-faint" />
          {{ t("owner.settings.payouts.online.fpx") }}
        </li>
        <li class="flex items-center gap-3 rounded-md border border-line-passive p-3 text-caption text-ink-muted">
          <Icon name="CreditCard" :size="18" class="text-ink-faint" />
          {{ t("owner.settings.payouts.online.card") }}
        </li>
      </ul>
    </section>

    <PayoutAccountFormModal v-model:open="formOpen" :account="editing" @saved="load" />

    <Modal
      :open="removing !== null"
      :title="t('owner.settings.payouts.deleteConfirm.title')"
      size="sm"
      @update:open="(v) => { if (!v) removing = null }"
    >
      <p class="text-body text-ink">
        {{ t("owner.settings.payouts.deleteConfirm.body", { label: removing?.label ?? "" }) }}
      </p>
      <p v-if="removing && removing.agreementCount > 0" class="mt-3 text-caption text-ink-muted">
        {{ t("owner.settings.payouts.deleteConfirm.agreements", { count: removing.agreementCount }) }}
      </p>
      <template #footer>
        <Button variant="ghost" :disabled="busyId !== null" @click="removing = null">
          {{ t("common.cancel") }}
        </Button>
        <Button variant="accent" :loading="busyId !== null" @click="onConfirmRemove">
          {{ t("owner.settings.payouts.deleteConfirm.confirm") }}
        </Button>
      </template>
    </Modal>
  </div>
</template>
