import { defineConfig } from "vitest/config";
import { fileURLToPath } from "node:url";

// Unit tests for pure modules only (no Nuxt auto-imports). `~` mirrors Nuxt's alias.
export default defineConfig({
  resolve: { alias: { "~": fileURLToPath(new URL("./app", import.meta.url)) } },
  test: { include: ["app/**/*.test.ts"], environment: "node" },
});
