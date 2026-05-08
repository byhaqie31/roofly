# Roofly frontend (Nuxt 4)

Owner + tenant UIs. Runs in a Docker container alongside the rest of the stack — see [docker-compose.yml](../docker-compose.yml) at the repo root.

## Quickstart

```bash
# from the repo root
docker compose up -d frontend
```

App at http://localhost:3000.

## Adding a dependency

**Important:** the container has an isolated `frontend_node_modules` volume (declared in [docker-compose.yml](../docker-compose.yml#L83)). Running `npm install <pkg>` on the host **does not** reach the container — Vite inside the container will still throw `Cannot find package '<pkg>'`.

**Default workflow — rebuild the image:**

```bash
docker compose build frontend
docker compose up -d frontend
```

The Dockerfile re-runs `npm ci` on every build, so the container's `node_modules` volume picks up the new package. This is the path to use before committing — it guarantees CI matches.

**Faster alternative while iterating:**

```bash
docker compose exec frontend npm install <pkg>
docker compose restart frontend   # so Vite clears its module cache
```

Same rule applies to removing or upgrading a dependency: change `package.json` on the host, then rebuild (or `exec npm install`) to sync the container.

## Common commands

```bash
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run build
docker compose logs -f frontend
```

## Docs

- [../docs/frontend/UI-STANDARDS.md](../docs/frontend/UI-STANDARDS.md) — visual + interaction language
- [../docs/frontend/MOCK-POC.md](../docs/frontend/MOCK-POC.md) — current frontend-first mock plan
- [Nuxt 4 docs](https://nuxt.com/docs)
