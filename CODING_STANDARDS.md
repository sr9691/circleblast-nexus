# Coding Standards

## File Size Policy

- Hard limit: **750 lines per file**  
- If exceeded, code must be split before merge

  ### Splitting Patterns

- Service → Service \+ Rules \+ Formatter  
- Admin Page → Page \+ Table \+ Actions  
- Webhook → Handler \+ Validator \+ Mapper  
  ---

  ## Modification Rules

- Do not recreate entire files/functions unless \>50% change  
- Use line-numbered Before/After diffs  
- Never repeat unchanged code  
  ---

  ## Style

- WordPress Coding Standards (PHPCS)  
- Explicit types where possible  
- Early returns preferred over nested conditionals  
  ---

  ## Backward Compatibility

- Do not change public method signatures  
- Do not rename hooks or filters  
- Database changes require migrations  
  