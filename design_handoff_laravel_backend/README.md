# Handoff: SEBENARNYA.MY — Laravel Backend & Database

## Overview
SEBENARNYA.MY is a fact-checking / misinformation-reporting platform with three portals:
- **Public User** — submits inquiries to verify news, tracks their status, browses other public inquiries.
- **MCMC** (regulator, the intermediary) — triages incoming inquiries, assigns them to the right agency, monitors progress, runs reports, manages agency & user accounts, and mediates all agency-to-agency communication.
- **Agency** (e.g. Ministry of Health, Bank Negara Malaysia, etc.) — receives inquiries assigned by MCMC, investigates within its jurisdiction, updates status, and can only reach another agency by asking MCMC to loop them into the case (MCMC is always the intermediary — agencies never talk to each other directly).

## About the design files
The files in `design-files/` are **HTML/JS design references** (interactive prototypes built with a component runtime — you'll see `<x-dc>` templates and a `DCLogic` class per file). They show the exact intended UI, copy, states, and client-side behavior. **Do not ship this HTML as-is.** Your job is to:
1. Stand up a Laravel backend + database that models the entities and workflows below.
2. Rebuild the UI in Laravel's normal stack (Blade/Livewire, or Inertia+Vue/React — ask the user which they prefer if unset) reproducing these screens' layout, copy, and behavior faithfully, wired to real endpoints instead of the in-memory/localStorage data the prototypes currently use.

Two JS files encode real business logic worth reading closely before modeling data:
- `clarify-store.js` — the full state machine for MCMC-mediated clarification/consultation threads (this is the "MCMC as intermediary between agencies" flow).
- `export-utils.js` — the report spec shape (`{title, org, period, filters, groups:[{kpis, blocks:[{type:'table'|'chart', columns, rows}]}]}`) used to generate the PDF/Excel exports on every reports screen. Your report endpoints should be able to produce this same shape.

## Fidelity
**High-fidelity.** Every screen, status label, and copy string below is taken verbatim from the built prototypes — treat exact strings (status names, button labels, error messages) as production copy, not placeholders.

## Suggested phased delivery
Run this as a multi-session Claude Code project, one phase per session/PR:

1. **Phase 1 — Foundations**: Laravel install, auth scaffolding for 3 role types, full DB schema/migrations, seeders matching the prototype's sample data (agencies, categories, statuses).
2. **Phase 2 — Public portal**: registration/login/email verify/forgot-password, inquiry submission with evidence upload, my-inquiries list + detail + activity log, public browse with search/filter, notifications, profile (photo crop-upload).
3. **Phase 3 — MCMC portal**: staff login, dashboard KPIs/trend chart, triage (validate/discard/bulk-discard), assignment to agency, all-inquiries table, agency & user management (register agency → temp password emailed), settings.
4. **Phase 4 — Agency portal**: staff login with forced password change on first login, assigned-inquiries workflow (jurisdiction accept/reject → reassignment), status updates with reviewing-officer + timestamp, activity log, profile.
5. **Phase 5 — Clarification/consultation, reports & exports**: the MCMC-mediated clarification thread system, all reporting screens with PDF/Excel export, cross-cutting notification center polish.

Give Claude Code this whole README for context every phase, but tell it which phase to build.

---

## Roles & Auth
Three separate credential sets, one system:

| Role | Table (suggested) | How they get an account | Login fields |
|---|---|---|---|
| Public User | `users` (role=`public`) | Self-registers (`02-public-auth-flow.dc.html`) — email + password, must verify email before full access | email + password |
| MCMC Staff | `users` (role=`mcmc_staff`) | Provisioned internally (no public signup) | staff username + password |
| Agency Staff | `users` (role=`agency_staff`, belongs to an `agency_id`) | Registered **by MCMC** (`15-mcmc-user-management.dc.html` / `07-mcmc-agency-reports.dc.html` agency form) — system auto-generates a temp password, emails it; agency **must** change password on first login (enforce server-side, not just UI) | username + temporary/real password |

Common auth requirements seen across `01`, `02`, `05`, `08`:
- Password recovery via email for all three roles.
- Strong password policy enforced on agency first-login change and register: min 8 chars, 1 uppercase, 1 number, 1 special char (see `checkPwRequirements`/`checkRequirements` in `05` and `08` — mirror these exact rules server-side).
- All logout actions route back to the unified `01-login.dc.html` (role-tabbed) login screen — keep one login URL with a role switch, not three separate login pages, if you rebuild `01` as the entry point.

## Core entities

**User** — id, name, email, phone, password_hash, role (public|mcmc_staff|agency_staff), profile_photo, email_verified_at, must_change_password (bool), created_at, last_active_at.

**Agency** — id, name, specialization/jurisdiction (enum-ish free text, e.g. "Health", "Finance", "Public Policy"), contact_email, active_inquiries_count, resolved_count, status (active/inactive), created_at.

**Inquiry** — id, title, category, description, source_link, submitted_by (user_id, nullable if anonymous not allowed — check `03` submit form), status, assigned_agency_id (nullable), assigned_at, reviewed_by (user_id), reviewed_at, decision, prior_submissions_count, registered_date. Statuses (exact strings, used verbatim in UI — pick one canonical set and map): `Submitted` → `Under Investigation` → `Verified True` | `Identified Fake` | `Rejected` (agency declines jurisdiction, goes back to MCMC for reassignment) | `Discarded` (MCMC triage rejects as non-serious, terminal).

**InquiryEvidence** — id, inquiry_id, file_path, original_name, uploaded_at.

**InquiryActivityLog** — id, inquiry_id, actor_user_id, action_label, note, happened_at. Every screen with an "Activity Log" tab (03, 04, 05, 06, 08, 09, 12, 15) reads this per-inquiry timeline — build it generically, one row per state change/comment.

**ClarificationThread** — models `clarify-store.js` exactly:
  - id (format `CLR-YYYY-NNNN`), inquiry_id, owning_agency_id, topic (enum: Jurisdiction/scope, Missing or unclear evidence, Source verification, Submitter follow-up, Related or duplicate cases, Other), priority (Normal/Urgent), status (open=awaiting MCMC, answered=MCMC responded awaiting agency, closed=resolved), unread_by_agency (bool), unread_by_mcmc (bool), closed_by, created_at, updated_at.
  - **ClarificationMessage** — thread_id, from (agency|mcmc|system|consult), author_name, author_role, body, files[], sent_at, consult_agency_id (nullable, set when the message is part of a consult sub-thread).
  - **ClarificationConsult** — thread_id, consulted_agency_id, question, invited_by, invited_at, status (pending|responded|ended), responded_at, unread (bool). **Business rule**: only MCMC can create a consult (invite another agency into a thread); the consulted agency can only reply within its own consult sub-thread — it never gets thread-owner powers (can't close the thread, can't message the owning agency directly). Enforce this in policy/authorization, not just UI.

**Notification** — id, user_id (or role+scope for broadcast), type, title, body, related_inquiry_id (nullable), read (bool), created_at. Every portal has a bell-icon dropdown + full notification center with filter tabs — same shape reused 3x (see `11-shared-components.dc.html` for the canonical pattern).

**Report** — reports are generated on-demand, not stored; model your report endpoints to return the `export-utils.js` spec shape (kpis + chart data + table rows) so the existing PDF/Excel export code can stay largely client-side, or port that export logic server-side if preferred.

## Screen-by-screen map

| File | Portal | Purpose |
|---|---|---|
| `01-login.dc.html` | Shared | Unified login, role tabs (Public/MCMC/Agency), left-side photo panel |
| `02-public-auth-flow.dc.html` | Public | Register, verify-email, forgot/reset password |
| `03-public-core.dc.html` | Public | Dashboard, submit inquiry, my inquiries + detail/activity panel, notifications |
| `04-public-extra.dc.html` | Public | Browse all public inquiries (search+filter, sensitive info hidden), profile (info + security tabs, photo crop-upload) |
| `05-mcmc-auth-dashboard.dc.html` | MCMC | Staff login/forgot, dashboard KPIs + monthly trend chart, quick-access cards, notifications, settings (account/password/notification prefs) |
| `06-mcmc-triage-assignment.dc.html` | MCMC | Pending inquiry triage (validate/discard/bulk-discard), assignment to agency, reassignment queue, reviewed history, clarification inbox |
| `07-mcmc-agency-reports.dc.html` | MCMC | Agency roster + registration (issues temp password), user growth reports, inquiry reports, agency performance reports — all with PDF/Excel export |
| `08-agency-auth-dashboard.dc.html` | Agency | Staff login (temp password), forced first-login password change, dashboard, notifications, settings |
| `09-agency-workflow.dc.html` | Agency | Assigned inquiries list + detail panel: jurisdiction accept/reject, status update with notes, reviewing-officer attribution, clarification request to MCMC |
| `10-agency-profile.dc.html` | Agency | Profile info (agency name/contact — jurisdiction is MCMC-locked/read-only) + password/security |
| `11-shared-components.dc.html` | — | Reference sheet only — reusable notification/empty/error/404 patterns; not a real screen |
| `12-mcmc-all-inquiries.dc.html` | MCMC | Full inquiry registry across all statuses/agencies, same detail panel pattern as 06 |
| `13-agency-reports.dc.html` | Agency | Agency's own performance report (summary, category breakdown, resolved records) with export |
| `14-agency-activity.dc.html` | Agency | Agency's own activity log across all its inquiries, filterable, exportable |
| `15-mcmc-user-management.dc.html` | MCMC | Public user directory (profiles, registration details, activity), likely shares agency-registration form with `07` |
| `Navigation Shell.dc.html` | — | Shared sidebar/header chrome reference |

## Key workflow rules to preserve exactly
- **Triage**: MCMC validates (assigns to an agency) or discards (marks non-serious, terminal, logged) each new inquiry; bulk-discard exists for multi-select.
- **Jurisdiction rejection**: an agency can reject an assignment as outside its jurisdiction with a required reason; MCMC then reassigns to a different agency — the inquiry never silently disappears.
- **Status updates**: every status change requires a note, is timestamped, and is attributed to the reviewing officer (agency staff name) — this triple (timestamp, note, officer) must be captured on every transition, not just the current status.
- **Clarification (agency → MCMC)**: agency opens a thread on a specific inquiry (only one active thread per inquiry at a time — `clarify-store.js` enforces this); MCMC replies; either side can close.
- **Consultation (MCMC → other agency, mediated)**: within an open clarification thread, MCMC can invite a *different* agency to advise. That consulted agency sees the case context and can reply, but cannot close the thread or message the owning agency outside it. This is requirement "MCMC as intermediary between agencies" — do not let agencies message each other directly anywhere in the schema or API.
- **Reports**: every report screen supports filtering by date range/agency/category and exports to both PDF and Excel with the same filtered dataset — keep filter params and export payload in sync.
- **Notifications**: unread counts drive a badge on the bell icon in every portal header; "mark all as read" is per-user.

## Assets
`design-files/assets/` — brand photo (`login-photo.jpg`, newspaper/fact-checking imagery) and the SVG logo mark used inline across screens (dashed red rings + checkmark, ~512 viewBox — same mark repeated everywhere, worth extracting to one Blade component/partial).

## Files
See `design-files/` for all 15 numbered screens + shared shell + the two logic files (`clarify-store.js`, `export-utils.js`) referenced above.
