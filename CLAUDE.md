# Survey Application - Development Guide

## Project Overview
This is a Symfony 7.3 survey application with comprehensive security measures and proper secret management.

## 🔐 Security & Secret Management

### Environment Files
**CRITICAL**: Never commit files containing secrets to version control!

- **`.env`** - Base configuration with placeholder values (safe to commit)
- **`.env.dev`** - Development overrides (⚠️ NEVER COMMIT)
- **`.env.test`** - Test environment overrides (⚠️ NEVER COMMIT) 
- **`.env.prod`** - Production overrides (⚠️ NEVER COMMIT)
- **`.env.local`** - Local developer secrets (⚠️ NEVER COMMIT - git-ignored)

### Setting Up Your Development Environment

1. **Copy the example files:**
   ```bash
   cp .env.dev.example .env.dev
   cp .env.test.example .env.test
   ```

2. **Generate a secure APP_SECRET:**
   ```bash
   # Generate 64-character secure secret
   openssl rand -hex 32
   
   # Or use online generator: https://www.random.org/strings/
   ```

3. **Configure your environment files:**
   - Replace placeholder values with real secrets
   - Use different secrets for dev/test/prod environments
   - Never reuse the exposed secret: `6b1f17348df0767f83847020fb8c3119`

### APP_SECRET Security Requirements
- **Minimum 32 characters** (64+ recommended for production)
- **Use cryptographically secure random generation**
- **Different secrets for each environment**
- **Rotate quarterly** or when compromised

### Git History Cleanup (Already Completed)
The exposed secrets have been completely purged from git history using `git filter-branch`. Previous commits containing `6b1f17348df0767f83847020fb8c3119` and `$ecretf0rt3st` have been sanitized.

## 🛡️ Pre-commit Security Hooks

### Installation
```bash
# Install pre-commit framework
pip install pre-commit

# Install detect-secrets for secret scanning
pip install detect-secrets

# Install hooks in your repository
pre-commit install
```

### Security Checks
The pre-commit hooks perform:
- **Secret detection** using detect-secrets
- **Environment file protection** (prevents committing .env.dev/.env.test)
- **APP_SECRET pattern detection** (prevents hardcoded secrets)
- **Large file protection** (10MB limit)
- **Merge conflict detection**

### Bypassing Hooks (Emergency Only)
```bash
# ONLY for genuine false positives
git commit --no-verify -m "emergency fix"
```

## 🔄 Common Commands

### Development
```bash
# Start development server
symfony serve

# Run tests
php bin/phpunit

# Check security
symfony security:check
```

### Security Validation
```bash
# Test CSRF protection
php csrf_verification.php

# Audit dependencies
composer audit
npm audit
```

## 📋 Security Incident Response

### If Secrets Are Exposed
1. **Immediately rotate all affected secrets**
2. **Remove from current files** (replace with placeholders)
3. **Update .gitignore** to prevent future exposure
4. **Clean git history** using git filter-branch
5. **Force push cleaned history** (coordinate with team)
6. **All team members must re-clone** the repository
7. **Update deployment configurations** with new secrets

### Recovery Commands
```bash
# Remove secrets from tracking
git rm --cached .env.dev .env.test

# Clean git history (DANGER - coordinates with team first)
git filter-branch --force --index-filter \\
  'git rm --cached --ignore-unmatch .env.dev .env.test' \\
  --prune-empty --tag-name-filter cat -- --all
```

## 🏗️ Architecture Notes

### Symfony Configuration
- **Framework**: Symfony 7.3 with MicroKernel
- **Environment**: Development/Test/Production configurations
- **Security**: CSRF protection, session security, input validation
- **Asset Management**: Webpack Encore with Bootstrap 5 + Stimulus

### File Structure
```
├── .env                    # Base configuration (safe)
├── .env.dev.example       # Development template
├── .env.test.example      # Test template  
├── .env.local             # Your secrets (git-ignored)
├── .pre-commit-config.yaml # Security hooks
├── .secrets.baseline      # Secret scanning baseline
├── src/                   # Symfony application code
└── tests/                 # PHPUnit tests
```

## 📝 Development Workflow

1. **Never commit environment files with secrets**
2. **Always use example templates for setup**
3. **Run pre-commit hooks before every commit**
4. **Rotate secrets regularly**
5. **Test security configurations**

## 🚨 Security Alerts

- **Exposed Secret**: `6b1f17348df0767f83847020fb8c3119` (NEVER REUSE)
- **Weak Test Secret**: `$ecretf0rt3st` (NEVER REUSE)
- Both secrets have been purged from git history but should never be reused

## 📚 Additional Resources

- [Symfony Secrets Management](https://symfony.com/doc/current/configuration/secrets.html)
- [OWASP Secret Management Guide](https://owasp.org/www-community/vulnerabilities/Use_of_hard-coded_password)
- [Pre-commit Framework](https://pre-commit.com/)
- [Detect-secrets Tool](https://github.com/Yelp/detect-secrets)