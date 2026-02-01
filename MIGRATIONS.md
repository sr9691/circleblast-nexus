# Database Migrations

## Principles

- Migrations are append-only  
- Never modify an existing migration  
- Schema version tracked separately from plugin version  
  ---

  ## Structure

  migrations/  
  ├── 0001\_create\_members\_table.php  
  ├── 0002\_create\_meetings\_table.php  
    
  ---

  ## Rules

- One migration per logical change  
- Migrations must be idempotent  
- Destructive changes require explicit approval  
  ---

  ## Execution

- Run automatically on plugin activation  
- Safe to re-run (no duplicate effects)  
  