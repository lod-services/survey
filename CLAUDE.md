# Survey Application - Development Guide

## Project Overview
This is a Symfony-based survey application with comprehensive dependency management and security practices.

## Dependency Management

### Best Practices

#### Composer Operations
- **Always commit both `composer.json` and `composer.lock` together**
- **Never edit `composer.json` manually** - use `composer require` and `composer remove` commands instead
- **Run `composer validate --strict` before committing** to catch issues early

#### Choosing Between Install vs Update
- **Use `composer install`** for:
  - Production deployments (maintains exact versions from lock file)
  - When you want reproducible builds with same versions across environments
  - CI/CD pipelines to ensure consistent testing

- **Use `composer update`** for:
  - Development when you want latest compatible versions
  - Getting security patches (but requires comprehensive testing)
  - Initial setup or major dependency refreshes

#### Security Practices
- **Run `composer audit`** regularly to check for security vulnerabilities
- **Enable Dependabot** for automated security updates (configured in `.github/dependabot.yml`)
- **Review security updates promptly** and test thoroughly before deployment

#### Common Commands
```bash
# Validate composer files
composer validate --strict --no-check-all

# Install dependencies (reproducible builds)
composer install --prefer-dist --no-dev

# Update dependencies (get latest patches)
composer update

# Security audit
composer audit

# Check for outdated packages
composer outdated --direct
```

### CI/CD Integration

#### GitHub Actions
The project includes automated dependency scanning:
- **Security audit** runs on every push and PR
- **Composer validation** ensures file consistency
- **Outdated package checks** for maintenance awareness

#### Dependabot Configuration
- **Weekly dependency updates** on Mondays at 9:00 AM
- **Security updates** have higher priority
- **Automatic labeling** with "dependencies" and "security"

### Troubleshooting

#### Lock File Out of Sync
If you see "lock file is not up to date" errors:
1. **Check for manual edits** in `composer.json` - validate syntax
2. **Fix any package name errors** (e.g., `symfony/doctrine-bundle` → `doctrine/doctrine-bundle`)  
3. **Run `composer update`** to resolve missing packages
4. **Run `composer audit`** to verify security after update
5. **Test thoroughly** - dependency updates may introduce breaking changes

#### Package Name Errors
- Common mistake: `symfony/doctrine-bundle` (incorrect) vs `doctrine/doctrine-bundle` (correct)
- Always verify package names on [Packagist](https://packagist.org)
- Use `composer show package/name` to verify package exists

### Environment Synchronization

#### Deployment Strategy
1. **Staging**: Test with `composer install --no-dev` to mirror production
2. **Production**: Always use `composer install --no-dev --prefer-dist` for performance
3. **Rollback Plan**: Keep previous `composer.lock` for quick rollbacks

#### Testing Strategy for Dependency Updates
When updating dependencies, test these critical areas:
- **Console commands** (Symfony Console)
- **Database operations** (Doctrine ORM)
- **Authentication/Authorization** (Security Bundle)  
- **Form validation** (Form & Validator components)
- **Template rendering** (Twig)
- **Test suite execution** (PHPUnit)

## Development Workflow

### Code Quality Tools
- **PHPStan**: Static analysis (`vendor/bin/phpstan analyse`)
- **PHP CS Fixer**: Code formatting (`vendor/bin/php-cs-fixer fix`)
- **PHPUnit**: Testing (`vendor/bin/phpunit`)

### Development Commands
```bash
# Install dependencies for development
composer install

# Run code quality checks
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpunit

# Check for security issues
composer audit
```

## Security Considerations

### Dependency Security
- **Never commit sensitive data** in composer files
- **Review dependency licenses** for compliance
- **Monitor security advisories** through GitHub Dependabot
- **Update dependencies regularly** but test thoroughly

### Process Improvements
- **Pre-commit hooks** can validate composer files automatically
- **Branch protection** should require CI checks to pass
- **Regular security audits** should be part of maintenance schedule

## Maintenance Schedule

### Weekly Tasks
- Review Dependabot PRs and security alerts
- Check `composer outdated` for available updates
- Review CI/CD pipeline status

### Monthly Tasks  
- Run comprehensive dependency audit
- Update development dependencies
- Review and clean unused dependencies

### Quarterly Tasks
- Major version updates (with comprehensive testing)
- Security architecture review
- Dependency license compliance check

---

## Issue #416 Resolution Summary

**Fixed**: Critical composer.lock synchronization issue
- ✅ Corrected package name error: `symfony/doctrine-bundle` → `doctrine/doctrine-bundle`
- ✅ Synchronized 15 missing packages with `composer update`
- ✅ Verified no security vulnerabilities with `composer audit`  
- ✅ Added CI/CD dependency scanning (GitHub Actions + Dependabot)
- ✅ Documented best practices for future maintenance

**Result**: All dependencies are now properly locked, secure, and continuously monitored.