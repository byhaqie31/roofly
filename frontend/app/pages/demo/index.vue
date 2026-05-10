<script setup lang="ts">
import { Building2, DoorOpen } from "lucide-vue-next";
import Button from "~/components/ui/Button.vue";

definePageMeta({ layout: "default" });

useHead({ title: () => "Roofly demo" });

const auth = useAuthStore();
const loadingRole = ref<"owner" | "tenant" | null>(null);

const enter = async (role: "owner" | "tenant") => {
  loadingRole.value = role;
  await auth.login(`${role}@roofly.my`, "password");
  await navigateTo(role === "owner" ? "/owner" : "/tenant");
  loadingRole.value = null;
};
</script>

<template>
  <main class="mx-auto max-w-3xl px-6 py-16">
    <header class="text-center mb-10">
      <p class="text-micro font-medium uppercase tracking-wider text-ink-muted mb-3">
        Demo environment
      </p>
      <h1 class="text-display font-semibold tracking-snug">
        Welcome to the Roofly demo
      </h1>
      <p class="mt-3 text-body text-ink-muted">
        Pick a role to start exploring. All data shown is sample data — nothing you do here is saved.
      </p>
    </header>

    <section class="grid sm:grid-cols-2 gap-3">
      <Button
        variant="primary"
        size="lg"
        :loading="loadingRole === 'owner'"
        :disabled="loadingRole !== null"
        @click="enter('owner')"
      >
        <Building2 :size="18" :stroke-width="1.5" />
        Continue as owner
      </Button>

      <Button
        variant="ghost"
        size="lg"
        disabled
      >
        <DoorOpen :size="18" :stroke-width="1.5" />
        Continue as tenant — coming soon
      </Button>
    </section>
  </main>
</template>
