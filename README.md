# Survey Application

A Symfony-based survey application with security best practices.

## Installation & Setup

### Prerequisites
- PHP 8.0 or higher
- Composer
- Node.js and npm (for asset compilation)

### Initial Setup

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd survey
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install
   ```

3. **Security Configuration (REQUIRED)**:
   Generate a secure APP_SECRET and configure environment variables:
   
   ```bash
   # Generate a secure 64-character APP_SECRET
   openssl rand -hex 32
   ```
   
   Copy the generated secret and create `.env.local`:
   ```bash
   # .env.local (this file is git-ignored)
   APP_SECRET=your_generated_64_character_secret_here
   ```

4. Build assets:
   ```bash
   npm run build
   ```

5. Start the development server:
   ```bash
   symfony server:start
   # OR
   php -S localhost:8000 -t public/
   ```

## Security Configuration

### APP_SECRET Management

The `APP_SECRET` is critical for application security and is used for:
- CSRF token generation and validation
- Session encryption and security
- Various cryptographic operations

#### Environment-Specific Configuration:

- **Development**: Use `.env.local` (git-ignored) with a secure 64-character secret
- **Production**: Set `APP_SECRET` as an environment variable
- **Testing**: Use the provided secure secret in `.env.test`

#### Secret Generation Methods:

```bash
# Method 1: Using OpenSSL (recommended)
openssl rand -hex 32

# Method 2: Using Symfony Console
php bin/console secrets:generate-keys

# Method 3: Using PHP
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

#### Security Requirements:
- ✅ Minimum 32 characters length
- ✅ Recommended 64 characters
- ✅ Cryptographically secure random generation
- ✅ Never commit secrets to version control
- ✅ Use environment variables in production

### Session Security Impact

**IMPORTANT**: Changing the `APP_SECRET` will invalidate all existing user sessions, requiring users to re-authenticate.

#### Deployment Strategy:
1. Schedule updates during low-traffic periods
2. Notify users of potential re-authentication requirements
3. Consider gradual rollout for production environments
4. Monitor for authentication-related issues post-deployment

### Secret Rotation

#### Regular Rotation (Recommended):
- Rotate secrets quarterly or as per security policy
- Document rotation dates and procedures
- Test rotation process in staging environments

#### Emergency Rotation:
1. Generate new secret immediately
2. Update all environment configurations
3. Deploy to all environments
4. Monitor for security incidents
5. Audit access logs

## Environment Variables

| Variable | Description | Required | Example |
|----------|-------------|----------|---------|
| `APP_ENV` | Application environment | Yes | `dev`, `prod`, `test` |
| `APP_SECRET` | Cryptographic secret | Yes | 64-character random string |
| `APP_DEBUG` | Debug mode flag | No | `true`, `false` |

## File Structure

```
.env              # Default environment variables (committed)
.env.local        # Local overrides (git-ignored) - PUT YOUR APP_SECRET HERE
.env.example      # Template for environment setup (committed)
.env.dev          # Development-specific defaults (committed)
.env.test         # Test environment configuration (committed)
```

## Testing

### CSRF Verification

Verify CSRF functionality is working correctly:

```bash
# Check CSRF-protected routes
php bin/console debug:router

# Test CSRF token generation (if csrf_verification.php exists)
php csrf_verification.php

# Manual CSRF testing
curl -X POST http://localhost:8000/form-endpoint \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "csrf_token=invalid_token"
```

### Environment Validation

The application includes startup validation to ensure `APP_SECRET` is properly configured:

- ❌ Empty `APP_SECRET` → Application fails to start with clear error message
- ❌ Short `APP_SECRET` (< 32 chars) → Application fails to start
- ⚠️ Suboptimal length (< 64 chars) → Warning logged but application starts

## Deployment

### Production Checklist

- [ ] `APP_SECRET` configured as environment variable (not in files)
- [ ] No secrets committed to version control
- [ ] `.env.local` excluded from deployment artifacts
- [ ] Environment validation passes
- [ ] CSRF functionality tested
- [ ] Session invalidation impact communicated
- [ ] Monitoring configured for authentication failures

### Environment Variable Configuration

**Production deployment should use environment variables:**

```bash
# Example: Docker
docker run -e APP_SECRET=your_secret_here app:latest

# Example: Kubernetes
env:
  - name: APP_SECRET
    valueFrom:
      secretKeyRef:
        name: app-secrets
        key: app-secret

# Example: Traditional server
export APP_SECRET=your_secret_here
```

## Troubleshooting

### Common Issues

1. **"APP_SECRET environment variable is not set"**
   - Create `.env.local` with proper `APP_SECRET`
   - Ensure environment variable is set in production

2. **"APP_SECRET must be at least 32 characters long"**
   - Generate a new secret: `openssl rand -hex 32`
   - Update configuration with the new secret

3. **CSRF token validation failures**
   - Verify `APP_SECRET` is consistent across requests
   - Check if secret was recently changed (invalidates sessions)
   - Ensure secret is properly configured

4. **Users logged out after deployment**
   - Expected behavior when `APP_SECRET` changes
   - Communicate re-authentication requirement to users

### Validation Commands

```bash
# Check APP_SECRET length
php -r "echo 'APP_SECRET length: ' . strlen(getenv('APP_SECRET')) . PHP_EOL;"

# Verify environment configuration
php bin/console debug:container --env=prod

# Test application startup
php bin/console cache:clear --env=prod
```

## Security Best Practices

1. **Never commit secrets to version control**
2. **Use different secrets per environment**
3. **Rotate secrets regularly**
4. **Monitor for security incidents**
5. **Validate environment configuration**
6. **Document rotation procedures**
7. **Test secret changes in staging first**

## Support

For security-related issues or questions about secret management, please follow your organization's security incident response procedures.