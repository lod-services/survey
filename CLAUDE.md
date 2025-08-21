# Survey Application - Development Documentation

## Security Implementation

### Environment Variable Validation

The application implements mandatory environment variable validation during startup to prevent security vulnerabilities, particularly CSRF protection failures due to weak or missing APP_SECRET configuration.

#### Implementation Details

**Location**: `src/Kernel.php` constructor

The Kernel class now includes environment validation during the constructor initialization:

- **Validates APP_SECRET presence**: Ensures the environment variable is not empty
- **Enforces minimum length**: Requires at least 32 characters for sufficient entropy
- **Environment-specific**: Only validates in production environment (or with FORCE_SECURITY_VALIDATION)
- **Fails fast**: Application refuses to start with invalid configuration

#### Production Readiness Command

**Command**: `php bin/console app:production-readiness-check`
**Location**: `src/Command/ProductionReadinessCheckCommand.php`

This command validates:
- APP_SECRET configuration (presence, length, and entropy)
- Environment settings (APP_ENV)
- Returns appropriate exit codes for CI/CD integration

#### Exception Handling

**Location**: `src/Exception/SecurityConfigurationException.php`

Custom exception for configuration errors that provides clear, actionable error messages without exposing sensitive information.

#### Testing

Unit tests are located in:
- `tests/KernelValidationTest.php` - Tests kernel validation logic
- `tests/ProductionReadinessCheckCommandTest.php` - Tests production readiness command

#### Usage Examples

```bash
# Check production readiness
php bin/console app:production-readiness-check

# Generate a secure secret (example)
openssl rand -hex 32
```

#### Error Messages

The implementation provides clear error messages:
- Missing APP_SECRET: Guides users to configure a 32+ character secret
- Short APP_SECRET: Shows current length and minimum requirement
- Insufficient entropy: Warns about weak character variety
- Includes actionable steps for resolution

#### Security Considerations

- Error messages never expose actual secret values
- Validation occurs early in application lifecycle (constructor)
- Environment-specific validation improves development experience
- Uses direct environment variable access for consistency
- Enhanced entropy validation prevents weak patterns

## Deployment Requirements

### Environment Variables

- `APP_SECRET`: Must be at least 32 characters long with sufficient entropy
- `APP_ENV`: Should be set to "prod" for production deployments

### Pre-deployment Validation

Always run the production readiness check before deployment:

```bash
php bin/console app:production-readiness-check
```

This command should return exit code 0 for successful deployments.