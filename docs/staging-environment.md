# Staging Environment (Placeholder)

Status: **not provisioned**. No hosting provider has been chosen yet. This document
tracks what the `staging` environment needs to satisfy once one is picked
(Technical_Architecture.md §17 — CI/CD & Environments), so setup can proceed without
re-deriving the requirements. Nothing here should be provisioned until a provider is
selected as a separate task.

## Requirements checklist

- [ ] **Hosting provider selected** — blocks everything below.
- [ ] **Separate `.env.staging`** with its own `APP_KEY`, distinct from local and
      production.
- [ ] **Sandbox billing credentials** — Apple StoreKit and Google Play Billing
      configured in *sandbox/test* mode (not production credentials), so the
      entitlements pipeline (§10) can be exercised end-to-end without real charges,
      per §17's requirement that staging use a sandboxed App Store/Play Billing
      configuration.
- [ ] **Separate database from production** — its own Postgres instance/database
      (pgvector-enabled, matching local Sail and production), not a shared schema or
      read replica of the production database.
- [ ] **Separate Redis instance** — not shared with production, matching local/CI
      setup.
- [ ] **Separate object storage bucket/container** — isolated from the production
      bucket, for uploaded documents and OCR artifacts.
- [ ] **Automatic pre-deploy migrations** — schema changes run automatically before
      each staging deploy, in additive-only order (per §17: additive changes only for
      zero-downtime; destructive changes follow the two-step deprecate-then-remove
      pattern). This should mirror the production deploy process so staging is a
      faithful rehearsal of it.
- [ ] **Blue/green or rolling deploy** — same deploy strategy as production (§17), so
      staging validates the actual deploy mechanism, not just the code.
- [ ] **Separate APNs/FCM credentials** — sandbox/test push credentials, distinct from
      production, for notification testing.
- [ ] **Access restricted** — staging should not be publicly discoverable/indexed and
      should require authentication separate from production credentials.
- [ ] **Observability wired up** — logging/monitoring (§15) pointed at a
      staging-specific destination so staging noise doesn't pollute production
      dashboards/alerts.

## Out of scope for now

Actual provisioning (choosing a host, creating the staging database/Redis/storage,
registering sandbox billing credentials, wiring the deploy pipeline) is deferred to a
follow-up task once a hosting provider is chosen. This document only defines the
requirements so that task can move quickly.
