# DocMind AI — Development Roadmap
### Companion to the PRD (v1.0), UX/UI Specification (v1.0), and Technical Architecture Document (v1.0)

**Document owner:** Engineering / Product
**Status:** Draft v1.0

---

## 1. Purpose & Planning Assumptions

This roadmap sequences the Technical Architecture's components and the UX/UI Specification's screens into a buildable schedule that delivers the PRD's MVP (§13) inside its 8–10 week target, followed by the Version 1.5–4 phases.

**Assumptions:**
- **Team:** the PRD frames the MVP as buildable by "one experienced developer" in spirit — in practice this roadmap assumes a small core team of **2 engineers (1 backend-leaning full-stack, 1 Flutter-focused) + 1 designer at reduced/part-time capacity**, since a true solo build of both a Laravel backend and a Flutter app with AI/OCR pipelines in 8–10 weeks is only realistic if design and architecture (already produced in the prior three documents) are treated as done coming in — which they are.
- **Sprint cadence:** 2-week sprints, 5 sprints = 10 weeks for MVP.
- **Sequencing principle:** every sprint ends with something demoable end-to-end on-device, not just backend plumbing — this keeps UX assumptions honest early rather than discovering integration problems in week 9.
- **Definition of Done (applies every sprint):** feature matches its UX spec screen/state definitions (empty/loading/error/success), is covered by at least a smoke-level automated test, and is behind the correct entitlement/plan check if applicable.

---

## 2. MVP Timeline at a Glance

| Sprint | Weeks | Theme | Primary Architecture Components | Primary UX Screens |
|---|---|---|---|---|
| 0 | 1–2 | Foundation | Auth module, base schema, CI/CD, Flutter skeleton, design tokens | Splash, Onboarding, Sign Up/Login |
| 1 | 3–4 | Capture & OCR | Documents module, on-device OCR, upload pipeline, object storage | Camera/Scan, Scan Preview, Processing, Document Detail (viewer only) |
| 2 | 5–6 | AI Chat Core | AI Service Layer (chunking, retrieval, streaming, citations) | Document Detail (chat), Summary/Notes/Translation sheets |
| 3 | 7 | Organization & Search | Folders module, Search module | Folders, Folder Detail, Search, Favorites |
| 4 | 8 | Monetization & Sync Hardening | Entitlements & Billing module, sync queue reconciliation | Premium/Paywall, Settings/Subscription |
| 5 | 9–10 | Polish, QA, Launch | Notifications module, accessibility pass, observability wiring | All remaining states, dark mode across app, Help/Support |

---

## 3. Sprint-by-Sprint Detail

### Sprint 0 (Weeks 1–2) — Foundation
**Backend:**
- Laravel project scaffolding, module namespace structure per Technical Architecture §5.1.
- Core schema migrations: `users`, `subscriptions`, `usage_counters`, `folders`, `documents`, `document_pages` (Technical Architecture §6).
- Auth module: Apple/Google/email sign-in via Socialite + Sanctum, guest-mode UUID handling.
- CI/CD pipeline stood up (GitHub Actions, staging environment, sandbox billing config per Architecture §17).

**Mobile:**
- Flutter project scaffolding with Riverpod, `drift` local database matching backend schema shape.
- Design tokens implemented as a theme package (colors, type scale, spacing, radius from UX §1.1) so every subsequent screen pulls from one source.
- Splash, Onboarding carousel, Sign Up/Login screens built against the real Auth module (not mocked), including guest mode.

**Exit criteria:** a user can install the app, see onboarding, create an account or continue as guest, and land on an empty Home screen — full stack, no mocks.

---

### Sprint 1 (Weeks 3–4) — Capture & OCR
**Backend:**
- Documents module: upload endpoints issuing signed object-storage URLs (Architecture §11).
- Server-side OCR job wired to the managed OCR provider; `document_pages.canonical_text` / `text_offsets` populated (Architecture §8, steps 3–4).
- `usage_counters` increment logic for OCR scans (enforced even though billing isn't gating yet, so counting behavior is correct from day one).

**Mobile:**
- Camera/Scan screen with live edge detection and multi-page capture (UX §3.4).
- On-device OCR integration (Apple Vision / ML Kit) for instant local text (Architecture §8, step 1).
- Scan Preview/Crop and Processing screens, including the Signature scan-line animation (UX §1.1, §3.6).
- Document Detail screen's **viewer half only** (paged image display, pinch-zoom) — chat half stubbed as "AI is preparing…" until Sprint 2.
- Background upload + sync queue skeleton (Architecture §4.2) so captured documents survive app restarts and connectivity loss.

**Exit criteria:** a user can scan or import a document end-to-end, see it processed with the correct loading/error states, and view it in the document viewer, fully offline-capable for previously captured content.

---

### Sprint 2 (Weeks 5–6) — AI Chat Core
**Backend:**
- Chunking/embedding pipeline: `document_chunks` population job triggered post-OCR (Architecture §7.1, step 1).
- Retrieval logic (top-k vector similarity scoped to `document_id`) and prompt assembly (Architecture §7.1, steps 2–3).
- `AiProviderInterface` implemented against the Anthropic Claude API (Architecture §7.2), with streaming proxy endpoint.
- Citation-mapping: resolving model output markers back to `document_pages.text_offsets`, persisted on `chat_messages.citations`.
- `usage_counters` enforcement for AI questions (soft-limited in this sprint — hard paywall gating lands in Sprint 4).

**Mobile:**
- Document Detail's chat half: suggested-prompt chips, chat bubbles, streaming token rendering, citation chip UI (UX §2.4).
- Tapping a citation chip triggers the Signature scan-line jump to the cited passage in the viewer (UX §1.1, §3.7).
- Summarize / Explain / Rewrite / Translate / Notes actions wired end-to-end as sheets over the viewer (UX §3.8).

**Exit criteria:** a user can ask a question about a real scanned or imported document and get a streamed, cited answer; summarize/explain/notes actions work end-to-end. This is the sprint that proves the product's core promise ("scan it, ask it, done") for the first time.

---

### Sprint 3 (Week 7) — Organization & Search
**Backend:**
- Folders module: CRUD, 2-level nesting constraint (Technical Architecture §5.1, §6).
- Search module: filename trigram search + content full-text/semantic search over `document_pages` and `document_chunks` (Architecture §12).

**Mobile:**
- Folders and Folder Detail screens (UX §3.9), move-to-folder actions from the document context menu.
- Search screen with the two result groups and jump-to-passage behavior (UX §3.10).
- Favorites toggle and filtered view.

**Exit criteria:** a user can organize documents into folders and find both a document by name and a specific passage inside a document via content search.

---

### Sprint 4 (Week 8) — Monetization & Sync Hardening
**Backend:**
- Entitlements & Billing module: StoreKit/Play Billing receipt validation endpoint, webhook ingestion for App Store Server Notifications / Play RTDN (Architecture §10).
- Hard enforcement of plan limits (AI questions/day, OCR/day, storage, page limits per PRD §9) at the request layer — previously soft-tracked, now actually blocking with the correct error codes the client maps to the paywall.
- Storage quota calculation job (Architecture §11).
- Sync queue conflict-resolution path (last-write-wins + rare manual-merge case) hardened and tested against real multi-device scenarios (Architecture §4.2).

**Mobile:**
- Premium/Paywall screen fully wired to real purchase flows (UX §3.11), including trial messaging and restore-purchase.
- Settings/Subscription management screen (UX §3.12) with real plan state.
- Limit-reached banners/toasts triggered by the newly-enforced backend limits.

**Exit criteria:** the monetization loop is real end-to-end — a free user hits a real limit, sees a real paywall, can subscribe via the actual store, and the unlock is reflected within seconds per the Architecture's entitlement-sync target (§19).

---

### Sprint 5 (Weeks 9–10) — Polish, QA, and Launch Readiness
**Backend:**
- Notifications module: processing-complete, storage-warning, weekly-summary, and subscription-reminder notifications (PRD §11), scheduled jobs and push delivery via APNs/FCM.
- Observability wiring: Sentry tagging by module, key health metrics and alert thresholds live (Architecture §15).
- Load/soak testing of the queue-based OCR/AI pipeline under simulated concurrent scan load.

**Mobile:**
- Dark mode pass across every screen (UX §6), verifying the "documents render on a light mat even in dark mode" rule.
- Full accessibility pass: VoiceOver/TalkBack labels, dynamic type at 130%, tap target audit, reduced-motion behavior for the Signature scan-line (UX §7).
- Every remaining empty/loading/error/success state implemented per the UX spec's state table (§5) — this sprint is where "the last 20%" of state coverage that's easy to skip gets closed out.
- Recently Deleted / 30-day recovery flow.
- Help & Support screen (FAQ, contact) and Notification history screen.

**Store submission:**
- Fastlane-driven build and submission to TestFlight / Play internal testing (Architecture §17) mid-sprint, leaving time for at least one review-feedback cycle before public launch.
- Final privacy-copy review (App Store privacy nutrition label / Play Data Safety form) matching the actual data practices in Technical Architecture §14.

**Exit criteria:** the app is feature-complete against the MVP scope in PRD §13, passes an internal QA pass against every UX-spec state, and is submitted to both stores.

---

## 4. Cross-Cutting Workstreams (run throughout, not sprint-boxed)

| Workstream | Owner | Notes |
|---|---|---|
| Design QA | Designer | Reviews each sprint's built screens against the UX/UI Spec at sprint end, not just at final polish — catches drift early |
| Automated testing | Both engineers | Backend feature tests per module (Architecture §17); Flutter widget/unit tests for state-management logic, especially the sync queue |
| Analytics instrumentation | Backend engineer | Events from PRD §12 added incrementally as each feature ships, not bolted on at the end — e.g., `first_scan_completed` instrumented in Sprint 1, `paywall_viewed` in Sprint 4 |
| Content/copy | Designer + Product | UI copy reviewed against UX §9 voice guidelines each sprint, since copy tends to drift from "plain, active voice" under deadline pressure |
| Security review | Backend engineer | Storage encryption, signed-URL TTLs, and per-user query scoping (Architecture §14) verified before Sprint 4's billing work goes live, since billing correctness depends on account/data integrity being solid first |

---

## 5. Milestone Gates

| Gate | Timing | Pass criteria |
|---|---|---|
| **G1 — Core Loop Proven** | End of Sprint 2 | A real user can scan a document and get a cited AI answer, fully end-to-end, no mocks |
| **G2 — Organized & Findable** | End of Sprint 3 | Folders and search work against real multi-document libraries |
| **G3 — Monetization Live** | End of Sprint 4 | A test purchase completes through the real App Store/Play sandbox and unlocks Premium correctly |
| **G4 — Launch Ready** | End of Sprint 5 | Full UX-state coverage, accessibility pass complete, store submission accepted for review |

Each gate is a hard go/no-go — if a gate isn't met, the next sprint's scope is cut (not extended), preserving the 10-week window per PRD's MVP constraint. The most likely cut candidates, in order, are: Translation (defer to V1.5, it's already scoped there), Recently Deleted (ship as immediate hard-delete for launch, add recovery in V1.5), weekly-summary notifications (defer, since they depend on a full week of usage data anyway).

---

## 6. Post-MVP Roadmap (mapped to PRD §13 phases)

### Version 1.5 — Target: 4–6 weeks post-launch
| Feature | Primary architecture dependency | Notes |
|---|---|---|
| Translation | Extends existing AI Service Layer prompt assembly (Architecture §7) | Low new-infra cost — same chunking/retrieval, different output instruction |
| Rewrite tone options | Same as above | UI already scoped in MVP's action sheet (UX §3.7); only the prompt variants are new |
| Generate Notes | Same as above | |
| Shareable read-only links | New: a `document_shares` table + public, rate-limited read-only endpoint | First feature requiring unauthenticated public access — needs its own security review |
| Export to PDF/Word | New: a rendering job (queued, like OCR) | |
| Highlight-to-ask | Client-side selection UI + minor API addition to scope a chat question to a text range | |
| Weekly summary notifications | Notifications module extension, needs a full week of usage_counters history to be meaningful | Deferred from MVP per Section 5's cut list |
| Recently Deleted recovery | Un-cut from MVP if deferred there | |

### Version 2 — Target: Q2 post-launch
| Feature | Primary architecture dependency | Notes |
|---|---|---|
| Cross-document search & Q&A | Widening the AI Service Layer's retrieval filter beyond a single `document_id` (Architecture §7.3 — explicitly designed for this) | Likely the point at which `pgvector` is reassessed against a dedicated vector DB for cross-user-library scale (Architecture §3 tech stack note) |
| Handwriting-optimized OCR mode | Swap/extend the managed OCR provider integration | |
| In-app annotation | New client-side drawing layer over the document viewer + a new `annotations` table | |
| Family plan | Entitlements module extension: shared subscription across linked accounts | |
| Student discount verification | Reuses the app's own OCR pipeline against a student ID — a nice internal-dogfooding proof point | |

### Version 3 — Target: Q3–Q4 post-launch
Collaboration (shared folders, comments), Gmail/Outlook/Drive/Dropbox integrations, voice input for chat. Each of these is additive to existing modules (Folders gains sharing; a new Integrations module wraps external OAuth connections) rather than requiring architectural rework — confirming the modular-monolith boundaries chosen in the Technical Architecture (§5.2) were drawn correctly.

### Version 4 — Target: Year 2
Desktop/web companion, API/plugin ecosystem, professional analytics dashboards, enterprise-lite team plan. This phase is the first to plausibly justify extracting the AI Orchestration and Search modules into standalone services (Architecture §5.2's stated extraction path), once multi-surface (web + mobile) traffic patterns are known.

---

## 7. Risk-to-Timeline Mapping

Directly extending the PRD's Risks (§15) and Technical Architecture's non-functional targets (§19) into schedule impact:

| Risk | Sprint most exposed | Mitigation already built into this roadmap |
|---|---|---|
| OCR accuracy frustrates early users | Sprint 1 | On-device instant feedback + server canonical pass are both in scope from Sprint 1, not deferred |
| AI context/citation accuracy issues | Sprint 2 | Citation-mapping is a Sprint 2 exit criterion, not a later polish item — surfaced early enough to fix before it's load-bearing for other features |
| Billing/webhook correctness | Sprint 4 | Sandbox billing environment stood up in Sprint 0 specifically so Sprint 4 isn't the first time the team touches real store integration |
| App Store review rejection | Sprint 5 | Store submission happens with 1–2 weeks of buffer before intended public launch specifically to absorb a review-feedback cycle |
| Feature creep diluting MVP | All sprints | Milestone gates (Section 5) are hard cuts, not schedule extensions |

---

*End of document.*
