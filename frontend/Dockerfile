# Hauz frontend — Nuxt 3 dev container (Node 22-alpine).
# Production build will get a separate multi-stage Dockerfile in Phase 6.

FROM node:22-alpine

WORKDIR /app

# Compatibility for some native modules on alpine
RUN apk add --no-cache libc6-compat

# Install deps first (cached layer)
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

# App source (overridden by bind mount in dev)
COPY . .

EXPOSE 3000

# Bind to 0.0.0.0 so the host can reach Nuxt across the bridge network
CMD ["npm", "run", "dev"]
