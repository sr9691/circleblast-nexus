# Database Migrations

## Principles

- Migrations are append-only
- Never modify an existing migration
- Schema version tracked separately from plugin version (via `cbnexus_schema_version` option)

---

## Structure

```
includes/migrations/
├── class-migration-runner.php          # Registry-based runner with halt-on-failure
└── versions/
    ├── 001-create-log-table.php        # Plugin diagnostic log
    ├── 002-register-roles.php          # cb_super_admin, cb_admin, cb_member + 25 capabilities
    ├── 003-register-member-meta.php    # 17 profile meta keys + industry taxonomy
    ├── 004-create-email-log.php        # Email send tracking
    ├── 005-create-meetings.php         # 1:1 meeting lifecycle
    ├── 006-create-meeting-notes.php    # Post-meeting structured notes
    ├── 007-create-meeting-responses.php # Accept/decline/reschedule
    ├── 008-create-matching-rules.php   # 10 weighted rules (seeded with defaults)
    ├── 009-create-circleup-meetings.php # Group meeting records + transcripts
    ├── 010-create-circleup-attendees.php # CircleUp attendance
    ├── 011-create-circleup-items.php   # Extracted wins/insights/opportunities/actions
    ├── 012-create-analytics-snapshots.php # Historical metric tracking
    ├── 013-create-candidates.php       # Recruitment pipeline
    ├── 014-create-tokens.php           # Token-based email actions
    ├── 015-create-events.php           # Group events
    ├── 016-create-event-rsvps.php      # Event RSVPs
    ├── 017-create-recruitment-categories.php # Professional role definitions
    ├── 018-add-guest-cost.php          # Event guest cost field
    ├── 019-add-member-categories.php   # Member-to-category linking + target_count
    ├── 020-seed-recruitment-categories.php # Default recruitment categories
    ├── 021-create-feedback.php         # Member feedback submissions
    └── 022-matching-fixes.php          # Matching engine schema adjustments
```

---

## Rules

- One migration per logical change
- Migrations must be idempotent (safe to re-run)
- Destructive changes (DROP, ALTER column removal) require explicit approval
- Table creation uses `dbDelta()` with post-creation validation
- Role migrations remove and re-add roles to allow capability updates

---

## Execution

- Run automatically on plugin activation via `CBNexus_Migration_Runner`
- Sequential execution with halt-on-failure
- State tracked via `cbnexus_schema_version` option
- Each migration has file/class/method metadata for logging
- Safe to re-run — already-executed migrations are skipped

---

## Conventions

- File naming: `NNN-description.php` (zero-padded three digits)
- Table prefix: `{$wpdb->prefix}cb_` (except `cbnexus_log` which predates the convention)
- All tables use `utf8mb4_unicode_ci` charset
- Index names follow: `idx_{table}_{column}` pattern
- Foreign keys are logical only (no FK constraints) for WordPress compatibility
