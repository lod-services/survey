# Claude Knowledge Base - Survey Application

## Security - Environment Variables & Secrets

### Critical: APP_SECRET Management

The application uses `APP_SECRET` for CSRF protection, session security, and cryptographic operations via Symfony framework.

**Secure Setup Process:**
1. Copy `.env.dev.example` to `.env.local`
2. Generate secure secret: `openssl rand -hex 32`
3. Set `APP_SECRET=<generated-secret>` in `.env.local`

**NEVER commit real secrets to version control:**
- `.env.local` is git-ignored and safe for real secrets
- `.env.dev` is a template with placeholder values
- All environment files (`.env*`) are protected by `.gitignore`

**For New Developers:**
1. Clone repository
2. Copy `.env.dev.example` to `.env.local`  
3. Generate new APP_SECRET with `openssl rand -hex 32`
4. Set the generated secret in `.env.local`

**Testing CSRF Protection:**
The framework uses APP_SECRET for CSRF token generation. After secret changes, verify forms with CSRF protection still work correctly.

## Project Commands

(Add other frequently used commands here as they're discovered)