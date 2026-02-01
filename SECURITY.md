# Security Standards

## Access Control

- Every action requires:  
  - Capability check  
  - Nonce verification (for forms/actions)

  ---

  ## Data Handling

- Validate all input  
- Escape all output  
  - esc\_html()  
  - esc\_attr()  
  - wp\_kses\_post()

  ---

  ## Secrets

- No secrets in code or database  
- Use wp-config.php or environment variables  
  ---

  ## Logging

- Never log:  
  - Tokens  
  - Passwords  
  - Full transcripts with PII  
- Redact emails and identifiers where possible  
  