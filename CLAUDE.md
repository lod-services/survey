# Survey Application - Claude Documentation

## Project Overview
This is a Symfony-based survey application with security-focused secret management.

## 🔐 Security - Secret Management

### Current Implementation
- **Environment Files**: Secrets are NOT stored in version-controlled environment files
- **Local Development**: Uses `.env.local` (git-ignored) for actual secrets
- **Template Files**: `.env.local.example` provides setup templates

### Secret Management Setup

#### For New Developers
1. Copy the template file:
   ```bash
   cp .env.local.example .env.local
   ```

2. Generate a secure 64-character secret:
   ```bash
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```

3. Update `.env.local` with your generated secret:
   ```bash
   APP_SECRET=your_generated_64_character_secret_here
   ```

4. Set secure file permissions:
   ```bash
   chmod 600 .env.local
   ```

#### For Testing Environment
1. Copy the test template:
   ```bash
   cp .env.test.local.example .env.test.local
   ```

2. Generate a separate test secret and update the file.

3. Set secure file permissions:
   ```bash
   chmod 600 .env.test.local
   ```

### Security Requirements
- **Secret Length**: Must be exactly 64 characters (256-bit entropy)  
- **Format**: Hexadecimal characters only
- **Storage**: Never commit actual secrets to version control
- **Generation**: Use cryptographically secure random generation
- **Permissions**: Set restrictive file permissions (600) for secret files

### File Structure
```
.env                    # Base environment (committed, no secrets)
.env.local              # Local secrets (git-ignored) 
.env.local.example      # Template for developers (committed)
.env.dev                # Development config (committed, no secrets)
.env.test               # Test config (committed, no secrets)
.env.test.local         # Local test secrets (git-ignored)
.env.test.local.example # Test template (committed)
```

## 🧪 Testing

### CSRF Verification
Run the built-in CSRF verification script:
```bash
php csrf_verification.php
```

Expected output should show:
- ✅ APP_SECRET is properly configured (length: 64)
- ✅ CSRF protection is enabled
- ✅ Session support is configured

## 📋 Build Commands

### Frontend
```bash
npm install         # Install dependencies
npm run dev        # Development build
npm run watch      # Watch for changes
npm run build      # Production build
npm run lint       # Lint JavaScript
npm run lint:fix   # Fix linting issues
```

### PHP
```bash
composer install   # Install dependencies
```

## 🚨 Critical Security Notes

1. **NEVER** commit files containing actual secrets
2. **ALWAYS** use the template files to set up local environments  
3. **REGENERATE** secrets if you suspect they've been compromised
4. **VALIDATE** secret length (must be 64 characters)

## 🛠 Development Workflow

When working on this project:
1. Ensure `.env.local` exists with proper secrets
2. Run `php csrf_verification.php` to verify security setup
3. Use provided npm scripts for frontend builds
4. Follow security best practices for any new secret requirements

---

**Last Updated**: Security secret management implementation (Issue #172)
**Security Level**: High - Contains cryptographic secrets and CSRF protection