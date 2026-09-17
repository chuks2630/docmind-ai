# DocMind AI — API Specification
### Companion to the PRD (v1.0), UX/UI Specification (v1.0), Technical Architecture Document (v1.0), and Development Roadmap (v1.0)

**Document owner:** Engineering
**Status:** Draft v1.0
**Base URL:** `https://api.docmind.app/v1`

---

## 1. Conventions

- **Format:** JSON request/response bodies, `Content-Type: application/json` except file uploads (via signed URLs, Section 4.2) and the chat streaming endpoint (Section 6.1, `text/event-stream`).
- **Versioning:** URI-versioned (`/v1`). Breaking changes ship as `/v2`; additive changes (new optional fields) do not bump the version, per the Technical Architecture's zero-downtime migration approach (§17).
- **Authentication:** Bearer token (Laravel Sanctum personal access token) in the `Authorization: Bearer {token}` header on every endpoint except `/auth/*` and `/webhooks/*`.
- **Idempotency:** All mutating requests originating from the mobile sync queue (Technical Architecture §4.2) must include an `Idempotency-Key` header (client-generated UUID) so retried requests after a dropped connection don't double-apply.
- **Pagination:** cursor-based on all list endpoints — request `?cursor={cursor}&limit={n}` (default `limit=20`, max `100`), response includes `next_cursor` (null when no more results).
- **Timestamps:** ISO 8601 UTC (`2026-08-03T14:22:00Z`) throughout.
- **Plan gating:** endpoints subject to a Premium limit are marked **[Plan-Gated]**; see Section 9 for the shared error contract they return when a limit is hit.

---

## 2. Error Contract

All errors return a consistent envelope so the Flutter client can map any failure to the correct UX-spec error state (UX §5) without per-endpoint parsing logic:

```json
{
  "error": {
    "code": "ocr_failed",
    "message": "We couldn't read the text in this document.",
    "recoverable": true
  }
}
```

| HTTP status | Meaning | Typical `code` values |
|---|---|---|
| 400 | Malformed request | `invalid_request` |
| 401 | Missing/expired auth | `unauthenticated` |
| 403 | Authenticated but not permitted | `forbidden` |
| 404 | Resource not found or not owned by caller | `not_found` |
| 409 | Conflict (e.g., sync conflict, duplicate idempotency key with different body) | `conflict` |
| 422 | Validation error | `validation_failed` (includes a `fields` map) |
| 429 | Rate limit or **plan limit** reached | `plan_limit_reached`, `rate_limited` |
| 500 | Server error | `internal_error` |

`message` is always written in the plain, direct voice defined in UX §9 (fact + fix, no apology filler) since some of these strings surface directly in toasts.

**Plan-limit error (drives the paywall, per PRD §9 / UX §3.11):**
```json
{
  "error": {
    "code": "plan_limit_reached",
    "message": "You've used today's free AI questions.",
    "recoverable": true,
    "limit_type": "ai_question",
    "resets_at": "2026-08-04T00:00:00Z"
  }
}
```

---

## 3. Auth & Identity

### `POST /auth/register`
Create an account or upgrade a guest device to a full account (PRD §4.2 Registration journey).

**Request:**
```json
{
  "provider": "apple | google | email",
  "provider_token": "string (Apple/Google identity token; omitted for email)",
  "email": "string (required if provider=email)",
  "password": "string (required if provider=email)",
  "guest_device_id": "uuid (optional — triggers guest-data migration, Architecture §9)"
}
```
**Response `201`:**
```json
{
  "user": { "id": "uuid", "display_name": "string", "email": "string|null" },
  "access_token": "string",
  "refresh_token": "string",
  "expires_at": "iso8601"
}
```

### `POST /auth/login`
Same request/response shape as `register`, without the guest-migration field. Returns `401` (`code: invalid_credentials`) on failure.

### `POST /auth/refresh`
**Request:** `{ "refresh_token": "string" }` → **Response `200`:** new `access_token`/`expires_at` pair. `401` if the refresh token is expired or revoked — the client treats this as a forced re-authentication per Architecture §9 (no silent read-only degradation).

### `POST /auth/logout`
Revokes the current access token. **Response `204`.**

### `GET /me`
Returns the current user profile (Profile screen, UX §3.12).
```json
{ "id": "uuid", "display_name": "string", "email": "string|null", "created_at": "iso8601" }
```

### `DELETE /me`
Initiates account deletion (Technical Architecture §14). **Response `202`** — deletion is scheduled, not synchronous; access is revoked immediately regardless.

---

## 4. Documents

### `GET /documents`
List documents for Home/Folders (UX §3.3, §3.9). **[Plan-Gated: none — listing is always free]**

Query params: `folder_id` (optional filter), `favorite` (bool), `sort` (`recent`|`name`), `cursor`, `limit`.

**Response `200`:**
```json
{
  "data": [
    {
      "id": "uuid",
      "title": "string",
      "folder_id": "uuid|null",
      "source_type": "scan|pdf_import|image_import",
      "page_count": 12,
      "ocr_status": "pending|processing|complete|failed",
      "is_favorite": false,
      "thumbnail_url": "signed-url",
      "last_opened_at": "iso8601",
      "created_at": "iso8601"
    }
  ],
  "next_cursor": "string|null"
}
```

### `POST /documents`
Create a document record and receive signed upload URLs for its pages — the first call in the Scan/Import journeys (PRD §4.3, §4.4; Technical Architecture §11).

**Request:**
```json
{
  "title": "string",
  "source_type": "scan | pdf_import | image_import",
  "folder_id": "uuid|null",
  "page_count": 3
}
```
**Response `201`:**
```json
{
  "id": "uuid",
  "ocr_status": "pending",
  "upload_urls": [
    { "page_number": 1, "url": "https://signed-upload-url", "expires_at": "iso8601" }
  ]
}
```
**[Plan-Gated]** — returns `429 plan_limit_reached` (`limit_type: storage`) if the document would exceed the caller's storage quota, or (`limit_type: page_count`) if `page_count` exceeds the plan's per-document page limit (PRD §9: 20 free / 500 premium).

### `POST /documents/{id}/pages/{n}/complete`
Client calls this after successfully uploading a page image to its signed URL, triggering the OCR job (Technical Architecture §8).
**Response `202`:** `{ "ocr_status": "processing" }`

### `GET /documents/{id}`
Full document detail for the Document Detail screen (UX §3.7).
```json
{
  "id": "uuid",
  "title": "string",
  "folder_id": "uuid|null",
  "is_favorite": false,
  "ocr_status": "complete",
  "pages": [
    { "page_number": 1, "image_url": "signed-url", "extracted_text": "string" }
  ],
  "ai_ready": true
}
```
`ai_ready` is `false` until the chunking/embedding job (Architecture §7.1) finishes — the client shows the "Preparing AI…" disabled input state (Roadmap Sprint 2 note) while this is `false`.

### `PATCH /documents/{id}`
Rename, favorite/unfavorite, or move to a folder.
```json
{ "title": "string", "is_favorite": true, "folder_id": "uuid|null" }
```
`403 forbidden` (`code: folder_depth_exceeded`) if the target folder would exceed the 2-level nesting rule (Architecture §6).

### `DELETE /documents/{id}`
Soft-delete (moves to Recently Deleted, PRD §4.11). **Response `204`.**

### `POST /documents/{id}/restore`
Restores from Recently Deleted within the 30-day window. `404` if the recovery window has elapsed.

### `GET /documents/deleted`
List of soft-deleted documents with `days_remaining` per item (UX §3.9 Recently Deleted).

---

## 5. Folders

### `GET /folders`
Returns the folder tree (max depth 2). Each folder includes `document_count`.

### `POST /folders`
```json
{ "name": "string", "color": "string", "icon": "string", "parent_folder_id": "uuid|null" }
```
`422` if nesting would exceed depth 2.

### `PATCH /folders/{id}` / `DELETE /folders/{id}`
Standard rename/recolor and delete (deleting a folder does not delete its documents — they become unfiled, per the non-destructive UX pattern in §3.9).

---

## 6. AI Orchestration

This module is the API-level surface of Technical Architecture §7. Every endpoint here is scoped to a single `document_id`, matching the v1 "context awareness" rule (PRD §8) — there is no cross-document endpoint in this version (reserved for the Version 2 roadmap item, Architecture §7.3).

### 6.1 `POST /documents/{id}/chat` — streaming
**[Plan-Gated: `ai_question` counter]**

**Request:**
```json
{ "message": "string" }
```
**Response:** `200`, `Content-Type: text/event-stream`. Event stream frames:
```
event: token
data: {"text": "The lease "}

event: token
data: {"text": "renews annually "}

event: citation
data: {"chunk_id": "uuid", "page": 3, "start_offset": 120, "end_offset": 210}

event: done
data: {"message_id": "uuid"}
```
On a plan-limit or provider error mid-stream, the server sends a terminal `event: error` frame using the Section 2 error envelope as its `data` payload, and the client falls back to the inline chat error state (UX §2.4) with a retry action. If `ai_ready` was `false` on the parent document, this endpoint returns `409 conflict` (`code: ai_not_ready`) rather than accepting the request.

### `GET /documents/{id}/chat/messages`
Paginated chat history for reopening a thread (PRD §8 conversation memory).
```json
{
  "data": [
    {
      "id": "uuid",
      "role": "user|assistant",
      "content": "string",
      "citations": [{ "page": 3, "start_offset": 120, "end_offset": 210 }],
      "created_at": "iso8601"
    }
  ],
  "next_cursor": "string|null"
}
```

### `GET /documents/{id}/chat/suggested-prompts`
Returns 3–5 context-aware prompt strings for the chip row (UX §2.2), generated from the document's classified type (contract-like, academic, receipt-like — PRD §12 analytics tagging reused here).

### `POST /documents/{id}/summarize`
**[Plan-Gated: `ai_question` counter]**
```json
{ "length": "short|medium|detailed" }
```
**Response `200`:** `{ "summary": "string", "citations": [...] }` — appears as the first chat message per PRD §4.6.

### `POST /documents/{id}/translate`
**[Plan-Gated: language count — free plan capped at 3 languages per PRD §9]**
```json
{ "target_language": "es" }
```
`403` (`code: language_not_available_on_plan`) if the caller's plan doesn't include the requested language.

### `POST /documents/{id}/rewrite`
```json
{ "tone": "formal|simple|concise|persuasive", "scope": "full|selection", "selection_range": { "page": 2, "start_offset": 40, "end_offset": 300 } }
```

### `POST /documents/{id}/notes`
No body required beyond the document ID. Returns structured notes:
```json
{ "notes": [{ "heading": "string", "bullets": ["string"] }] }
```

---

## 7. Search

### `GET /search`
Powers the Search screen's two result groups (UX §3.10).

Query params: `q` (required), `folder_id` (optional), `type` (`title|content|all`, default `all`), `cursor`, `limit`.

**Response `200`:**
```json
{
  "title_matches": [
    { "document_id": "uuid", "title": "string", "thumbnail_url": "signed-url" }
  ],
  "content_matches": [
    {
      "document_id": "uuid",
      "title": "string",
      "snippet": "…the **lease** renews automatically unless…",
      "page": 3,
      "start_offset": 120,
      "end_offset": 210
    }
  ]
}
```
`snippet` bolding markers (`**`) indicate the matched term, matching the UX spec's bolded-match styling (§3.10).

---

## 8. Entitlements & Billing

### `GET /me/entitlements`
Polled on app foreground and post-purchase (Technical Architecture §10).
```json
{
  "plan": "free|premium",
  "status": "active|trial|cancelled|grace_period",
  "renews_at": "iso8601|null",
  "usage": {
    "ai_question": { "used": 4, "limit": 10, "resets_at": "iso8601" },
    "ocr_scan": { "used": 2, "limit": 5, "resets_at": "iso8601" },
    "storage_bytes": { "used": 104857600, "limit": 262144000 }
  }
}
```

### `POST /me/entitlements/validate`
Client-side receipt validation for the immediate optimistic unlock path (Technical Architecture §10).
```json
{ "store": "apple|google", "receipt": "base64-receipt-or-purchase-token" }
```
**Response `200`:** the same shape as `GET /me/entitlements`, now reflecting the (provisionally) unlocked plan.

### `POST /webhooks/apple` / `POST /webhooks/google`
Server-to-server only — not called by the mobile client. Verifies the store's signature, reconciles the `subscriptions` table as the authoritative source of truth (Technical Architecture §10). Always returns `200` quickly (per store requirements) after enqueuing the reconciliation job; actual processing is async.

---

## 9. Shared Plan-Gating Behavior

Every **[Plan-Gated]** endpoint above follows the same contract so the client needs only one handler:
1. Request is checked against `usage_counters` / plan rules **before** any downstream work (OCR job enqueue, LLM call) starts, per Technical Architecture §7.1 step 6 — the caller is never billed for provider usage they weren't entitled to.
2. On success, the relevant counter increments atomically in the same transaction as the request being accepted.
3. On limit breach, returns `429` with the Section 2 plan-limit error shape including `limit_type` and `resets_at`, which the client uses to render the specific limit-reached banner (UX §5) and route "Upgrade" taps to the Paywall screen (UX §3.11) with the right context pre-filled.

---

## 10. Notifications

### `GET /notifications`
Paginated notification history (UX §3.12 Notifications Center).
```json
{ "data": [{ "id": "uuid", "type": "processing_complete|storage_warning|weekly_summary|subscription_reminder", "payload": {}, "sent_at": "iso8601", "read_at": "iso8601|null" }] }
```

### `PATCH /notifications/{id}`
```json
{ "read": true }
```

### `PATCH /me/notification-preferences`
```json
{ "scan_reminders": true, "storage_usage": true, "weekly_summary": true, "subscription_reminders": true }
```
`subscription_reminders` cannot be disabled (mandatory for compliance, per PRD §11) — attempting to set it `false` returns `422`.

---

## 11. Sync

Backs the offline-first architecture (Technical Architecture §4.2).

### `POST /sync`
Batch endpoint for draining the client's local mutation queue.
```json
{
  "mutations": [
    {
      "idempotency_key": "uuid",
      "entity_type": "document|folder",
      "entity_id": "uuid",
      "operation": "update|delete",
      "payload": { "title": "string" },
      "client_timestamp": "iso8601"
    }
  ]
}
```
**Response `200`:**
```json
{
  "results": [
    { "idempotency_key": "uuid", "status": "applied|conflict|rejected", "server_state": { } }
  ]
}
```
`status: conflict` triggers the rare manual-merge UX path (PRD §4.12); `server_state` carries the authoritative version for the client to reconcile or present.

### `GET /sync/changes?since={iso8601}`
Pull-based reconciliation for changes made on other devices — returns all entities updated after the given timestamp, used on app foreground to catch up without a full re-fetch.

---

## 12. Rate Limiting

Independent of plan-based usage counters (Technical Architecture §14 abuse prevention): a general **60 requests/minute per user** limit applies across all endpoints, and a stricter **10 requests/minute** limit applies specifically to `/documents` (POST) and `/documents/{id}/chat` regardless of plan, to bound cost exposure from any single compromised or scripted client. Both return `429 rate_limited` with a `Retry-After` header.

---

## 13. Webhook Signature Verification

Both `/webhooks/apple` and `/webhooks/google` verify the payload signature against the respective store's public key/JWT verification scheme before processing; unverified payloads return `401` and are logged but never trigger entitlement changes — this is the boundary that keeps the billing source of truth (Technical Architecture §10) trustworthy.

---

*End of document.*
