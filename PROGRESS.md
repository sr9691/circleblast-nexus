# CircleBlast Nexus – Progress Tracker

## ✅ PROJECT FEATURE-COMPLETE — ITER-0003 through ITER-0017

---

## Completed: Post-Release Code Review & Fixes (February 2026)

### Goals

- Comprehensive code review of the full codebase
- Fix all identified bugs, security issues, and performance problems

### Fixes Applied (14 total)

**Security (3 fixes):**
- [x] Fireflies webhook now rejects all requests when no secret is configured (was accepting all — high severity)
- [x] Added CSRF nonce verification to all 5 token router POST forms (defense-in-depth)
- [x] Removed database fallback for Claude API key; now requires wp-config.php constant per SECURITY.md

**Bugs (6 fixes):**
- [x] Use `$wpdb->prepare()` for SHOW TABLES LIKE in recruitment coverage service
- [x] Add explicit format array to meeting repository `update()` for DECIMAL/DATETIME columns
- [x] Check per-user response status before sending follow-up reminders (was notifying already-responded members)
- [x] Fix nullable integer handling in email log insert (conditional column inclusion)
- [x] Handle JSON string values in `category_select` sanitization (decode before cast)
- [x] Cache portal URL query in static variable (was running LIKE query on every call)

**Performance (3 fixes):**
- [x] Cache `get_full_coverage()` result per request (was computing 2-3× per page load)
- [x] Batch active meeting pair checks into single query in matching engine (was N² individual queries)
- [x] Prime user meta cache with `update_meta_cache()` before bulk profile loads in member repository

**Code Quality (2 fixes):**
- [x] Add all 10 portal admin tab classes to autoloader class map
- [x] Move dev scripts (`create-members.php`, `seed-data.php`) to `dev/` directory

### Files Modified

- `includes/recruitment/class-recruitment-coverage-service.php` — SQL prepare, per-request caching
- `includes/meetings/class-meeting-repository.php` — format array
- `includes/matching/class-suggestion-generator.php` — per-user reminder check
- `includes/emails/class-email-service.php` — nullable int handling
- `includes/members/class-member-service.php` — JSON category sanitization
- `includes/public/class-portal-router.php` — static URL cache
- `includes/circleup/class-fireflies-webhook.php` — default-deny
- `includes/tokens/class-token-router.php` — CSRF nonces
- `includes/circleup/class-ai-extractor.php` — remove DB key fallback
- `includes/matching/class-matching-engine.php` — batch active pairs
- `includes/members/class-member-repository.php` — meta cache priming
- `includes/class-autoloader.php` — admin tab entries
- `includes/class-color-scheme.php` — cron-safe documentation

---

## Completed Iteration: ITER-0017 (Admin Analytics, Reports & Recruitment Pipeline)

### Goals

- Admin tools for engagement monitoring, automated reports, and new-member pipeline

### Deliverables

- [x] Migration 013: cb_candidates table (name, email, company, industry, referrer_id, stage, notes, timestamps)
- [x] includes/admin/class-admin-analytics.php — Club overview cards (members, meetings, suggestions, acceptance rate). Per-member engagement table: meetings, unique met, CircleUp, notes %, accept %, engagement score (0-100 weighted composite), churn risk (low/medium/high based on inactivity + score). CSV export with all analytics columns. Automated monthly report email to admins/super admins via WP-Cron.
- [x] includes/admin/class-admin-recruitment.php — Pipeline funnel with stage filter buttons (referral→contacted→invited→visited→decision→accepted→declined). Inline candidate intake form with referrer dropdown. Stage updates via dropdown auto-submit. Full candidate list with stage, notes, referrer, updated date.
- [x] templates/emails/monthly_admin_report.php — Stat cards (members, meetings, acceptance rate, high-risk count) + portal link
- [x] WP-Cron: monthly admin report
- [x] Updated autoloader, migration runner, main bootstrap

### Engagement Score Formula

- Meetings completed: up to 30pts (3pts × min(meetings, 10))
- Unique connections: up to 24pts (3pts × min(unique, 8))
- CircleUp attendance: up to 24pts (4pts × min(circleup, 6))
- Notes completion: up to 12pts (notes_pct × 0.12)
- Acceptance rate: up to 10pts (accept_pct × 0.10)

### Churn Risk Criteria

- High: >90 days inactive OR score <20
- Medium: >45 days inactive OR score <40
- Low: everything else

---

## Completed Iteration: ITER-0016 (Club Dashboard & Presentation Mode)

### Goals

- Group-wide analytics and screen-share-optimized presentation for CircleUp meetings

### Deliverables

- [x] includes/public/class-portal-club.php — Club dashboard with 6 stat cards (active members, new members 90d, 1:1 meetings, network density %, CircleUp count, wins total). Top connectors leaderboard (UNION query across member_a/b). Discussion topic cloud (word frequency from CircleUp items, 20 keywords). Recent wins grid (6 cards). Presentation mode: fullscreen dark-gradient layout with giant stat numbers, wins grid, connectors leaderboard, QR code (via qrserver.com API) for portal access.
- [x] "Club Stats" section added to portal navigation with chart-area icon
- [x] Nightly analytics snapshot via WP-Cron (club-scope: total_members, meetings_total, network_density, wins_total → cb_analytics_snapshots)
- [x] Presentation CSS: dark gradient background, gradient header text, stat cards, wins grid with frosted glass effect, ranked leaderboard, responsive
- [x] Updated autoloader, portal router, main bootstrap

---

## Completed Iteration: ITER-0015 (Personal Member Dashboard)

### Goals

- Individual engagement metrics and activity tracking replacing the placeholder dashboard
- Live data from meetings, CircleUp, and member systems

### Deliverables

- [x] includes/public/class-portal-dashboard.php — Personal dashboard with live stats: 1:1 meetings completed, unique members met (with % of total), CircleUp attendance count, notes completion rate, wins/insights contributed count
- [x] Action Required section: pending meeting requests + meetings needing notes, with direct links
- [x] Two-column layout: Upcoming meetings (with status pills) + Personal action items (from CircleUp)
- [x] Recent meeting history with notes completion status per meeting
- [x] Migration 012: cb_analytics_snapshots table (snapshot_date, scope, member_id, metric_key, metric_value) for future trend tracking
- [x] Dashboard CSS: highlight cards, alert rows, two-column grid, responsive
- [x] Portal router: dashboard section now renders CBNexus_Portal_Dashboard::render

### Risks / Notes

- Stats computed live via SQL on each render; fine for small groups, snapshot table available for future caching
- get_upcoming currently returns all non-terminal meetings; will refine in ITER-0016

---

## Completed Iteration: ITER-0014 (CircleUp Archive & Member Submissions)

### Goals

- Member-facing archive of published CircleUp meetings with search and browse
- Quick submission form and personal action item tracker

### Deliverables

- [x] includes/public/class-portal-circleup.php — Timeline of published meetings with summary previews and stat badges (wins, insights, duration). Individual meeting detail pages with expandable sections per item type (wins, insights, opportunities, actions) with speaker attribution. Full-text search across all approved items via AJAX. Quick submit form (win/insight/opportunity) attached to most recent meeting. Personal action items page with due dates and status.
- [x] assets/js/circleup.js — 400ms debounce search, quick submit form with success/error messaging
- [x] Portal router: "CircleUp" section added to navigation with megaphone icon
- [x] CircleUp archive CSS: timeline with dot markers, submit row, items list, actions table, responsive
- [x] Updated autoloader, main bootstrap

### Risks / Notes

- Quick submissions attach to most recent published meeting; no standalone bucket yet
- Search is SQL LIKE on item content; adequate for small datasets, consider fulltext index at scale
- Action items are read-only in portal; status updates through admin Archivist UI

---

## Completed Iteration: ITER-0013 (AI Extraction Pipeline & Archivist Workflow)

### Goals

- Extract structured insights from transcripts via Claude API
- Archivist review tools: edit, approve/reject items, curate summaries
- Publish workflow with auto-generated summary email to all members

### Deliverables

- [x] includes/circleup/class-ai-extractor.php — Claude API integration: builds prompt with member name map for speaker attribution, parses structured JSON response (wins, insights, opportunities, actions), resolves speaker names to user IDs, handles code fence stripping, 120s timeout
- [x] includes/admin/class-admin-archivist.php — Full admin CRUD: CircleUp list page with status/item counts, Add Meeting form (manual transcript paste), Edit/Review page with curated summary editor, attendee checkboxes, per-item approve/reject dropdowns, collapsible transcript viewer, "Run AI Extraction" and "Publish & Email" action buttons
- [x] templates/emails/circleup_summary.php — "What We Won / Learned / Next" email with stat cards (wins, insights, actions counts), curated summary, portal link
- [x] WP-Cron: daily AI extraction for new unprocessed transcripts
- [x] Updated PROGRESS.md

### Risks / Notes

- Claude API key required: CBNEXUS_CLAUDE_API_KEY in wp-config.php (never in DB per SECURITY.md)
- Long transcripts truncated at 100k chars before sending to API
- AI extraction replaces existing items on re-run; Archivist should approve before re-extracting
- Summary email sends to all active members on publish

---

## Completed Iteration: ITER-0012 (CircleUp + Fireflies Integration)

### Goals

- Database foundation for CircleUp meetings, attendees, and extracted items
- Fireflies.ai webhook to receive transcripts automatically

### Deliverables

- [x] Migration 009: cb_circleup_meetings table (meeting_date, title, fireflies_id, full_transcript, ai_summary, curated_summary, duration, recording_url, status, published_by/at)
- [x] Migration 010: cb_circleup_attendees table (circleup_meeting_id, member_id, attendance_status)
- [x] Migration 011: cb_circleup_items table (circleup_meeting_id, item_type, content, speaker_id, assigned_to, due_date, status)
- [x] includes/circleup/class-circleup-repository.php — Full CRUD for meetings (including by fireflies_id), attendees (with display_name join), items (bulk insert, typed queries, member action items)
- [x] includes/circleup/class-fireflies-webhook.php — REST endpoint at /wp-json/cbnexus/v1/fireflies-webhook, secret validation (Bearer token or query param), flexible payload parsing for multiple Fireflies shapes, duplicate detection, auto-attendee matching by name search
- [x] Updated autoloader, migration runner, main bootstrap

### Risks / Notes

- Fireflies webhook requires CBNEXUS_FIREFLIES_SECRET in wp-config.php (rejects all requests if not configured)
- Webhook handles multiple payload formats (sentences array, plain text, nested transcript object)
- Attendee matching is best-effort by name search; manual correction via Archivist UI

---

## Completed Iteration: ITER-0011 (Automated Suggestion Generation)

### Goals

- Monthly automated matching cycle with email-based suggestion delivery
- One-click accept/decline from email via tokenized URLs
- Follow-up reminders for non-responses
- Admin trigger and cycle status dashboard

### Deliverables

- [x] includes/matching/class-suggestion-generator.php — Run cycle (create suggested meetings + send emails), cron callback, token-based accept/decline, follow-up reminders for unresponsive suggestions, admin trigger with confirmation, cycle stats
- [x] templates/emails/suggestion_match.php — Monthly match email with member card, accept/decline buttons via tokenized URLs
- [x] templates/emails/suggestion_reminder.php — Follow-up reminder for non-responses
- [x] Admin matching page updated: "Run Suggestion Cycle" button, cycle status table (last run, acceptance rates)
- [x] WP-Cron: monthly suggestion cycle + weekly follow-up reminders
- [x] Token cleanup: auto-expire after 14 days

### Risks / Notes

- Monthly/weekly cron schedules may need the WP Cron Schedules filter for custom intervals
- Tokens stored in wp_options; fine for small groups, consider dedicated table at scale
- Greedy selection ensures each member appears at most once per cycle

---

## Completed Iteration: ITER-0010 (Matching Rules Engine)

### Goals

- Configurable weighted scoring algorithm for intelligent member matching
- 10 rules covering meeting history, diversity, needs alignment, and more
- Admin UI for rule configuration with dry-run preview

### Deliverables

- [x] Migration 008: cb_matching_rules table, seeded with 10 default rules
- [x] includes/matching/class-matching-rules.php — 10 individual rule implementations (meeting_history, industry_diversity, expertise_complement, needs_alignment, new_member_priority, tenure_balance, meeting_frequency, response_rate, admin_boost, recency_penalty)
- [x] includes/matching/class-matching-engine.php — Context builder (pair history, meeting counts, response rates), all-pairs scoring, greedy selection, dry-run mode
- [x] includes/admin/class-admin-matching.php — Rule config table (enable/disable, adjust weights), dry-run preview with score breakdowns
- [x] Updated autoloader, migration runner, main bootstrap

### Risks / Notes

- All-pairs scoring is O(n²); fine for groups under ~200 members
- Active meeting pair check now uses batched single query instead of N² individual queries
- Rules return 0.0–1.0, multiplied by weight (can be negative for penalties)
- Admin boost rule uses config_json for specific pair overrides

---

## Completed Iteration: ITER-0009 (Manual 1:1 Requests & Meeting UI)

### Goals

- Members can request, respond to, and track 1:1 meetings
- Post-meeting notes capture with structured form
- Email notifications throughout the meeting lifecycle

### Deliverables

- [x] includes/public/class-portal-meetings.php — Portal meetings page with sections: Action Required, Submit Notes, Upcoming, Awaiting Response, History
- [x] assets/js/meetings.js — AJAX handlers for request, accept, decline, schedule, complete, cancel, submit notes
- [x] Wired "Request 1:1" button on directory profiles (was disabled placeholder)
- [x] 6 email templates: request received/sent, accepted, declined, notes request, reminder
- [x] Meeting reminder cron (daily, 24h before scheduled meetings)
- [x] Portal router: meetings section now renders CBNexus_Portal_Meetings::render
- [x] Meetings CSS added to portal.css
- [x] Updated PROGRESS.md

### Risks / Notes

- Scheduling uses datetime-local input (browser native); timezone is user's local
- Notes auto-close meeting when both participants have submitted
- Reminder cron runs daily; meetings scheduled with <24h notice may miss reminder

---

## Completed Iteration: ITER-0008 (Meetings Data Model & Core Workflow)

### Goals

- Database foundation and state machine for the 1:1 meeting lifecycle
- Validation rules: no self-meetings, no duplicate active meetings

### Deliverables

- [x] Migration 005: cb_meetings table (member_a_id, member_b_id, status, source, score, timestamps)
- [x] Migration 006: cb_meeting_notes table (meeting_id, author_id, wins, insights, action_items, rating)
- [x] Migration 007: cb_meeting_responses table (meeting_id, responder_id, response, message)
- [x] includes/meetings/class-meeting-repository.php — Full SQL operations: CRUD, queries by member/status, notes upsert, responses, upcoming scheduled
- [x] includes/meetings/class-meeting-service.php — State machine: suggested→pending→accepted→scheduled→completed→closed, plus declined/cancelled paths
- [x] Updated autoloader, migration runner
- [x] Updated PROGRESS.md

---

## Completed Iteration: ITER-0007 (Member Directory)

### Goals

- Searchable, filterable member directory with grid/list views
- Individual member profile pages with contact and networking info
- AJAX-powered filtering for responsive experience

### Deliverables

- [x] includes/public/class-directory.php — Directory rendering, AJAX filter handler, member cards, individual profile pages
- [x] assets/js/directory.js — AJAX search (350ms debounce), industry/status filters, grid/list view toggle
- [x] assets/css/portal.css — Directory grid/list layouts, member cards, avatars, tags, profile page, contact list, responsive
- [x] Portal router wired: directory section now renders CBNexus_Directory::render
- [x] "Request 1:1" button on profiles (disabled placeholder, wired in ITER-0009)
- [x] Updated autoloader, main plugin bootstrap
- [x] Updated PROGRESS.md

### Risks / Notes

- Industry filter is client-side post-fetch; scales fine for groups under ~200 members
- "Request 1:1" button is disabled with tooltip — will be enabled in ITER-0009
- Profile photos use URL-based approach (no file upload yet)

---

## Completed Iteration: ITER-0006 (Portal Shell & Profile Edit)

### Goals

- Authenticated member-facing portal with profile self-management
- Shortcode-based page routing with access control
- Responsive CSS for mobile-friendly portal

### Deliverables

- [x] includes/public/class-portal-router.php — Shortcode [cbnexus_portal], section routing, access control, nav, dashboard placeholder
- [x] includes/public/class-portal-profile.php — Member self-service profile edit with nonce verification
- [x] assets/css/portal.css — Responsive portal layout, cards, forms, nav, buttons, mobile breakpoints
- [x] Non-members redirected away; non-logged-in users sent to login
- [x] Updated autoloader and main plugin bootstrap
- [x] Updated PROGRESS.md

### Risks / Notes

- Portal requires a WordPress page containing [cbnexus_portal] shortcode
- Dashboard, Directory, Meetings sections are placeholders until their iterations
- Email field is disabled on profile form (admin-only change)

---

## Completed Iteration: ITER-0005 (Admin Member Management UI)

### Goals

- Admin interface for creating and managing members
- Centralized email service with template support
- Welcome email on member creation

### Deliverables

- [x] Migration 004: Create cb_email_log table
- [x] includes/admin/class-admin-members.php — Member list with status filters, search, bulk actions
- [x] includes/admin/class-admin-member-form.php — Add/edit form with all 17 fields in grouped sections
- [x] includes/emails/class-email-service.php — Centralized sender with template support, HTML layout, db logging
- [x] templates/emails/welcome_member.php — Branded welcome email with password reset link
- [x] Nonce verification + capability checks on all admin actions

---

## Completed Iteration: ITER-0004 (Member Data Model & Roles)

### Goals

- Define the member data foundation that all features depend on
- Create custom roles with capability matrix
- Build service/repository layer for member CRUD operations

### Deliverables

- [x] Migration 002: Custom WordPress roles (cb_super_admin, cb_admin, cb_member) with 25+ capabilities
- [x] Migration 003: Member meta schema (17 keys) + industry taxonomy stored in wp_options
- [x] includes/members/class-member-repository.php — CRUD, search, status, count operations via wp_usermeta
- [x] includes/members/class-member-service.php — Validation, creation, update, status transitions (active/inactive/alumni)
- [x] Updated migration runner with 002 + 003 entries
- [x] Updated autoloader with Member_Repository + Member_Service
- [x] Updated PROGRESS.md

### Risks / Notes

- Roles are removed and re-added on activation to allow capability updates
- Industry taxonomy stored in wp_options; editable via future admin settings
- Tags fields (expertise, looking_for, can_help_with) stored as JSON in usermeta

---

## Completed Iteration: ITER-0003 (Architecture Consolidation)

### Goals

- Resolve dual-architecture issue (core/ vs includes/)
- Establish clean foundation for all feature development

### Deliverables

- [x] Removed orphaned core/ directory (Plugin.php, Logger.php, Migrator.php)
- [x] Removed orphaned modules/Modules.php and migrations/0001_init.php
- [x] Created includes/class-autoloader.php (class-map autoloader, replaces manual requires)
- [x] Created includes/admin/class-admin-logs.php (WP admin page: log viewer with level/date filtering)
- [x] Added PHP 7.4+ / WP 5.9+ compatibility checks with admin notice + activation guard
- [x] Fixed CBNexus_Log_Retention::warning() call signature (3 args → 2)
- [x] Fixed CBNexus_Logger user_id null → 0 cast (conditional format array)
- [x] Updated circleblast-nexus.php to use autoloader, bumped version to 0.2.0
- [x] Updated PROGRESS.md

### Risks / Notes

- No external references to core/ were found; safe to remove
- Autoloader supports runtime class-map additions via CBNexus_Autoloader::add()

---

## Completed Iteration: ITER-0002 (Persistence Foundations)

### Goals

- Introduce first real migration with no behavioral breakage
- Enable durable internal logging for diagnostics and support

### Deliverables

- [x] Migration `001_create_log_table` (creates `{$wpdb->prefix}cbnexus_log`)
- [x] Activation-only migration execution
- [x] DB-backed logging with guaranteed fallback to baseline behavior

### Risks / Notes

- No runtime or upgrade-triggered migrations
- No admin UI or capability changes

---

## Completed Iteration: ITER-0001 (Plugin Skeleton)

Delivered:
- Plugin bootstrap
- Activation/deactivation hooks
- Migration runner (activation-only)
- Base logging service (stub)

---

## Completed Iteration: ITER-0000 (Foundation)

Delivered:
- CONTRIBUTING.md
- ARCHITECTURE.md
- CODING_STANDARDS.md
- SECURITY.md
- MIGRATIONS.md
- PROGRESS.md

---

## Rules

- Every feature or fix **must update this file**
- Updates should be 1–3 lines per change
