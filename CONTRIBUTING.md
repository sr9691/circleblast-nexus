# Contributing to CircleBlast Nexus

## Core Principles

- Incremental change over rewrites  
- Backward compatibility by default  
- Explicit approval for breaking or destructive changes  
- Clarity over cleverness  
  ---

  ## Change Rules (Hard Requirements)

  ### 1\. Incremental Modifications Only

  Do NOT recreate an entire file or function unless:  
- It is a new file/function, OR  
- More than 50% of the logic must change, OR  
- A full rewrite is explicitly requested  
  **Required format for changes:**  
- File path  
- Line numbers  
- Before / After blocks (only changed lines)  
  ---

  ### 2\. No Silent Removal

  Existing functionality must not be removed or disabled without explicit prior consent.  
  ---

  ### 3\. File Size Limit

- Hard cap: **750 lines per file**  
- Soft cap: \~400–500 lines (plan a split)  
  ---

  ### 4\. Progress Tracking (Mandatory)

  **No feature may be merged without an update to `PROGRESS.md`.**  
  Minimum update:  
- What changed  
- Status  
- Any new risk or dependency  
  ---

  ## Pull Request Checklist

- [ ] No unnecessary rewrites  
- [ ] No functionality removed without approval  
- [ ] File size limits respected  
- [ ] PROGRESS.md updated  
- [ ] Security rules followed  
      