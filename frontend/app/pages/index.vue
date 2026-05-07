<script setup lang="ts">
definePageMeta({ layout: false });

const auth = useAuthStore();

if (!auth.isAuthenticated) {
  await navigateTo("/auth/login");
} else if (auth.isOwner) {
  await navigateTo("/owner");
} else if (auth.isTenant) {
  await navigateTo("/tenant");
} else {
  await navigateTo("/auth/login");
}
</script>

<template>
  <div />
</template>
