# Security Guide for Survey Application

## Environment Configuration Security

### Critical Security Requirements

#### 1. APP_SECRET Security
The `APP_SECRET` is the most critical security component in your Symfony application. It is used for:
- CSRF token generation and validation
- Session encryption and security
- Password hashing operations
- Cryptographic operations throughout the framework

**Production Requirements:**
- **Minimum 64 characters** (32 minimum, 64+ recommended)
- **Cryptographically secure random generation**
- **Unique per environment** (dev/test/staging/prod must all be different)
- **Never committed to version control**
- **Rotated periodically** (recommended: quarterly)

#### 2. Secure Secret Generation

**Method 1 - OpenSSL (Recommended):**
```bash
# Generate 64-character hex secret
openssl rand -hex 32

# Generate base64 secret (for special character support)
openssl rand -base64 48 | tr -d "=+/" | cut -c1-64
```

**Method 2 - System Random:**
```bash
# Linux/macOS
head -c32 /dev/urandom | base64 | tr -d "=+/" | cut -c1-64

# Alternative with xxd
head -c32 /dev/urandom | xxd -p -c 32
```

**Method 3 - PHP:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Production Deployment Checklist

#### Environment Setup
- [ ] **Generate unique 64+ character APP_SECRET for production**
- [ ] **Verify APP_SECRET is different from dev/test/staging**
- [ ] **Set APP_ENV=prod**
- [ ] **Set APP_DEBUG=false**
- [ ] **Use environment variables instead of .env files**
- [ ] **Ensure .env files are not deployed to production**

#### Security Hardening
- [ ] **Enable HTTPS only (no HTTP redirect)**
- [ ] **Configure proper session security settings**
- [ ] **Set secure cookie attributes**
- [ ] **Enable CSRF protection (already configured)**
- [ ] **Configure proper error handling (no debug info)**
- [ ] **Set up proper logging and monitoring**

#### Infrastructure Security
- [ ] **Use separate secrets per environment**
- [ ] **Implement secret rotation strategy**
- [ ] **Use container orchestration secrets (if applicable)**
- [ ] **Configure proper file permissions**
- [ ] **Disable directory listing**
- [ ] **Remove development tools and files**

### Environment Variable Management

#### Development Environment
```bash
# .env.dev (git-ignored)
APP_ENV=dev
APP_DEBUG=true
APP_SECRET=your_unique_development_secret_32_plus_chars
```

#### Test Environment  
```bash
# .env.test (git-ignored)
APP_ENV=test
APP_DEBUG=false
APP_SECRET=your_unique_testing_secret_32_plus_characters
SYMFONY_DEPRECATIONS_HELPER=999999
```

#### Production Environment
**Never use .env files in production!** Instead, set environment variables directly:

```bash
# Via environment variables (recommended)
export APP_ENV=prod
export APP_DEBUG=false
export APP_SECRET="your_secure_production_secret_64_plus_characters_here"

# Via container orchestration
# Docker Compose, Kubernetes secrets, etc.
```

### Security Best Practices

#### Secret Management
1. **Use dedicated secret management tools** for production:
   - Kubernetes Secrets
   - HashiCorp Vault  
   - AWS Secrets Manager
   - Azure Key Vault
   - Docker Secrets

2. **Consider Symfony Secrets Component** for production:
   ```bash
   # Generate secrets vault (when console is available)
   php bin/console secrets:generate-keys --env=prod
   php bin/console secrets:set APP_SECRET --env=prod
   ```

#### Monitoring and Auditing
1. **Log all authentication attempts**
2. **Monitor for configuration changes**
3. **Set up alerts for security events**
4. **Regular security audits of environment configuration**
5. **Automated vulnerability scanning**

#### Incident Response
1. **Secret Rotation Procedure:**
   - Generate new APP_SECRET
   - Update production environment
   - Restart application services
   - Invalidate all existing sessions
   - Monitor for issues

2. **Compromise Response:**
   - Immediately rotate all secrets
   - Audit access logs
   - Check for unauthorized access
   - Update security policies
   - Document incident

### Common Security Mistakes to Avoid

❌ **Never Do:**
- Commit actual secrets to version control
- Use the same secret across environments
- Use weak or predictable secrets
- Enable debug mode in production
- Use .env files in production
- Share secrets via insecure channels

✅ **Always Do:**
- Generate cryptographically secure secrets
- Use environment variables in production
- Implement proper secret rotation
- Monitor and audit configuration changes
- Follow the principle of least privilege
- Keep security documentation updated

### Emergency Procedures

#### Immediate Secret Rotation
```bash
# 1. Generate new secret
NEW_SECRET=$(openssl rand -hex 32)

# 2. Update environment variable
export APP_SECRET="$NEW_SECRET"

# 3. Restart application
# (specific to your deployment method)

# 4. Verify application functionality
curl -I https://your-domain.com/
```

#### Git History Cleanup (if secrets were committed)
```bash
# WARNING: This rewrites git history - coordinate with team
git filter-branch --force --index-filter \
  'git rm --cached --ignore-unmatch .env.dev .env.test' \
  --prune-empty --tag-name-filter cat -- --all

# Force push (dangerous - coordinate with team)
git push origin --force --all
```

### Compliance and Standards

This security configuration addresses:
- **OWASP Top 10 2021**
  - A02:2021 - Cryptographic Failures
  - A05:2021 - Security Misconfiguration
- **NIST Cybersecurity Framework**
- **ISO 27001 Information Security Standards**
- **Symfony Security Best Practices**

For questions or security concerns, contact the security team immediately.