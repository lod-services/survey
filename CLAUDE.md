# Survey Application - Development Notes

## Security Configuration

### APP_SECRET Management

**Current Status**: ✅ Secure APP_SECRET configured (2025-08-02)

The application uses Symfony's APP_SECRET for cryptographic operations including:
- CSRF token generation and validation
- Session security
- Password reset tokens
- API token generation

#### Secret Requirements
- **Length**: Minimum 64 characters (current: 64 characters)
- **Entropy**: 256-bit cryptographically secure randomness
- **Format**: Hexadecimal string
- **Rotation**: Every 90 days

#### Current Configuration
- **Main Environment** (`.env`): Secure 64-character secret configured
- **Development** (`.env.dev`): Separate development secret
- **Testing** (`.env.test`): Separate test secret

#### Secret Generation Process
```bash
# Generate new 64-character secret
openssl rand -hex 32

# Validate length (should be 64)
echo "Secret length: ${#SECRET} characters"
```

#### Safe Deployment Process
1. **Backup**: Always create timestamped backup of .env file
2. **Atomic Update**: Use temporary file for atomic replacement
3. **Validation**: Verify replacement was successful
4. **Testing**: Run CSRF verification script

#### Emergency Secret Rotation
```bash
# 1. Generate new secret
SECRET=$(openssl rand -hex 32)

# 2. Create backup
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# 3. Update atomically
sed "s/APP_SECRET=.*/APP_SECRET=$SECRET/" .env > .env.tmp && mv .env.tmp .env

# 4. Verify update
php csrf_verification.php

# 5. Test application functionality
```

### CSRF Protection

**Status**: ✅ Enabled and functional

- **Configuration**: `config/packages/framework.yaml:3`
- **Session Support**: Required and configured
- **Verification Script**: `csrf_verification.php`
- **Test Forms**: Available at `/test-form`

#### Validation Process
```bash
# Run CSRF verification
php csrf_verification.php

# Expected output should show all ✅ marks
```

### Testing Commands

```bash
# Run all tests
./vendor/bin/phpunit

# Verify CSRF configuration
php csrf_verification.php

# Check APP_SECRET configuration
grep "APP_SECRET=" .env
```

### Security Notes

⚠️ **Critical**: Never commit actual secrets to version control
⚠️ **Production**: Use environment variables instead of .env files
⚠️ **Backup**: Always backup before secret rotation
⚠️ **Timeline**: Address security issues within 48-72 hours

### Recent Security Fixes

- **2025-08-02**: Fixed critical empty APP_SECRET vulnerability (Issue #134)
  - Generated 64-character cryptographically secure secret
  - Added proper documentation and rotation schedule
  - Verified CSRF protection functionality
  - Implemented atomic file operations with backup/restore capability