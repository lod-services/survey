# Survey Application - Development Guide

## Project Overview
This is a Symfony 7.3 survey application with comprehensive security hardening, Bootstrap 5.3.7 frontend, and Stimulus controllers for interactivity.

## Technology Stack
- **Framework**: Symfony 7.3
- **Frontend**: Bootstrap 5.3.7, Stimulus 3.2.2
- **Build Tool**: Webpack Encore
- **Database**: Doctrine ORM with PostgreSQL (configurable)
- **Testing**: PHPUnit 9.6 with Symfony test framework

## Security Implementation
The application includes comprehensive security headers and session hardening:

### Security Headers (via SecurityHeadersSubscriber)
- **CSP**: Nonce-based Content Security Policy with environment-specific configurations
- **HSTS**: HTTP Strict Transport Security (production only)
- **Frame Protection**: X-Frame-Options: DENY
- **Content-Type Protection**: X-Content-Type-Options: nosniff
- **Referrer Policy**: strict-origin-when-cross-origin
- **Permissions Policy**: Restricts camera, microphone, geolocation access

### Session Security
- **HttpOnly**: Prevents client-side access to session cookies
- **Secure**: HTTPS-only cookies (production)
- **SameSite**: Strict mode for enhanced CSRF protection
- **Timeouts**: 30min regular sessions, 15min admin sessions

### CSRF Protection
- Enhanced with cryptographically secure 64-character APP_SECRET
- Fully integrated with Symfony's CSRF component

## Common Commands

### Development
```bash
# Install dependencies
composer install
npm install

# Build assets for development
npm run dev

# Start development server  
symfony server:start

# Watch for asset changes
npm run watch
```

### Testing
```bash
# Run PHPUnit tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Run specific test
vendor/bin/phpunit tests/ApplicationTest.php
```

### Code Quality
```bash
# Run PHP CS Fixer
vendor/bin/php-cs-fixer fix

# Run PHPStan analysis
vendor/bin/phpstan analyse src tests
```

### Asset Management
```bash
# Production build
npm run build

# Development build with watch
npm run dev

# Production build with optimization
npm run production
```

## Project Structure

### Security Components
- `src/EventSubscriber/SecurityHeadersSubscriber.php` - Main security headers implementation
- `src/Twig/SecurityExtension.php` - CSP nonce support for templates
- `config/packages/framework.yaml` - Session security configuration
- `config/packages/prod/framework.yaml` - Production-specific security settings
- `config/packages/dev/framework.yaml` - Development security overrides

### Configuration Files
- `.env.dev` - Development environment with secure APP_SECRET
- `.env.prod` - Production environment template with HTTPS configuration
- `config/services.yaml` - Service definitions including SecurityHeadersSubscriber

### Frontend Assets
- `assets/app.js` - Main JavaScript entry point with Stimulus
- `assets/styles/app.css` - Main CSS file
- `assets/controllers/` - Stimulus controllers
- `public/build/` - Built assets (generated)

### Templates
- `templates/base.html.twig` - Base template with security meta tags
- `templates/home/index.html.twig` - Homepage with Bootstrap components
- All templates support CSP nonces via `csp_nonce()` Twig function

## Environment Configuration

### Development Environment
- Web Profiler enabled with CSP exceptions
- Relaxed CSP for debugging (allows unsafe-eval, unsafe-inline)
- Auto-detection of HTTPS (`cookie_secure: auto`)
- Extended session timeout (1 hour)

### Production Environment  
- Strict CSP with nonce-based inline content only
- HSTS enforcement with preload
- Forced HTTPS cookies (`cookie_secure: true`)
- 30-minute session timeout

## Security Best Practices

### CSP Nonce Usage
When adding inline scripts or styles to templates:
```twig
<script nonce="{{ csp_nonce() }}">
    // Your inline JavaScript
</script>

<style nonce="{{ csp_nonce() }}">
    /* Your inline CSS */
</style>
```

### Session Management
- Session timeout configured via `gc_maxlifetime`
- Admin users get shorter timeout (15min via role-based configuration)
- Remember-me functionality limited to 7 days maximum

### HTTPS Requirements
- Production deployment requires valid HTTPS certificates
- HSTS header only applied in production environment
- All security features optimized for HTTPS deployment

## Testing Security Features

### Manual Testing
1. Check response headers in browser developer tools
2. Verify CSP violations are logged appropriately
3. Test session timeout functionality
4. Verify cookie security attributes

### Automated Testing
- `ApplicationTest.php` verifies basic functionality and security headers
- Tests confirm CSP nonce generation and usage
- Session configuration validated through functional tests

## Troubleshooting

### CSP Issues
- Check browser console for CSP violation reports
- Ensure all inline content uses proper nonces
- Review environment-specific CSP policies

### Session Problems
- Verify HTTPS configuration in production
- Check session storage permissions
- Review cookie domain and path settings

### Asset Building Issues
- Clear Symfony cache: `php bin/console cache:clear`
- Rebuild assets: `npm run dev`
- Check for missing dependencies: `npm install`

## Performance Considerations

### Security Overhead
- Security headers add < 1ms per request
- CSP nonce generation is lightweight
- EventSubscriber uses high priority to minimize processing

### Asset Optimization
- Production builds include minification and optimization
- Webpack Encore provides efficient asset versioning
- CDN support available for static assets

## Deployment Notes

### Prerequisites
- PHP 8.1+
- Composer installed
- Node.js and npm for asset building
- HTTPS certificate for production
- Database (PostgreSQL recommended)

### Security Checklist
- [ ] APP_SECRET is 64 characters and secure
- [ ] HTTPS properly configured and tested
- [ ] CSP policies tested and working
- [ ] Session timeouts appropriate for use case
- [ ] Security headers validated in production
- [ ] Database connection secured with strong credentials

## Documentation References
- `SECURITY.md` - Comprehensive security implementation details
- Symfony Security Documentation: https://symfony.com/doc/current/security.html
- OWASP Security Headers: https://owasp.org/www-project-secure-headers/