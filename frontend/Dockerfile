# Roofly frontend — Nuxt 3 dev container (Node 22-alpine).
# Production build will get a separate multi-stage Dockerfile in Phase 6.

FROM node:22-alpine

WORKDIR /app

# Compatibility for some native modules on alpine
RUN apk add --no-cache libc6-compat

# Install deps first (cached layer).
# Using `npm install` instead of `npm ci` so the container can resolve
# platform-specific optionalDependencies (esbuild has separate variants per
# CPU/OS — strict ci breaks when the host that generated the lockfile is on
# a different architecture from the container).
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund

# App source (overridden by bind mount in dev)
COPY . .

EXPOSE 3000

# Bind to 0.0.0.0 so the host can reach Nuxt across the bridge network
CMD ["npm", "run", "dev"]
