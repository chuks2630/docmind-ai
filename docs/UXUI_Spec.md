# DocMind AI — UX/UI Specification
### Companion to the Product Requirements Document (v1.0)

**Platform:** Flutter (iOS + Android) · Mobile-first
**Document owner:** Design
**Status:** Draft v1.0

---

## 1. Design Principles & Personality

DocMind sits between two worlds the PRD's personas live in: the physical paper world (scanning, receipts, contracts, handouts) and the digital intelligence world (AI chat, citations, summaries). The visual language has to feel **trustworthy and legible enough for a lawyer**, while being **fast and warm enough for a student between classes**.

**Personality:** *Clear-headed librarian, not a chatbot.* Calm, precise, unshowy. The product's confidence comes from getting things right (accurate scans, cited answers) — not from flashy AI theatrics.

**Three design commitments:**
1. **Paper is the material, light is the intelligence.** Every screen is built on a paper-like surface; AI presence is always signaled with light/highlight metaphors (see Signature Element, 1.1), never with generic "sparkle" iconography.
2. **Citations are load-bearing, not decorative.** Anywhere the AI makes a claim, the source is visually one tap away. This is the product's core trust mechanic and must never be visually buried.
3. **Quiet by default, precise everywhere.** Minimal ornament; the boldness budget is spent entirely on the Signature Element below.

### 1.1 Design Tokens

**Color — named palette:**

| Token | Hex | Usage |
|---|---|---|
| `paper` | `#F6F5F1` | Primary light-mode background — a cool, slightly grey paper tone (not warm cream) |
| `ink` | `#1B1F2B` | Primary text; primary dark-mode background |
| `ink-soft` | `#565C6B` | Secondary text, captions, metadata |
| `signal` | `#2E4E8F` | Primary actions, links, active nav state — a confident "stamped" blue, evokes an official document stamp rather than a generic app blue |
| `highlighter` | `#E8B930` | AI citations, highlight tool, the Signature scan-line — a literal reference to a highlighter pen |
| `sync-green` | `#3E8E6D` | Success states, sync confirmation, completed processing |
| `alert` | `#C1473B` | Errors, destructive actions, limit-reached states |
| `line` | `#DFDCD3` | Hairline dividers, card borders (light mode) |

**Typography — three roles:**

| Role | Typeface | Notes |
|---|---|---|
| Display (headlines, empty-state titles, paywall headline) | **Newsreader** (serif) | Used deliberately and sparingly — gives the brand a literate, document-native feel without tipping into "editorial magazine." Semi-bold, tight tracking. |
| Body / UI (everything interactive: buttons, chat, nav, body copy) | **Inter** | Humanist sans, excellent at small sizes, the workhorse face. |
| Utility / Data (timestamps, page numbers, citation tags, token counters) | **IBM Plex Mono** | Reinforces the "document metadata" feel — used only for small, factual strings, never for prose. |

**Type scale (base 16px / 1.25 ratio):**
`Display XL 32/40` · `Display L 26/32` · `Title 20/28` · `Body 16/24` · `Body Small 14/20` · `Caption/Mono 12/16`

**Spacing scale (4px base):** `4 · 8 · 12 · 16 · 24 · 32 · 48 · 64`

**Radius:** Cards & sheets `16px` · Buttons & chips `12px` · Document page thumbnails `4px` (mimics a trimmed paper corner, deliberately sharper than the rest of the UI)

**Elevation:** Flat design with one soft shadow level only (`0px 4px 16px rgba(27,31,43,0.08)`), used exclusively for floating elements (the elevated Scan button, bottom sheets, toasts) — everything else is separated with `line` hairlines, not shadows, to keep the paper metaphor flat and calm.

### 1.2 Signature Element — The Scan Line

A single thin `highlighter`-colored line (2px, soft-glow) that sweeps top-to-bottom. It appears in exactly three moments, and nowhere else, so it retains meaning:
1. **During OCR/processing** — sweeps the document image top to bottom, echoing a physical scanner bar.
2. **When an AI answer cites a source** — the same line sweeps to and briefly rests under the cited paragraph in the document viewer.
3. **On the splash/loading screen** — a single sweep across the wordmark on cold start.

This is the one piece of motion the brand "spends its boldness on" — everywhere else, motion is restrained (see Section 5).

---

## 2. Component Library

### 2.1 Buttons
- **Primary:** `signal` fill, white text, `Body` weight 600, 48px height, 12px radius. Full-width on forms, intrinsic-width in cards.
- **Secondary:** `paper` fill, 1px `line` border, `ink` text.
- **Destructive:** `alert` text on transparent, used only in confirmation sheets, never as a primary page action (prevents accidental deletes).
- **Icon-only:** 40x40 tap target minimum regardless of visual icon size (24px icon centered).

### 2.2 Suggested-Prompt Chips
Pill shape, 12px radius, `line` border, `ink-soft` text, `paper` fill. On tap, briefly fills with `highlighter` at 15% opacity before submitting — reinforces "this is an AI-lit suggestion."

### 2.3 Document Card (grid/list)
- Grid: 4px-radius page thumbnail with a subtly stacked "second page" shadow if multi-page, title (Body, 1 line truncate), folder-color dot, last-opened `Caption/Mono` timestamp.
- List: thumbnail (40x52) left, title + metadata stacked right, chevron.
- Long-press → contextual action sheet (Move to folder, Favorite, Share, Export, Delete).

### 2.4 Chat Bubble (AI Thread)
- **User message:** right-aligned, `signal` at 10% fill, `ink` text, 16px radius (bottom-right corner 4px to indicate direction, a subtle "speech tail" without literally drawing one).
- **AI message:** left-aligned, `paper` fill with 1px `line` border, `ink` text, same radius logic mirrored.
- **Citation chip:** inline, small `Caption/Mono` pill in `highlighter` at 20% fill with a page-icon glyph, e.g. `p.3 ¶2`. Tapping it triggers the Signature scan-line in the document viewer.
- **Streaming state:** three-dot pulse in `ink-soft`, replaced token-by-token by the real response — no separate "typing" avatar, to keep focus on content not chatbot personality.

### 2.5 Bottom Navigation
5 slots, fixed, `paper`/`ink` background per theme, 1px top `line` border, no shadow. Center slot is the elevated **Scan** button: a `signal`-filled circle, 56px, floating 8px above the bar with the one permitted shadow (`elevation` token). Active tab indicated by `signal` icon + label color; inactive tabs use `ink-soft`.

### 2.6 Sheets & Modals
Bottom sheets (folder actions, share options, filters) use 16px top-corner radius, a 4px drag handle, `paper` background, and slide up over a 40%-opacity `ink` scrim. Reserved for contextual, dismissible actions; full paywall and onboarding use full-screen pages, not sheets, since they require deliberate reading rather than a quick glance.

### 2.7 Toasts / Inline Confirmations
Bottom-anchored, 8px above the nav bar, `ink` fill (dark) regardless of theme for consistent legibility, white text, auto-dismiss 3s, single optional action link (e.g., "Undo").

---

## 3. Screen-by-Screen UI Specification

Each entry maps to the PRD's Screen Inventory (Section 6) and specifies layout, hierarchy, and key measurements.

### 3.1 Splash / Onboarding
- Splash: wordmark centered, Signature scan-line sweep once, 900ms, auto-advance.
- Value-prop carousel: full-bleed illustration top 60% of viewport, `Display L` headline + `Body` subtext bottom 40%, page dots at 24px above the Skip/Next row. Swipeable, Skip always visible top-right in `ink-soft`.

### 3.2 Sign Up / Log In
Vertically centered stack: wordmark (small), `Title` "Get started," three full-width buttons (Apple, Google, Email) each 48px with 12px gaps, then a `Body Small` "Continue as guest" text link beneath, not a button — visually de-emphasized since it's a secondary path.

### 3.3 Home
- Top: greeting (`Title`) + avatar (40px, top-right, opens Profile).
- Quick actions row: two large tappable cards side-by-side (Scan / Import), each with icon top, label bottom, `paper` fill with `line` border, 96px height.
- "Recent" section: horizontal-scroll Document Cards (grid variant), section header `Body` bold + "See all" link in `signal`.
- "Continue chatting" section: same card pattern but showing last AI message preview (`Body Small`, 2-line truncate) instead of timestamp.
- Empty state (first launch, no documents): illustration centered, `Display L` "Scan your first document," single primary button "Scan now."

### 3.4 Camera / Scan Screen
Full-bleed camera preview. Edge-detection overlay drawn in `highlighter` at 60% opacity, animated corner-snap on lock. Bottom control bar (88px, `ink` at 70% opacity scrim over camera): flash toggle left, large 64px shutter center (white ring, `signal` fill), page counter badge right ("+2") when multi-page session active. "Done" text button appears top-right once ≥1 page captured.

### 3.5 Scan Preview / Crop
Cropped image fills upper 75%; filter selector as horizontal chip row (Auto / Color / B&W) below; "Retake" (secondary) and "Confirm" (primary) buttons pinned bottom, full-width split 40/60.

### 3.6 Processing / OCR
Centered document thumbnail with the Signature scan-line actively sweeping; `Body` status text beneath cycles ("Reading text…" → "Almost done…"); no percentage number shown (uncertain durations — a moving line reads as "working" better than a possibly-wrong progress bar).

### 3.7 Document Detail
Two-zone vertical split, resizable by drag handle (defaults 55/45):
- **Upper zone — Viewer:** paged document image(s), pinch-zoom, page indicator dots bottom-center of this zone.
- **Lower zone — AI Chat:** suggested-prompt chips row (only before first message), chat thread, input bar pinned to lower-zone bottom with a leading "+" for action menu (Summarize / Translate / Rewrite / Explain / Notes) and a send icon that becomes a stop-icon while streaming.
- Top app bar: back chevron, editable title (`Title`, tap to rename inline), overflow menu (Favorite / Share / Export / Move / Delete).

### 3.8 Summary / Notes / Translation Views
Presented as a sheet over the Document Detail viewer (not a new full page) so the source stays one swipe-down away. Length/language selector as a segmented control at top of the sheet; content body in `Body`; sticky footer with Copy / Share / Export icon row.

### 3.9 Folders
Grid of folder tiles (2 columns), each a colored icon tile + name + `Caption/Mono` document count. "+ New Folder" as the final grid tile, dashed `line` border to visually read as an add-slot rather than a folder.

### 3.10 Search
Sticky search field top (auto-focused on tab entry), below it two result groups with `Body` bold section labels: "Documents" (title matches) and "Inside your documents" (content matches, each result showing a `Body Small` snippet with the matched term bolded in `signal`). Tapping a content result opens Document Detail with the scan-line already resting on the matched passage.

### 3.11 Premium / Paywall
Full-screen, not a sheet. `Display L` headline stating the core unlock ("Ask unlimited questions"), a compact comparison table (Free column vs Premium column, checkmarks in `sync-green`/dashes in `ink-soft`), monthly/annual segmented toggle with a `highlighter`-badge "Save 50%" on annual, single full-width primary CTA button whose label states the exact trial/price ("Start 7-day free trial"), and a small `Caption` legal line beneath with restore-purchase link.

### 3.12 Settings / Profile
Standard grouped list pattern: avatar + name + plan badge (a `highlighter`-fill pill reading "Premium" when applicable) at top, then grouped `line`-divided rows (Appearance, Language, Notifications, Storage — with an inline usage bar, Privacy, Help, Sign out). Sign out is a `Body` link in `alert`, isolated at the bottom with extra top margin to prevent mis-taps.

---

## 4. Interaction & Motion Patterns

- **Page transitions:** standard platform-native push/pop (iOS slide, Android's default motion) — DocMind does not override OS navigation physics; consistency with the platform builds trust faster than custom transitions.
- **Sheet transitions:** 250ms ease-out slide-up with scrim fade-in, matching the OS's native sheet feel.
- **Micro-interactions:** shutter button scales to 92% on press; chip tap has a 100ms fill transition; favorite star has a single small scale-bounce (1 → 1.15 → 1) on toggle-on only, no animation on toggle-off (removal should feel quiet, addition should feel slightly delightful).
- **Reduced motion:** when the OS reduced-motion setting is on, the Signature scan-line becomes a static highlight fade-in instead of a sweep; all scale/bounce micro-interactions are disabled in favor of instant state changes.
- **Loading skeletons:** used only for list/grid content (Home, Folders, Search) with a slow 1.5s shimmer in `line` tone — never for the chat thread, which uses the streaming-dot pattern instead.

---

## 5. States — Visual Treatment

Applying the PRD's required Empty / Loading / Error / Success states consistently:

| State | Visual pattern |
|---|---|
| **Empty** | Centered single-color line illustration (not a stock photo), `Title` headline framed as an invitation ("Star documents to find them here"), one primary action if applicable. Never just blank white space with small grey text. |
| **Loading** | Skeleton shimmer for lists/grids; Signature scan-line for document/AI processing; never a generic spinner alone on a screen that has known content structure. |
| **Error** | `alert`-tinted icon, plain-language explanation of what happened (not a code), a single clear recovery action ("Retake photo," "Try again"). Errors never use passive/apologetic language — they state the fact and the fix. |
| **Success** | Brief toast (`sync-green` accent icon within the standard `ink` toast) or, for larger completions (subscription purchase, export complete), a full-screen confirmation with `Display` headline and single "Done" dismissal. |

---

## 6. Dark Mode Specification

Dark mode is a first-class theme, not an inverted filter:
- `ink` (`#1B1F2A`) becomes the background; `paper` becomes primary text via a lightened `#F1EFEA`.
- `signal` shifts to a slightly brighter `#5C82D6` to maintain 4.5:1 contrast on dark backgrounds.
- `highlighter` stays the same hex but is used at lower opacity (12–15% vs 15–20% in light mode) to avoid glare against dark surfaces.
- Document thumbnails always render on a light "paper" mat (`#F1EFEA`) even in dark mode, since scanned documents are inherently light-background content — inverting them would misrepresent the source.

---

## 7. Accessibility Specifications

- All text meets WCAG AA (4.5:1 for body text, 3:1 for large `Display` text) in both themes.
- Every interactive element has a minimum 44x44pt (iOS) / 48x48dp (Android) tap target, including chips and icon buttons, regardless of visual glyph size.
- Full VoiceOver/TalkBack labeling: citation chips announce as "Citation, page 3, paragraph 2, activate to view source," not just "button."
- Dynamic type support up to at least 130% scale without truncation on primary actions; body text reflows rather than clipping.
- Color is never the sole indicator of state — the favorite star also changes fill/outline, the sync status also has a text label, not just a colored dot.
- The Signature scan-line's motion respects `prefers-reduced-motion` (see Section 4).

---

## 8. Platform Adaptations (iOS vs Android)

| Element | iOS | Android |
|---|---|---|
| Navigation transitions | Native slide-from-right push | Native Material shared-axis transition |
| Action sheets | iOS-style bottom action sheet with grouped/destructive styling | Material bottom sheet with ripple feedback |
| Typography rendering | SF-style dynamic type scaling respected on top of custom fonts | Android's font-scale setting respected identically |
| Back navigation | Swipe-from-edge back gesture supported everywhere | System back button/gesture supported everywhere; no custom overrides that break it |
| Permission priming | Custom priming screen before native iOS permission dialog | Custom priming screen before native Android permission dialog, worded per Android's rationale-dialog conventions |

Both platforms share the exact same visual design tokens (color, type, spacing) — only navigation physics and system-level interaction patterns are allowed to diverge, to respect user muscle memory on each OS without fragmenting the brand.

---

## 9. Content & Voice Guidelines

- **Buttons name the action's result, not the mechanism:** "Save changes," not "Submit." A button labeled "Start free trial" produces a toast that says "Trial started" — the vocabulary never shifts mid-flow.
- **Errors state fact + fix, no apology filler:** "Couldn't read this photo — try retaking it in better light," not "Oops! Something went wrong."
- **Empty states invite action, not describe absence:** "Scan your first document" rather than "You have no documents."
- **AI voice is plain, never performative:** summaries and explanations are written in direct, active sentences — no "Great question!" or "As an AI..." framing. The product's confidence is shown through citations, not through chatty affect.
- **Metadata is always in the Mono utility face** (timestamps, page/paragraph citations, token/usage counters) so users learn to visually distinguish "fact" from "prose" at a glance.

---

*End of document.*
