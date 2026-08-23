import { isTrackedPath } from "~/composables/useTrack";

/** page_view on every client-side navigation to a public/marketing path (spec § 3). */
export default defineNuxtPlugin((nuxtApp) => {
  const router = useRouter();
  const { track } = useTrack();
  router.afterEach((to) => {
    if (isTrackedPath(to.path)) nuxtApp.runWithContext(() => track("page_view"));
  });
});
