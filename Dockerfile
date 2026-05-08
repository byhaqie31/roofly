# Roofly demo — production Nuxt 3 image (frontend-only, Phase 1.1).
# Builds the Nuxt app from ./frontend and serves the SSR output on port 3000.

# Build stage
FROM node:22-alpine AS builder
WORKDIR /app

RUN apk add --no-cache libc6-compat

COPY frontend/package.json frontend/package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY frontend/ .
RUN npm run build

# Production stage
FROM node:22-alpine
WORKDIR /app

COPY --from=builder /app/.output ./.output

ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=3000

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
