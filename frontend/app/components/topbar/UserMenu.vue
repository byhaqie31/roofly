<script setup lang="ts">
import { LogOut, User as UserIcon } from "lucide-vue-next";
import { ref } from "vue";

const auth = useAuthStore();
const open = ref(false);

const onLogout = async () => {
  await auth.logout();
  await navigateTo("/auth/login");
};

const initials = computed(() => {
  const n = auth.user?.name ?? "";
  return n
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((s) => s[0]?.toUpperCase())
    .join("");
});
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="inline-flex items-center justify-center h-9 w-9 rounded-pill bg-accent-soft text-accent text-caption font-semibold hover:opacity-90 focus-visible:shadow-focus transition"
      :aria-label="auth.user?.name ?? 'User menu'"
      @click="open = !open"
    >
      {{ initials || "·" }}
    </button>
    <Transition name="fade">
      <div
        v-if="open"
        class="absolute right-0 mt-2 w-56 rounded-lg border border-line-passive bg-surface-page shadow-modal p-2 z-50"
        @mouseleave="open = false"
      >
        <div class="px-3 py-2 border-b border-line-passive">
          <div class="text-caption font-medium text-ink truncate">{{ auth.user?.name }}</div>
          <div class="text-micro text-ink-muted truncate">{{ auth.user?.email }}</div>
        </div>
        <button
          type="button"
          class="mt-1 flex items-center gap-2 w-full px-3 py-2 rounded-sm text-caption text-ink-strong hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
          @click="onLogout"
        >
          <LogOut :size="16" :stroke-width="1.5" />
          {{ $t("auth.logout") }}
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 120ms ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
