# Survey Application

## Security Configuration

### APP_SECRET Setup

The application requires a secure `APP_SECRET` configuration for CSRF protection and session security.

#### Production Setup

1. **Generate a secure 64-character secret:**
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```

2. **Create `.env.local` file (git-ignored):**
   ```bash
   # Create the file with your generated secret
   echo "APP_SECRET=your_generated_64_char_secret_here" > .env.local
   
   # Set secure file permissions (read/write for owner only)
   chmod 600 .env.local
   ```

3. **Verify configuration:**
   ```bash
   php csrf_verification.php
   ```

#### Development Setup

The development environment is pre-configured with a secure secret in `.env.dev`, but you can override it locally:

1. Create `.env.local` with your own secret (optional)
2. The application will automatically use your local secret

#### Security Requirements

- **Secret Length**: Minimum 64 characters (enforced at startup)
- **File Permissions**: `.env.local` should have 600 permissions
- **Version Control**: Never commit secrets to git - `.env.local` is git-ignored

#### Deployment Checklist

- [ ] Generate secure APP_SECRET (64+ characters)
- [ ] Create `.env.local` in production environment
- [ ] Set file permissions to 600 on `.env.local`
- [ ] Verify no secrets are committed to version control
- [ ] Run CSRF verification script to validate configuration

#### Container Deployment

For containerized deployments:

1. **Docker Secrets** (recommended):
   ```bash
   docker secret create app_secret your_generated_secret
   ```

2. **Environment Variables**:
   ```bash
   docker run -e APP_SECRET=your_generated_secret survey-app
   ```

3. **Volume Mount** `.env.local`:
   ```bash
   docker run -v /host/path/.env.local:/app/.env.local survey-app
   ```

#### Error Messages

If the application fails to start due to security configuration issues:

- **"APP_SECRET is not configured"**: Create `.env.local` with a valid secret
- **"APP_SECRET must be at least 64 characters"**: Generate a longer secret using the command above

#### Security Health Check

The application includes built-in validation that:
- Checks APP_SECRET presence at startup
- Validates minimum 64-character length requirement
- Provides clear error messages for configuration issues
- Prevents the application from starting with insecure configuration