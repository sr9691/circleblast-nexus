# CircleBlast Nexus

A modular WordPress plugin that powers private member networks with secure onboarding, intelligent 1:1 matching, meeting workflows, knowledge capture, and analytics — built with strict change control, incremental delivery, and long-term maintainability in mind.

## Features

- **Member Management** — Admin-created profiles with 17 fields, custom roles (Super Admin / Admin / Member), status lifecycle (active / inactive / alumni)
- **Member Directory** — Searchable, filterable grid/list views with individual profile pages and one-click 1:1 meeting requests
- **1:1 Meeting System** — Full lifecycle from request through notes capture, with email notifications at every stage
- **Intelligent Matching** — 10 configurable weighted rules (meeting history, expertise complement, needs alignment, etc.) with monthly automated suggestion cycles
- **CircleUp Archive** — Group meeting knowledge base with Fireflies.ai transcript integration, AI-powered extraction (wins, insights, opportunities, actions), and Archivist review workflow
- **Events** — Calendar with RSVP tracking, member-submitted events with admin approval, and automated digest emails
- **Recruitment Pipeline** — Category-based coverage tracking, candidate pipeline (referral → invited → visited → decision), referral prompts throughout the portal and emails
- **Analytics** — Personal dashboards, club-wide stats with presentation mode, engagement scoring, churn risk flags, and CSV export
- **Email System** — 27 customizable templates with token-based no-login actions, color scheme integration, and recruitment referral footer injection
- **Feedback System** — Member feedback submissions with admin triage workflow

## Requirements

- WordPress 5.9+
- PHP 7.4+

## Installation

1. Upload the `circleblast-nexus` folder to `wp-content/plugins/`
2. Activate the plugin — migrations run automatically
3. Create a WordPress page containing the `[cbnexus_portal]` shortcode
4. Configure in the portal's admin Settings tab

## Configuration

Define in `wp-config.php`:

```php
define('CBNEXUS_CLAUDE_API_KEY', 'your-key');      // Required for AI extraction
define('CBNEXUS_FIREFLIES_SECRET', 'your-secret');  // Required for webhook
```

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) — System structure, layering, database schema
- [CODING_STANDARDS.md](CODING_STANDARDS.md) — Style rules, patterns, conventions
- [SECURITY.md](SECURITY.md) — Access control, CSRF, secrets, token system
- [MIGRATIONS.md](MIGRATIONS.md) — Database migration system and history
- [PROGRESS.md](PROGRESS.md) — Iteration-by-iteration delivery log
