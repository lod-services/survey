# Survey Application Database and Caching Setup Guide

This guide provides comprehensive instructions for setting up the database layer and caching infrastructure for the Survey Application across development, staging, and production environments.

## Quick Start (Development)

### Prerequisites
- PHP 8.1+
- Composer
- Docker & Docker Compose (recommended)
- PostgreSQL or MySQL (if not using Docker)

### 1. Environment Setup

```bash
# Copy environment configuration
cp .env.development .env.local

# Install dependencies
composer install

# Start database and cache services (Docker)
docker-compose up -d database redis memcached
```

### 2. Database Setup

```bash
# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# Load sample data (optional)
php bin/console doctrine:fixtures:load
```

### 3. Cache Setup

The application is configured with multi-level caching:
- **Primary**: Redis (localhost:6379)
- **Fallback**: Filesystem cache
- **Development**: Filesystem cache (no Redis required)

## Environment Configurations

### Development Environment

**Database Options:**
1. **PostgreSQL** (recommended): `postgresql://app:dev_password@localhost:5432/survey_dev`
2. **MySQL** (alternative): `mysql://app:dev_password@localhost:3306/survey_dev`
3. **SQLite** (lightweight): `sqlite:///%kernel.project_dir%/var/database.db`

**Caching:**
- Uses filesystem cache by default
- Redis available via Docker for testing production scenarios

### Staging Environment

**Database:**
- PostgreSQL with SSL required
- Connection pooling (20-50 connections)
- Daily automated backups
- Manual migration execution

**Caching:**
- Redis primary with Memcached fallback
- SSL/TLS encryption enabled
- Performance monitoring

### Production Environment

**Database:**
- PostgreSQL with read replicas
- SSL/TLS with certificate validation
- Connection pooling (50-100 connections)
- High availability setup
- Continuous backup with point-in-time recovery

**Caching:**
- Redis cluster with sentinel
- Memcached cluster fallback
- Circuit breaker patterns
- Performance monitoring and alerting

## Docker Services

### Available Services

```yaml
services:
  database:      # PostgreSQL 16
  mysql:         # MySQL 8.0 (alternative)
  redis:         # Redis 7 (primary cache)
  memcached:     # Memcached 1.6 (fallback cache)
```

### Service Management

```bash
# Start all services
docker-compose up -d

# Start specific services
docker-compose up -d database redis

# View logs
docker-compose logs -f database

# Stop services
docker-compose down
```

## Database Migrations

### Creating Migrations

```bash
# Generate migration from entity changes
php bin/console make:migration

# Create empty migration
php bin/console doctrine:migrations:generate
```

### Running Migrations

```bash
# Check migration status
php bin/console doctrine:migrations:status

# Run migrations
php bin/console doctrine:migrations:migrate

# Migrate to specific version
php bin/console doctrine:migrations:migrate 20231201120000
```

### Environment-Specific Migration Strategies

**Development:**
- Auto-run migrations
- Schema validation enabled
- Query logging enabled

**Staging:**
- Manual migration execution
- Backup before migration
- Rollback procedures tested

**Production:**
- Blue-green deployment strategy
- Zero-downtime migrations
- Comprehensive backup and rollback plans

## Caching Strategy

### Cache Layers

1. **Redis** (Primary)
   - Session storage
   - Application cache
   - Query result cache
   - Real-time data

2. **Memcached** (Fallback)
   - Application cache fallback
   - Session fallback
   - Simple key-value storage

3. **Filesystem** (Final Fallback)
   - Development environment
   - Emergency fallback
   - Config cache

### Cache Pools

- `survey.cache`: 30 minutes TTL
- `response.cache`: 15 minutes TTL
- `user.session.cache`: 2 hours TTL
- `doctrine.result_cache_pool`: 1 hour TTL
- `doctrine.system_cache_pool`: 24 hours TTL

## SSL/TLS Configuration

### Database SSL

**PostgreSQL:**
```env
DATABASE_URL="postgresql://user:pass@host:5432/db?sslmode=require&sslcert=/path/to/client-cert.pem&sslkey=/path/to/client-key.pem&sslrootcert=/path/to/ca-cert.pem"
```

**MySQL:**
```env
DATABASE_URL="mysql://user:pass@host:3306/db?ssl_ca=/path/to/ca-cert.pem&ssl_cert=/path/to/client-cert.pem&ssl_key=/path/to/client-key.pem"
```

### Redis SSL

```env
REDIS_URL="rediss://password@host:6380?ssl_cert_file=/path/to/redis-client.crt&ssl_key_file=/path/to/redis-client.key&ssl_ca_file=/path/to/redis-ca.crt"
```

## Performance Optimization

### Database Indexing

The entities include strategic indexes for:
- Survey lookups by creator and status
- Question ordering within surveys
- Response querying by user and survey
- User authentication and authorization

### Connection Pooling

**Development:** 5-10 connections
**Staging:** 20-50 connections  
**Production:** 50-100 connections

### Query Optimization

- Entity lazy loading enabled
- Second-level cache configured
- Query result cache with Redis
- Optimized repository methods

## Monitoring and Maintenance

### Health Checks

```bash
# Database connectivity
php bin/console doctrine:ensure-production-settings

# Cache connectivity
php bin/console cache:warmup

# Overall application health
curl http://localhost:8000/health
```

### Performance Monitoring

- Slow query logging
- Cache hit rate monitoring
- Connection pool utilization
- Memory usage tracking

### Backup Procedures

**Development:**
- Manual backups as needed
- Git version control for schema

**Staging:**
- Daily automated backups
- 7-day retention period
- Backup verification testing

**Production:**
- Continuous backup with WAL-E/WAL-G
- Point-in-time recovery capability
- Cross-region backup replication
- Monthly disaster recovery testing

## Troubleshooting

### Common Issues

**Connection Refused:**
- Check if database service is running
- Verify connection parameters
- Check firewall settings

**SSL Certificate Issues:**
- Verify certificate paths
- Check certificate validity
- Ensure proper permissions

**Cache Connection Issues:**
- Check Redis/Memcached service status
- Verify network connectivity
- Review cache configuration

**Migration Failures:**
- Check database permissions
- Verify schema compatibility
- Review migration script syntax

### Debug Commands

```bash
# Database connection test
php bin/console doctrine:database:create --connection=default --if-not-exists

# Cache pool status
php bin/console cache:pool:list

# Entity validation
php bin/console doctrine:schema:validate

# Cache clear
php bin/console cache:clear --env=prod
```

## Security Best Practices

### Database Security
- Use SSL/TLS encryption for all connections
- Implement certificate-based authentication
- Regular security patches and updates
- Principle of least privilege for database users

### Cache Security  
- Enable Redis AUTH if exposed
- Use SSL for Redis in production
- Implement proper network segmentation
- Regular cache invalidation strategies

### Application Security
- Store secrets in secure vault systems
- Use environment-specific configurations  
- Implement proper input validation
- Regular security audits and penetration testing

## Production Deployment Checklist

- [ ] SSL certificates installed and validated
- [ ] Database connection pooling configured
- [ ] Redis cluster with sentinel setup
- [ ] Backup procedures tested
- [ ] Monitoring and alerting configured
- [ ] Load testing completed
- [ ] Security audit performed
- [ ] Documentation updated
- [ ] Team training completed

## Support and Maintenance

For ongoing support and maintenance:
- Monitor application logs regularly
- Perform regular database maintenance
- Update dependencies and security patches
- Review and optimize query performance
- Conduct periodic disaster recovery tests

## Additional Resources

- [Symfony Doctrine Configuration](https://symfony.com/doc/current/doctrine.html)
- [PostgreSQL SSL Configuration](https://www.postgresql.org/docs/current/ssl-tcp.html)
- [Redis Security Guide](https://redis.io/topics/security)
- [MySQL SSL Configuration](https://dev.mysql.com/doc/refman/8.0/en/using-encrypted-connections.html)