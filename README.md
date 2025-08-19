# Survey Application

A Symfony-based survey application with enhanced security features and CSRF protection.

## 🚀 Quick Start

### Prerequisites

- PHP 8.1 or higher
- Composer
- Node.js and npm (for frontend assets)
- Web server (Apache/Nginx) or Symfony CLI

### Environment Setup

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd survey
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment:**
   ```bash
   # Copy the environment template
   cp .env.dist .env
   
   # Edit .env and configure your settings
   # IMPORTANT: Generate a secure APP_SECRET (see instructions below)
   ```

4. **Generate APP_SECRET:**
   Choose one of these methods to generate a secure secret:
   
   ```bash
   # Method 1: Using PHP (recommended)
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   
   # Method 2: Using OpenSSL
   openssl rand -hex 32
   
   # Method 3: Using Symfony Console (if available)
   php bin/console secrets:generate-keys
   ```
   
   Copy the generated secret and replace `REPLACE_WITH_STRONG_32_PLUS_CHARACTER_SECRET` in your `.env` file.

5. **Build frontend assets:**
   ```bash
   npm run build
   ```

6. **Start the development server:**
   ```bash
   # Using Symfony CLI (recommended)
   symfony server:start
   
   # Or using PHP built-in server
   php -S localhost:8000 -t public/
   ```

7. **Verify setup:**
   ```bash
   # Run the CSRF verification script
   php csrf_verification.php
   ```

Your application should now be running at `http://localhost:8000`

## 🛡️ Security Configuration

### Environment Variables

This application uses environment variables for configuration. **Never commit `.env` files to version control.**

#### Required Variables

- **`APP_SECRET`**: Critical for security (CSRF tokens, sessions, encryption)
  - Minimum 32 characters (64+ recommended for production)
  - Use cryptographically secure random string
  - Generate unique secrets for each environment

- **`APP_ENV`**: Application environment (`dev`, `prod`, `test`)
- **`APP_DEBUG`**: Debug mode (`true`/`false`, always `false` in production)

#### Environment File Precedence

Symfony loads environment variables in this order (highest priority first):

1. **System environment variables** (e.g., `export APP_SECRET=...`)
2. **`.env.local`** (local overrides, ignored by git)
3. **`.env.$APP_ENV.local`** (environment-specific local overrides)
4. **`.env.$APP_ENV`** (environment-specific defaults)
5. **`.env`** (general defaults)

### Security Best Practices

#### Development Environment

- ✅ Use `.env` for local development configuration
- ✅ Use `.env.local` for personal overrides (ignored by git)
- ✅ Generate strong, unique secrets for each developer
- ✅ Keep `APP_DEBUG=true` for development
- ✅ Use `APP_ENV=dev` for development

#### Production Environment

- ✅ Use system environment variables or Symfony secrets management
- ✅ Generate production-specific secrets (minimum 64 characters)
- ✅ Set `APP_DEBUG=false` (critical security requirement)
- ✅ Set `APP_ENV=prod` for production
- ✅ Implement secret rotation procedures
- ✅ Use HTTPS for all communication
- ✅ Consider using [Symfony's secrets management](https://symfony.com/doc/current/configuration/secrets.html)

### Git Security

The following files are automatically ignored by git (see `.gitignore`):

- `.env` - Main environment file
- `.env.local` - Local overrides
- `.env.*.local` - Environment-specific local files

**⚠️ NEVER commit environment files containing real secrets to version control.**

## 🔧 Development

### Available Commands

```bash
# Install dependencies
composer install
npm install

# Build assets for development
npm run dev

# Build assets for production
npm run build

# Watch assets for changes
npm run watch

# Run tests
php bin/phpunit

# Run static analysis (if configured)
vendor/bin/phpstan analyse

# Clear cache
php bin/console cache:clear
```

### Testing CSRF Protection

1. Visit `/test-form` to access the CSRF-protected test form
2. Submit the form with valid tokens (should succeed)
3. Test with invalid tokens (should fail with CSRF error)

Expected error message for invalid CSRF tokens:
```
"The CSRF token is invalid. Please try to resubmit the form."
```

### Verifying Configuration

Run the CSRF verification script to check your setup:

```bash
php csrf_verification.php
```

This script will verify:
- ✅ APP_SECRET is properly configured
- ✅ CSRF protection is enabled
- ✅ Session support is available
- ✅ Test form components exist

## 🚨 Security Incident Response

### If Secrets Are Compromised

**Immediate Response:**

1. **Generate new secrets immediately:**
   ```bash
   # Generate new APP_SECRET
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```

2. **Update all environments:**
   - Development: Update `.env` files
   - Production: Update system environment variables
   - Staging: Update configuration

3. **Restart application:**
   ```bash
   # Clear cache and restart
   php bin/console cache:clear --env=prod
   # Restart web server/application
   ```

4. **Invalidate user sessions** (all users will need to log in again)

**Investigation:**

1. **Check git history for committed secrets:**
   ```bash
   # Search for APP_SECRET in git history
   git log --grep="APP_SECRET" --oneline
   git log -S "APP_SECRET" --oneline
   ```

2. **Audit access logs** for suspicious activity

**Remediation:**

If secrets were committed to git:

1. **Remove from git history:**
   ```bash
   # Use git filter-branch (destructive - coordinate with team)
   git filter-branch --force --index-filter \
     'git rm --cached --ignore-unmatch .env*' \
     --prune-empty --tag-name-filter cat -- --all
   
   # Force push (requires team coordination)
   git push origin --force --all
   ```

2. **Team notification:**
   - Notify all team members immediately
   - Recommend fresh repository clones
   - Share new secret generation procedures

### Secret Rotation Schedule

**Recommended rotation schedule:**
- **Development**: Quarterly or when team members change
- **Production**: Quarterly or immediately if suspected compromise
- **Staging**: Monthly or when production rotates

**Rotation procedure:**
1. Generate new secrets using secure methods
2. Update configuration in target environment
3. Test application functionality
4. Update documentation and team
5. Schedule old secret deactivation

## 📚 Additional Resources

### Symfony Security

- [Symfony Security Documentation](https://symfony.com/doc/current/security.html)
- [CSRF Protection](https://symfony.com/doc/current/security/csrf.html)
- [Secrets Management](https://symfony.com/doc/current/configuration/secrets.html)
- [Environment Variables](https://symfony.com/doc/current/configuration.html#env-var-processors)

### Security Best Practices

- [OWASP Security Guidelines](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://phpsecurity.readthedocs.io/)
- [Symfony Security Best Practices](https://symfony.com/doc/current/best_practices/security.html)

## 🐛 Troubleshooting

### Common Issues

**Application won't start - "APP_SECRET not found"**
```bash
# Solution: Copy and configure environment file
cp .env.dist .env
# Edit .env and add a secure APP_SECRET
```

**CSRF token errors**
```bash
# Solution: Verify APP_SECRET is configured
php csrf_verification.php
```

**Environment variables not loading**
```bash
# Check file permissions
ls -la .env*
# Verify file syntax (no spaces around =)
cat .env
```

### Getting Help

1. **Check the verification script:**
   ```bash
   php csrf_verification.php
   ```

2. **Verify environment configuration:**
   ```bash
   php bin/console debug:container --env-vars
   ```

3. **Check application logs:**
   ```bash
   tail -f var/log/dev.log
   ```

4. **Clear cache:**
   ```bash
   php bin/console cache:clear
   ```

---

## 📄 License

[Add your license information here]

## 🤝 Contributing

[Add contributing guidelines here]