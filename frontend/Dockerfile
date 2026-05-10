# Roofly frontend — multi-stage Dockerfile.
#
# Targets:
#   dev    — Node 22 with full dev deps + HMR. Used by docker-compose.override.yml
#            (auto-loaded for local `docker compose up`).
#   runner — minimal runtime serving the Nuxt SSR build. Used by docker-compose.yml
#            (the production deploy on the VPS, invoked with -f to bypass override).
#
# Build the prod image directly:
#   docker build -t roofly-frontend --target runner ./frontend


# ─────────────────────────────────────────────────────────────────────────────
# Stage 1 — dependencies
# ─────────────────────────────────────────────────────────────────────────────
FROM node:22-alpine AS deps

WORKDIR /app

# Compatibility for some native modules on alpine
RUN apk add --no-cache libc6-compat

COPY package.json package-lock.json* ./

# `npm ci` for reproducibility; if the host-generated lockfile is missing
# Linux-specific optionalDependencies (esbuild platform variants) the strict
# install fails — fall back to `npm install` so the build doesn't block.
# When the lockfile is regenerated inside Docker (or via CI on Linux), the
# fallback never triggers.
RUN npm ci --include=optional --no-audit --no-fund \
    || (echo "npm ci failed — falling back to npm install" \
        && npm install --no-audit --no-fund)


# ─────────────────────────────────────────────────────────────────────────────
# Stage 2a — dev (HMR, used locally via docker-compose.override.yml)
# ─────────────────────────────────────────────────────────────────────────────
FROM deps AS dev

COPY . .

EXPOSE 3000

# Bind to 0.0.0.0 so the host can reach Nuxt across the bridge network.
# The compose override mounts ./frontend over /app at runtime so source
# changes hot-reload without a rebuild.
CMD ["npm", "run", "dev"]


# ─────────────────────────────────────────────────────────────────────────────
# Stage 2b — builder (compiles Nuxt for prod)
# ─────────────────────────────────────────────────────────────────────────────
FROM deps AS builder

COPY . .
RUN npm run build


# ─────────────────────────────────────────────────────────────────────────────
# Stage 3 — runner (the production runtime image, ~10x smaller than dev)
# ─────────────────────────────────────────────────────────────────────────────
FROM node:22-alpine AS runner

WORKDIR /app

# Only the compiled SSR output ships in the prod image — no source, no
# node_modules, no dev tooling.
COPY --from=builder /app/.output ./.output

ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=3000

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
