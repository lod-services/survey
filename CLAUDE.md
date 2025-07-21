# Claude Documentation - Survey Application

## Security Configuration

### APP_SECRET Management

**Critical Security Requirement**: The `APP_SECRET` environment variable must never be empty or weak.

#### Generating a Secure APP_SECRET

For a cryptographically secure 256-bit secret, use:
```bash
openssl rand -hex 32
```

This generates a 64-character hexadecimal string suitable for Symfony applications.

#### Configuration Files

- **Development**: Set `APP_SECRET` in `.env` file
- **Production**: Use environment variables or secure secret management systems
- **Never commit**: Real secrets should never be committed to version control

#### Example .env Configuration
```
APP_ENV=dev
APP_SECRET=your_64_character_hexadecimal_secret_here
```

#### Security Validation

The application includes automatic startup validation that:
- Prevents empty `APP_SECRET` values
- Ensures minimum 32-character length
- Provides clear error messages for invalid configurations

If validation fails, the application will not start and will display helpful error messages.

#### Backup and Recovery

Before modifying `.env`:
```bash
cp .env .env.backup
```

#### Dependency Management

This project uses Composer for PHP dependencies:
```bash
composer install  # Install dependencies
composer update    # Update dependencies (if lock file issues)
```

Note: If dependency issues occur, they can be resolved separately from security fixes.

## Development Workflow

### Common Commands

- **Install dependencies**: `composer install`
- **Clear cache**: `php bin/console cache:clear` (requires dependencies)
- **Generate secrets**: `openssl rand -hex 32`

### Testing

- **Unit tests**: `vendor/bin/phpunit` (requires dependencies)
- **Code standards**: `vendor/bin/php-cs-fixer fix`
- **Static analysis**: `vendor/bin/phpstan analyse`

## Security Best Practices

1. **Always validate APP_SECRET before deployment**
2. **Use cryptographically secure random generation**
3. **Never commit secrets to version control**
4. **Rotate secrets periodically in production**
5. **Use environment variables in production environments**

## Emergency Security Response

For critical security vulnerabilities like empty APP_SECRET:

1. **Immediate fix**:
   ```bash
   # Create backup
   cp .env .env.backup
   
   # Generate secure secret
   SECURE_SECRET=$(openssl rand -hex 32)
   
   # Update .env file manually with the new secret
   # Replace: APP_SECRET=
   # With: APP_SECRET=${SECURE_SECRET}
   ```

2. **Verify fix**: Application startup will validate the secret
3. **Test functionality**: Ensure application works correctly
4. **Document and commit changes**

This process ensures immediate security remediation with minimal downtime.