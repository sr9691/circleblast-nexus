# CircleBlast Nexus – Architecture Overview

## High-Level Structure

This system is implemented as a modular WordPress plugin (v1.0.0, PHP 7.4+, WP 5.9+).

```
circleblast-nexus/
├── circleblast-nexus.php          # Bootstrap, activation/deactivation hooks
├── uninstall.php                  # Cleanup on plugin deletion
├── includes/
│   ├── class-autoloader.php       # Class-map autoloader (59 entries)
│   ├── class-color-scheme.php     # Theming: 3 presets + custom palettes
│   ├── admin/                     # WP-Admin pages (logs, members, matching, archivist, events, recruitment, analytics, email templates)
│   ├── api/                       # REST API endpoints (members, events ICS)
│   ├── circleup/                  # Fireflies webhook, AI extractor, CircleUp repository, summary parser
│   ├── club/                      # Club tips service, analytics
│   ├── emails/                    # Email service with template rendering, logging, referral prompt injection
│   ├── events/                    # Event repository + service
│   ├── feedback/                  # Member feedback service
│   ├── logging/                   # DB-backed logger + log retention cron
│   ├── matching/                  # 10-rule scoring engine, suggestion generator
│   ├── meetings/                  # Meeting repository + state machine service
│   ├── members/                   # Member repository + service (CRUD, validation, status)
│   ├── migrations/                # 22 sequential migrations (activation-only)
│   ├── public/                    # Portal pages: router, dashboard, directory, meetings, circleup, club, events, profile, help, referral form, feedback form
│   │   └── admin-tabs/            # In-portal admin sections (10 tabs)
│   ├── recruitment/               # Recruitment coverage service (member-to-category linking)
│   └── tokens/                    # Token service + router (email action links with CSRF protection)
├── templates/
│   └── emails/                    # 27 email templates (HTML with placeholder system)
├── assets/
│   ├── css/                       # 18 stylesheets (portal, admin, components)
│   ├── js/                        # 8 scripts (directory, meetings, circleup, events, etc.)
│   └── img/                       # Logos, icons
└── dev/                           # Development-only scripts (seeding, test data)
```

---

## Layering Rules

```
UI (Portal Pages / Admin Tabs)
    → Controllers / Handlers (Portal Router, Token Router, REST API)
        → Services (Business Logic, Validation, State Machines)
            → Repositories (SQL, wp_usermeta, wp_options)
                → Database (22 custom tables + WordPress core tables)
```

- UI never queries the database directly
- Services contain business logic and validation
- Repositories encapsulate all SQL via `$wpdb->prepare()`
- All classes use the `CBNexus_` prefix
- Autoloader (class-map) handles class loading; admin tabs also registered

---

## Custom Roles & Capabilities

| Role | Key Capabilities |
|------|-----------------|
| `cb_super_admin` | All capabilities including system settings, API keys, cron management |
| `cb_admin` | Member management, recruitment, matching, archivist, events, analytics |
| `cb_member` | Portal access, profile edit, directory, meetings, CircleUp, events |

25+ custom capabilities gating every action. All admin handlers verify nonces + capabilities.

---

## Database Schema

22 migrations creating these tables (all prefixed with `{$wpdb->prefix}cb_`):

| Table | Purpose |
|-------|---------|
| `cbnexus_log` | Plugin diagnostic logging |
| `cb_email_log` | Email send tracking |
| `cb_meetings` | 1:1 meeting lifecycle |
| `cb_meeting_notes` | Post-meeting structured notes |
| `cb_meeting_responses` | Accept/decline/reschedule |
| `cb_matching_rules` | 10 configurable weighted rules |
| `cb_circleup_meetings` | Group meeting records + transcripts |
| `cb_circleup_attendees` | CircleUp attendance |
| `cb_circleup_items` | Extracted wins/insights/opportunities/actions |
| `cb_analytics_snapshots` | Historical metric snapshots |
| `cb_candidates` | Recruitment pipeline |
| `cb_tokens` | Token-based email actions |
| `cb_events` | Group events |
| `cb_event_rsvps` | Event RSVPs |
| `cb_recruitment_categories` | Professional role definitions |
| `cb_feedback` | Member feedback submissions |

Member data stored in `wp_usermeta` with `cb_` prefixed keys (17 profile fields).

---

## Key Subsystems

### Token System
SHA-256 hashed tokens for no-login email actions (accept/decline meetings, submit notes, manage preferences). Single-use and multi-use variants with expiry. CSRF nonce verification on all POST forms.

### Email System
27 templates with block-based HTML rendering, placeholder replacement (`{{first_name}}`, `{{portal_url}}`, etc.), color scheme integration, optional recruitment referral footer injection. All sends logged to `cb_email_log`.

### Matching Engine
10 weighted rules (meeting history, industry diversity, expertise complement, needs alignment, new member priority, tenure balance, meeting frequency, response rate, admin boost, recency penalty). Greedy pair selection. Monthly automated cycles via WP-Cron. Batched active meeting checks for O(1) pair lookup.

### Recruitment Coverage
Links members to recruitment categories via `cb_member_categories` usermeta. Computed coverage status (covered/partial/gap) replaces manual toggles. Surfaces on dashboard, directory (ghost cards), club stats (scorecard), and email footers. Per-request caching to avoid redundant computation.

---

## Background Processing

- All heavy work via WP-Cron or async handlers
- Cron jobs must be idempotent
- Each run logged with correlation ID via `CBNexus_Logger`

### Registered Cron Jobs

| Hook | Frequency | Purpose |
|------|-----------|---------|
| `cbnexus_log_retention` | Daily | Purge logs older than 30 days |
| `cbnexus_meeting_reminders` | Daily | 24h-before meeting reminders |
| `cbnexus_suggestion_cycle` | Monthly | Generate automated 1:1 suggestions |
| `cbnexus_follow_up_reminders` | Weekly | Remind non-responsive suggestion recipients |
| `cbnexus_ai_extraction` | Daily | Process new CircleUp transcripts |
| `cbnexus_analytics_snapshot` | Nightly | Club-scope metric snapshots |
| `cbnexus_monthly_admin_report` | Monthly | Email admin analytics summary |
| `cbnexus_recruitment_blast` | Configurable | Recruitment needs email to members |
| `cbnexus_recruitment_rotation` | Monthly | Rotate focus recruitment categories |

---

## Naming & Conventions

- Class prefix: `CBNexus_`
- File naming: `class-{name}.php` (WordPress standard)
- Prefix everything: `cb_` (tables, options, cron, hooks, meta keys)
- Nonces: `_wpnonce` for forms (via `wp_nonce_field`), `_panonce` for URL actions (via `wp_nonce_url`)
- No global state unless explicitly intentional
