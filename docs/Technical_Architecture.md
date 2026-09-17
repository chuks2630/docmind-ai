# DocMind AI — Technical Architecture Document
### Companion to the PRD (v1.0) and UX/UI Specification (v1.0)

**Platform:** Flutter (iOS + Android) · Laravel backend
**Document owner:** Engineering
**Status:** Draft v1.0

---

## 1. Purpose & Architectural Goals

This document translates the PRD's product requirements and the UX/UI Specification's screen/interaction contracts into a concrete system architecture. It is written for engineers building and operating the system, not for product stakeholders.

**Architectural goals, in priority order:**
1. **Fast local-first UX** — every screen in the UX spec (Home, Document Detail, Chat) must render from local cache before any network round trip, per the "offline-first thinking" UX principle.
2. **Correct, cited AI answers** — the citation-chip UI element is a hard technical requirement, not a nice-to-have: every AI response must carry machine-readable source offsets, not just prose.
3. **Plan-aware everywhere** — every feature that the Premium Model (PRD §9) gates (AI questions/day, OCR/day, storage, page limits) must be enforceable server-side, never trusted to the client.
4. **Buildable by a small team in the MVP window** — architecture favors managed services and boring, well-understood technology over novel infrastructure, per the PRD's 8–10 week single-developer MVP constraint.
5. **Swappable AI provider** — the model behind AI Chat should be replaceable without client changes, since model quality/pricing will shift over the product's life.

---

## 2. System Overview

```
┌─────────────────────────┐        ┌──────────────────────────────────┐
│   Flutter Mobile App     │◄──────►│         API Gateway / LB          │
│  (iOS / Android)          │  HTTPS │        (Laravel entry point)      │
│  - Local cache (SQLite)   │        └──────────────┬────────────────────┘
│  - Sync queue             │                       │
│  - On-device OCR (fast)   │        ┌──────────────▼────────────────────┐
└─────────────────────────┘        │        Laravel Application         │
                                     │  Auth · Documents · Folders ·      │
                                     │  Search · Notifications ·          │
                                     │  Entitlements/Billing              │
                                     └───┬─────────────┬─────────────┬────┘
                                         │             │             │
                              ┌──────────▼───┐  ┌───────▼──────┐ ┌────▼─────────┐
                              │ Queue Workers │  │ AI Service    │ │ Search Index │
                              │ (OCR, sync,   │  │ Layer         │ │ (content +   │
                              │ notifications)│  │ (chunk/embed/ │ │ metadata)    │
                              └───────┬───────┘  │ retrieve/cite)│ └──────────────┘
                                      │           └──────┬────────┘
                          ┌───────────▼─────┐    ┌────────▼─────────┐
                          │ Object Storage    │    │ LLM Provider(s)  │
                          │ (originals, pages)│    │ via abstraction  │
                          └───────────────────┘    └──────────────────┘
                                      │
                          ┌───────────▼─────┐
                          │ Primary Database  │
                          │ (Postgres/MySQL)  │
                          └────────────────────┘

           ┌────────────────────────────┐   ┌───────────────────────────┐
           │ App Store / Play Billing    │   │ Push Notification Service │
           │ (webhooks → Entitlements)   │   │ (APNs / FCM)               │
           └────────────────────────────┘   └───────────────────────────┘
```

**Guiding pattern:** the Laravel app is a thin, synchronous orchestration layer over three asynchronous subsystems — OCR, AI, and Search — all fronted by a queue, so that no user-facing request blocks on slow work. The mobile app never talks to the AI provider or OCR engine directly; everything is proxied and metered through the backend so plan limits and citation-mapping are always enforced server-side.

---

## 3. Technology Stack

| Layer | Choice | Rationale |
|---|---|---|
| Mobile framework | Flutter 3.x | Single codebase for iOS/Android per PRD constraint |
| Mobile state management | Riverpod | Testable, avoids widget-tree coupling, good fit for the offline-first sync layer needing app-wide reactive state |
| Local mobile storage | SQLite (via `drift`) + local file storage for cached images | Structured querying for documents/folders/chat locally, required for full offline reading |
| Backend framework | Laravel 11 (PHP 8.3) | Rapid CRUD/auth/billing development matching the MVP timeline; mature ecosystem for queues, jobs, and Sanctum-based mobile auth |
| Primary database | PostgreSQL | Strong JSONB support (useful for citation offsets, embeddings metadata) and full-text search primitives as a fallback/complement to a dedicated search index |
| Queue / background jobs | Laravel Queues on Redis, with Horizon for visibility | OCR, AI processing, sync reconciliation, and notification dispatch are all async by design (Section 2) |
| Object storage | S3-compatible (e.g., AWS S3) | Originals, page images, thumbnails; supports signed URLs for secure, time-limited client access |
| Search index | Postgres full-text + `pgvector` for embeddings (MVP); dedicated vector DB (e.g., a managed vector store) evaluated at Version 2 scale | Keeps MVP infra minimal (PRD §14); revisit once cross-document search (Roadmap V2) is built |
| On-device OCR | Platform-native OCR (Apple Vision framework on iOS, ML Kit Text Recognition on Android) | Immediate low-latency feedback per UX spec's Processing screen; no network dependency for the first pass |
| Server-side OCR | Managed OCR service (e.g., Google Cloud Vision or AWS Textract) | Higher-accuracy canonical text used for AI/search once the client uploads |
| AI provider | Anthropic Claude API, behind an internal abstraction | Swappable per architectural goal #5; supports streaming and large context for document chunking |
| Push notifications | APNs (iOS) / FCM (Android) via a unified Laravel notification channel | Matches PRD §11 notification strategy |
| Billing | Apple StoreKit + Google Play Billing, reconciled via App Store Server Notifications / RTDN webhooks | Required by App Store/Play policy for digital subscriptions (PRD §15 risk) |
| CI/CD | GitHub Actions → Fastlane (mobile) / standard deploy pipeline (backend) | Automates build, test, store submission |
| Monitoring | Sentry (crash/error), Laravel Telescope (dev), a metrics/APM tool (e.g., Datadog) in production | Section 15 |

---

## 4. Mobile App Architecture (Flutter)

### 4.1 Layered structure
- **Presentation layer** — widgets matching the UX spec's screens 1:1; no business logic in widgets.
- **State/controller layer (Riverpod providers)** — one provider family per domain: `documentsProvider`, `folderProvider`, `chatThreadProvider(documentId)`, `entitlementsProvider`, `syncQueueProvider`.
- **Repository layer** — abstracts "where data comes from," always reading local SQLite first and reconciling with the API in the background (offline-first, per UX §"Loading" state guidance: skeletons only show when there is genuinely no cached data).
- **Local data layer** — `drift`-backed SQLite tables mirroring backend entities (documents, folders, chat_messages, citations) plus a `sync_queue` table for pending local mutations.
- **Platform layer** — thin native bridges for camera capture, on-device OCR, and biometric app-lock (future).

### 4.2 Offline-first data flow
1. User action (e.g., rename a document) writes immediately to local SQLite and updates the UI optimistically.
2. The same action is appended to `sync_queue` with a monotonic local timestamp and a client-generated idempotency key.
3. A background sync worker drains the queue against the API whenever connectivity is available, retrying with exponential backoff.
4. Server responses reconcile back into local SQLite; conflicts use last-write-wins by server timestamp, with the rare true-conflict case (edit vs. delete) surfaced as an in-app merge prompt per the PRD's cloud-sync journey.

### 4.3 Chat streaming on-device
The chat screen opens a chunked HTTP stream (Server-Sent Events style over HTTPS) to the backend's `/documents/{id}/chat` endpoint; tokens are appended to the AI bubble as they arrive, matching the UX spec's streaming bubble behavior. If the stream drops, the client shows the standard inline chat error state ("Couldn't get a response — retry") and can resume by re-issuing the same question with the partial answer discarded (no partial-answer persistence, to avoid confusing citation mapping).

---

## 5. Backend Architecture (Laravel)

### 5.1 Module boundaries
Structured as bounded modules within a single Laravel app for the MVP (a modular monolith, not microservices — appropriate for the team size and timeline):

- **Auth & Identity** — registration/login (Apple/Google/email via Socialite + Sanctum tokens), guest-to-account upgrade.
- **Documents** — CRUD for documents/pages, upload orchestration, triggers OCR jobs.
- **Folders** — folder CRUD, document-folder association, 2-level nesting constraint enforced server-side.
- **AI Orchestration** — the AI Service Layer's Laravel-facing interface (Section 7): receives chat requests, enforces plan limits, delegates to chunking/retrieval, proxies streaming responses.
- **Search** — indexing pipeline (triggered post-OCR) and query endpoint serving the Search screen's two result groups (filename vs. content matches).
- **Entitlements & Billing** — subscription state, webhook ingestion from App Store/Play, usage-limit counters (AI questions/day, OCR/day).
- **Notifications** — scheduling and dispatch of the PRD §11 notification set.

### 5.2 Why a modular monolith, not microservices
The MVP's 8–10 week, single-developer constraint (PRD §13) makes network-boundary overhead (service discovery, distributed transactions) a net negative. Module boundaries are enforced at the code level (separate namespaces/service classes, no cross-module direct model access) so that specific modules — most likely AI Orchestration and Search — can be extracted into standalone services later (Version 2+) without a full rewrite.

---

## 6. Data Model

Core tables (fields abbreviated to the architecturally significant ones):

**users** — id, auth_provider, auth_provider_id, email (nullable for guest), display_name, created_at.

**subscriptions** — id, user_id, plan (`free`/`premium`), store (`apple`/`google`), original_transaction_id, status (`active`/`trial`/`cancelled`/`grace_period`), renews_at, source_of_truth_synced_at.

**usage_counters** — id, user_id, counter_type (`ai_question`/`ocr_scan`), count, window_start (resets daily; enforced against plan limits from PRD §9 at request time, not just displayed).

**folders** — id, user_id, parent_folder_id (nullable, enforces max depth 2 in application logic), name, color, icon, created_at.

**documents** — id, user_id, folder_id (nullable), title, source_type (`scan`/`pdf_import`/`image_import`), page_count, ocr_status (`pending`/`processing`/`complete`/`failed`), storage_key_original, is_favorite, deleted_at (soft delete → Recently Deleted).

**document_pages** — id, document_id, page_number, storage_key_image, extracted_text (client-fast OCR pass), canonical_text (server high-accuracy OCR pass), text_offsets (JSONB — maps text to on-page coordinates for citation highlighting).

**document_chunks** — id, document_id, chunk_index, text, embedding (`pgvector`), start_page, end_page, start_offset, end_offset. *(Populated post-OCR; this table is what the AI retrieval step queries — see Section 7.)*

**chat_threads** — id, document_id (one thread per document, per PRD §8 "conversation memory"), created_at.

**chat_messages** — id, thread_id, role (`user`/`assistant`), content, created_at, citations (JSONB array of `{chunk_id, page, start_offset, end_offset}` — this is the field that powers the UX spec's citation chip).

**notifications_log** — id, user_id, type, payload, sent_at, read_at.

**sync_conflicts** (server-side mirror of rare true conflicts, for support/debugging) — id, user_id, entity_type, entity_id, client_version, server_version, resolved_at.

---

## 7. AI Service Layer

This is the architectural component that makes the PRD's AI Features (§8) real. It sits behind the AI Orchestration module and is deliberately provider-agnostic.

### 7.1 Responsibilities
1. **Chunking** — on OCR completion, `canonical_text` per page is split into overlapping chunks (target ~500 tokens, 15% overlap) and embedded, populating `document_chunks`. This runs as a queued job, not inline with OCR, so a large document doesn't block the "document ready" state the UX spec's Processing screen represents — text is viewable immediately; AI chat becomes available a few seconds later, with the input bar showing a brief "Preparing AI…" disabled state if a question arrives before chunking finishes.
2. **Retrieval** — on each chat message, the query is embedded and the top-k (~6–10) most relevant chunks for that specific document are retrieved via vector similarity, then assembled into the model's context alongside recent conversation history from `chat_messages`. This directly implements the PRD's "Handling Large Documents" requirement without needing the full document in-context every turn.
3. **Prompt assembly** — a system prompt fixes the assistant's scope to the retrieved chunks of the *current* document only (enforcing "Context Awareness," PRD §8) and instructs the model to output citation markers referencing chunk IDs inline.
4. **Citation mapping** — the raw model output's citation markers are resolved against `document_chunks` → `document_pages.text_offsets` to produce the structured `citations` JSON stored on the `chat_messages` row and returned to the client, which is what renders as tappable citation chips per the UX spec.
5. **Streaming proxy** — the Laravel endpoint opens a streaming connection to the LLM provider and re-streams tokens to the mobile client as they arrive, buffering only long enough to detect and structure citation markers without perceptibly delaying visible text.
6. **Plan enforcement** — before any call to the LLM provider, the request checks `usage_counters` against the caller's plan (PRD §9); over-limit requests are rejected with a specific error code the client maps to the paywall screen, not a generic error.

### 7.2 Provider abstraction
All provider calls go through an internal `AiProviderInterface` (methods: `embed()`, `complete()`, `stream()`). This is the seam that lets the team swap or add model providers without touching chunking, retrieval, or citation-mapping logic — satisfying architectural goal #5.

### 7.3 Cross-document search (forward-looking note)
The PRD's Version 2 roadmap item ("ask across all your documents") is intentionally *not* built into the MVP's retrieval scope — the system prompt and retrieval query are both hard-scoped to a single `document_id` in v1. The `document_chunks` schema is designed to support cross-document retrieval later by simply widening the retrieval query's filter, without a schema migration.

---

## 8. OCR Pipeline

1. **Capture (client):** on-device OCR runs immediately after crop/confirm (UX §3.6 Processing screen), giving the user fast, offline-capable text for immediate viewing and search-while-offline.
2. **Upload:** the original page image uploads to object storage in the background (queued if offline, per the sync architecture in Section 4.2); `document_pages.extracted_text` is populated from the on-device pass right away.
3. **Server-side high-accuracy pass:** once uploaded, a queued job runs the managed OCR service against the image, populating `canonical_text` and `text_offsets`.
4. **Reconciliation:** if the canonical text differs meaningfully from the on-device text, the client silently updates its cached copy on next sync — the user never sees a jarring "text changed" moment, per the UX principle of quiet, non-alarming background behavior.
5. **Failure handling:** if both OCR passes fail (e.g., truly illegible image), `documents.ocr_status = failed` triggers the UX spec's error state ("Couldn't read this photo — try retaking it").

---

## 9. Authentication & Authorization

- **Mobile auth:** Sign in with Apple / Google via OAuth, or email/password, all issuing a Laravel Sanctum personal access token stored securely in the device keychain/keystore.
- **Guest mode:** a locally-generated UUID identifies an unauthenticated device; guest documents are stored fully locally. On sign-up, the client replays its local `sync_queue` against the newly authenticated account, migrating guest data server-side with zero loss — directly implementing the PRD's "Registration" user journey.
- **Authorization:** every API resource (documents, folders, chat threads) is scoped to `user_id` at the query level via a global scope, not per-controller checks, to prevent accidental cross-user data leaks as the codebase grows.
- **Token lifecycle:** short-lived access tokens (1 hour) with silent refresh; refresh failures force re-authentication rather than silently degrading to read-only, to avoid confusing partial-functionality states.

---

## 10. Entitlements & Billing Architecture

- **Source of truth:** Apple/Google's billing systems are authoritative; the `subscriptions` table is a synchronized cache, updated via:
  - **Client-side receipt validation** at time of purchase (fast path — unlocks the app immediately).
  - **Server-side webhook ingestion** (App Store Server Notifications v2 / Google Play RTDN) for renewals, cancellations, refunds, and grace periods (authoritative path — corrects the cache if the client-side event is missed, e.g., app was closed).
- **Usage limits:** `usage_counters` rows reset on a rolling daily window per user timezone; every AI-question and OCR-scan request increments the relevant counter atomically before proceeding, preventing race-condition over-usage.
- **Client sync of entitlement state:** the `entitlementsProvider` (Section 4.1) polls a lightweight `/me/entitlements` endpoint on app foreground and after any purchase flow, so plan-gated UI (paywall triggers, limit banners) reflects server truth within seconds, not just optimistic client state.

---

## 11. Storage Architecture

- **Object storage layout:** `documents/{user_id}/{document_id}/original.*`, `.../pages/{n}.jpg`, `.../thumbnails/{n}.jpg` — partitioned by user for straightforward access-control and eventual per-user data export/deletion.
- **Signed URLs:** the mobile client never receives permanent storage URLs; the API issues short-lived signed URLs (5–10 min TTL) for both upload and download, keeping the bucket itself fully private.
- **Encryption:** server-side encryption at rest (SSE-S3 or equivalent) plus TLS in transit everywhere; this satisfies the PRD's privacy commitments for sensitive document types (legal, medical, financial) called out in §15 risks.
- **Storage quota enforcement:** a per-user aggregate byte count (denormalized on the `users` row, recalculated via a nightly reconciliation job) is checked before accepting new uploads, driving the UX spec's Settings storage bar and the PRD's storage-limit upgrade prompts.

---

## 12. Search & Indexing

- **Filename search:** standard Postgres `ILIKE`/trigram index on `documents.title` — fast, simple, sufficient for the MVP's scale.
- **Content search:** Postgres full-text search (`tsvector`) over `document_pages.canonical_text` for keyword matching, combined with the `pgvector` embeddings in `document_chunks` for semantic relevance ranking — giving the Search screen's "Inside your documents" group both exact-term and meaning-based matches.
- **Index population timing:** triggered as the final step of the OCR pipeline (Section 8), so a document becomes searchable within seconds of processing completing, not on a batch delay.
- **Re-indexing:** any edit to a document's canonical text (rare — e.g., a manual correction feature, not in MVP) would re-trigger indexing for just the affected pages, not the whole document.

---

## 13. Caching Strategy

| Cache | Layer | Contents | Invalidation |
|---|---|---|---|
| Local SQLite | Client | Full document metadata, chat history, folder structure | Reconciled on every successful sync |
| Local file cache | Client | Recently viewed page images and thumbnails | LRU eviction against a configurable storage cap |
| Redis | Server | Entitlement checks, rendered chunk-retrieval results for repeated identical questions, rate-limit counters | Short TTL (minutes) for entitlements; usage counters are the authoritative store, not just cache |
| CDN | Edge | Thumbnails and page images served via signed URLs | Standard TTL with cache-busting on document edit/delete |

---

## 14. Security & Privacy

- **Least-privilege data access:** all queries scoped by `user_id` (Section 9); no endpoint returns cross-user data even in aggregate/analytics paths without explicit anonymization.
- **No training on user content:** document content and chat transcripts are not used to train or fine-tune any model, and this is stated plainly in the app's privacy copy (per UX §9 content guidelines) rather than buried in a long ToS.
- **Sensitive-category handling:** documents are not automatically classified by sensitivity in the MVP, but the storage/encryption posture (Section 11) is set to the standard appropriate for legal/financial/medical content from day one, rather than retrofitted later.
- **Account deletion:** deleting an account cascades a scheduled hard-delete of all associated documents, embeddings, and chat history within a bounded window (e.g., 30 days, matching the Recently Deleted retention pattern), with an immediate access-revocation on request even if the physical delete job runs asynchronously.
- **Abuse prevention:** rate limiting at the API gateway layer (separate from plan-based usage counters) to prevent scripted abuse of OCR/AI endpoints regardless of plan.

---

## 15. Observability & Monitoring

- **Error tracking:** Sentry integrated in both the Flutter client and Laravel backend, tagged by module (Auth, Documents, AI Orchestration, etc.) to align with the architectural boundaries in Section 5.
- **Key health metrics (feeding PRD §12 analytics, from an engineering-health angle):** OCR job failure rate, AI response latency (p50/p95), streaming-connection drop rate, sync-queue drain latency, webhook-processing lag (billing correctness depends on this staying low).
- **Alerting thresholds:** OCR failure rate > 5% over 15 minutes, AI p95 latency > 8s, billing webhook processing lag > 10 minutes — each routed to on-call, since each maps directly to a broken user-facing promise in the PRD (fast processing, reliable citations, correct entitlements).
- **Structured logging:** every AI request/response pair logs token counts and retrieved chunk IDs (not full user content by default) to support debugging citation-accuracy issues without over-retaining sensitive text in log storage.

---

## 16. Scalability & Infrastructure

- **Stateless API tier:** Laravel app servers hold no session state (Sanctum tokens are stateless-verifiable), so horizontal scaling behind a load balancer is a simple replica-count change.
- **Queue-based load smoothing:** OCR and AI chunking jobs are queued (Section 3, Section 7) so traffic spikes (e.g., many users scanning at once) degrade to slightly longer processing times rather than request failures or AI-request timeouts.
- **Database scaling path:** MVP runs a single Postgres primary with read replicas introduced only when read load (search, document listing) measurably contends with write load — deferred until real usage data justifies it, per the "boring technology" architectural goal.
- **AI provider rate limits:** the AI Service Layer's provider abstraction (Section 7.2) includes built-in backoff/queueing against provider-side rate limits, so a provider-side throttle degrades to slower streaming starts rather than visible failures.

---

## 17. CI/CD & Environments

- **Environments:** `local` → `staging` → `production`, with staging using a sandboxed App Store/Play Billing configuration to safely test the entitlements pipeline (Section 10) end-to-end before release.
- **Mobile pipeline:** GitHub Actions builds on every PR (lint, unit/widget tests), Fastlane handles signing and store submission on tagged releases.
- **Backend pipeline:** GitHub Actions runs the Laravel test suite (feature tests per module boundary) and static analysis on every PR; deploys via a standard blue/green or rolling deploy to avoid downtime during OCR/AI job processing.
- **Migrations:** all schema changes ship as reversible Laravel migrations, run automatically pre-deploy in a maintenance-safe order (additive changes only for zero-downtime; destructive changes follow a two-step deprecate-then-remove pattern).

---

## 18. Third-Party Integrations

| Integration | Purpose | Notes |
|---|---|---|
| Apple Sign In / Google Sign In | Auth | Required by App Store guidelines when other third-party logins are offered |
| Apple StoreKit / Google Play Billing | Subscriptions | Section 10 |
| Managed OCR service | Server-side canonical text extraction | Provider abstracted similarly to the AI layer, in case of future swap |
| Anthropic Claude API | AI chat, summarization, translation, rewrite | Behind the `AiProviderInterface` (Section 7.2) |
| APNs / FCM | Push notifications | Unified through Laravel's notification channels |
| Object storage provider | File storage | S3-compatible interface preferred for portability |

---

## 19. Non-Functional Requirements Summary

| Requirement | Target | Source |
|---|---|---|
| Cold start time | < 2s | UX §Performance principle |
| OCR first-pass feedback | < 3s on mid-tier device | UX §Performance principle |
| AI response start (first streamed token) | < 2s p50, < 5s p95 | PRD §1.5 success metrics, adapted |
| Offline read availability | 100% of previously opened documents/chats | PRD §4.12, UX §Offline-first principle |
| Uptime (API) | 99.9% | Standard consumer SaaS target |
| Data encryption | At rest and in transit, no exceptions | PRD §15 privacy risk |
| Entitlement sync latency after purchase | < 5s | Section 10 |

---

## 20. Key Data Flows (Sequence Summaries)

**A. Scan → Searchable & Chat-Ready**
Client captures pages → on-device OCR (instant local text) → background upload → server OCR job → `canonical_text` + `text_offsets` written → chunking/embedding job → `document_chunks` populated → search index updated → AI chat unlocked. Each arrow after "background upload" is an async queue step; the user can read and even ask basic questions against the on-device text before the canonical pass completes, with citations "upgrading" silently to the more accurate offsets once ready.

**B. Ask a Question → Cited Answer**
Client sends message → Laravel checks `usage_counters` against plan → query embedded → top-k chunks retrieved from `document_chunks` (scoped to `document_id`) → prompt assembled with chunks + recent thread history → streamed to LLM provider → tokens re-streamed to client → citation markers resolved against chunk/page offsets → final `chat_messages` row persisted with structured `citations` → client renders tappable chips.

**C. Purchase → Entitlement Unlock**
Client initiates StoreKit/Play Billing purchase → client-side receipt sent to `/me/entitlements/validate` for immediate optimistic unlock → asynchronously, the store's webhook arrives at `/webhooks/{apple|google}` → `subscriptions` row reconciled as authoritative → any discrepancy (e.g., client claimed success but webhook shows a failed charge) corrects the cached entitlement and surfaces the appropriate UX (limit banners reappear) within the next `/me/entitlements` poll.

---

*End of document.*
