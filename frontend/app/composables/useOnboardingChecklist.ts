import { computed, ref } from "vue";
import { buildChecklist, type ChecklistStep } from "~/utils/onboardingChecklist";

/**
 * Loads the four lists the checklist needs and exposes the computed steps.
 * Skips the network entirely when the card wouldn't render (dismissed).
 */
export const useOnboardingChecklist = () => {
  const auth = useAuthStore();
  const loading = ref(false);
  const steps = ref<ChecklistStep[]>([]);

  const dismissed = computed(() => auth.user?.checklistDismissedAt !== null && auth.user?.checklistDismissedAt !== undefined);
  const allDone = computed(() => steps.value.length > 0 && steps.value.every((s) => s.done));
  const visible = computed(() => !dismissed.value && !allDone.value && steps.value.length > 0);
  const doneCount = computed(() => steps.value.filter((s) => s.done).length);

  const load = async () => {
    if (dismissed.value || !auth.isOwner) return;
    loading.value = true;
    try {
      const [properties, units, tenants, agreements] = await Promise.all([
        useProperties().getProperties(),
        useUnits().getUnits(),
        useTenants().getTenants(),
        useAgreements().getAgreements(),
      ]);
      steps.value = buildChecklist({
        purposes: auth.user?.purposes ?? [],
        properties, units, tenants, agreements,
      });
    } finally {
      loading.value = false;
    }
  };

  const dismiss = async () => {
    // Caller (GettingStartedCard's @dismiss) fires this without awaiting the
    // result, so a rejection here must be handled locally — otherwise a
    // failed request becomes an unhandled promise rejection and the card's
    // own toast would still claim success. Show the outcome here instead.
    const { t } = useI18n();
    const { show } = useToast();
    try {
      const user = await useOwnerSettings().setChecklistDismissed(true);
      auth.setUser(user);
      show(t("owner.checklist.dismissedToast"), "default");
    } catch {
      show(t("common.genericError"), "danger");
    }
  };

  return { load, loading, steps, visible, doneCount, dismiss };
};
