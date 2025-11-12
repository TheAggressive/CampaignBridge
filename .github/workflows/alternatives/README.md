# Alternative CI/CD Approaches

This directory contains alternative CI/CD pipeline implementations for reference.

## 📁 Available Alternatives

### 1. **docker-compose.yml** - Docker Compose Approach

**Philosophy:** Traditional Docker Compose setup with explicit service configuration

**Pros:**
- ✅ More control over containers
- ✅ Familiar to Docker users
- ✅ Easier to customize services
- ✅ Better for complex setups

**Cons:**
- ❌ More configuration required
- ❌ Slower startup (~60 seconds)
- ❌ Manual network/volume management
- ❌ More maintenance overhead

**When to use:**
- Team is experienced with Docker Compose
- Need custom WordPress/MySQL configuration
- Want explicit control over all services
- Have complex multi-service requirements

**Performance:**
```
First run:  ~6 minutes
Cached run: ~3.5 minutes
Setup time: ~60 seconds (Docker Compose startup)
```

---

### 2. **classic-setup.yml** - Classic Setup Approach

**Philosophy:** Traditional MySQL service + manual WordPress installation (pre-wp-env style)

**Pros:**
- ✅ Transparent process
- ✅ No Docker dependency
- ✅ Easier to understand
- ✅ More customizable

**Cons:**
- ❌ Complex setup script
- ❌ Longer execution time
- ❌ More maintenance needed
- ❌ Manual WordPress installation

**When to use:**
- Team unfamiliar with Docker/wp-env
- Need to understand every setup step
- Want to customize WordPress installation
- Have specific database requirements

**Performance:**
```
First run:  ~7 minutes
Cached run: ~4 minutes
Setup time: ~90 seconds (install-wp-tests.sh)
```

---

## 🎯 Recommended Approach

**The main pipeline (`.github/workflows/ci.yml`) uses the optimized wp-env approach.**

### Why wp-env? (Default/Recommended)

**Pros:**
- ✅ **Official WordPress tool**
- ✅ **Fastest setup** (~30 seconds)
- ✅ **Lowest maintenance**
- ✅ **Best performance** (2.5 min cached)
- ✅ **Consistent with local dev**
- ✅ **Automatic updates**

**Cons:**
- ⚠️ Less control over internals
- ⚠️ Requires Docker knowledge

---

## 📊 Performance Comparison

| Approach | First Run | Cached | Setup Time | Complexity |
|----------|-----------|--------|------------|------------|
| **wp-env (recommended)** | 4 min | 2.5 min | 30s | Low ⭐⭐⭐ |
| **docker-compose** | 6 min | 3.5 min | 60s | Medium ⭐⭐ |
| **classic-setup** | 7 min | 4 min | 90s | High ⭐ |

---

## 🔄 Switching Between Approaches

### From wp-env to Docker Compose

```bash
# 1. Backup current
mv .github/workflows/ci.yml .github/workflows/ci-wpenv.yml

# 2. Copy alternative
cp .github/workflows/alternatives/docker-compose.yml .github/workflows/ci.yml

# 3. Update docker-compose.yml in project root
cat > docker-compose.ci.yml << 'EOF'
version: '3.8'
services:
  wordpress:
    image: wordpress:6.4-php8.2
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_NAME: wordpress_test
      WORDPRESS_DB_USER: root
      WORDPRESS_DB_PASSWORD: root
    volumes:
      - .:/var/www/html/wp-content/plugins/campaignbridge
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress_test
EOF

# 4. Commit and push
git add .github/workflows/ci.yml docker-compose.ci.yml
git commit -m "ci: switch to Docker Compose approach"
git push
```

### From wp-env to Classic Setup

```bash
# 1. Backup current
mv .github/workflows/ci.yml .github/workflows/ci-wpenv.yml

# 2. Copy alternative
cp .github/workflows/alternatives/classic-setup.yml .github/workflows/ci.yml

# 3. Ensure install-wp-tests.sh exists
ls bin/install-wp-tests.sh || echo "⚠️  Create bin/install-wp-tests.sh"

# 4. Commit and push
git add .github/workflows/ci.yml
git commit -m "ci: switch to classic setup approach"
git push
```

### Back to wp-env (Recommended)

```bash
# 1. Restore from backup
mv .github/workflows/ci-wpenv.yml .github/workflows/ci.yml

# 2. Or copy from alternatives
cp .github/workflows/alternatives/wpenv.yml .github/workflows/ci.yml

# 3. Commit and push
git add .github/workflows/ci.yml
git commit -m "ci: restore optimized wp-env pipeline"
git push
```

---

## 🧪 Testing Alternatives Locally

### Test Docker Compose Approach

```bash
# 1. Start services
docker-compose -f docker-compose.ci.yml up -d

# 2. Run tests
docker-compose exec wordpress bash -c "cd /var/www/html/wp-content/plugins/campaignbridge && vendor/bin/phpunit"

# 3. Stop services
docker-compose -f docker-compose.ci.yml down
```

### Test Classic Setup Approach

```bash
# 1. Install test database
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# 2. Run tests
vendor/bin/phpunit

# 3. Cleanup
rm -rf /tmp/wordpress-tests-lib /tmp/wordpress
```

### Test wp-env Approach (Current)

```bash
# 1. Start environment
pnpm env:start

# 2. Run tests
pnpm test

# 3. Stop environment
pnpm env:stop
```

---

## 🎓 When to Choose Each Approach

### Choose **wp-env** (Recommended) if:
- ✅ You want the fastest, easiest setup
- ✅ You're okay with wp-env's defaults
- ✅ You want consistency with local development
- ✅ You prioritize performance over customization
- ✅ You're building a WordPress plugin/theme

### Choose **Docker Compose** if:
- ✅ You need custom service configurations
- ✅ Your team is experienced with Docker
- ✅ You need multiple interconnected services
- ✅ You want full control over container setup
- ✅ You have complex networking requirements

### Choose **Classic Setup** if:
- ✅ You want to understand every step
- ✅ Your team is unfamiliar with Docker
- ✅ You need highly customized WordPress install
- ✅ You prefer traditional CI approaches
- ✅ You need specific MySQL configurations

---

## 🔍 Detailed Alternative Configurations

### Docker Compose Configuration

**File structure needed:**
```
.
├── .github/workflows/alternatives/docker-compose.yml
├── docker-compose.ci.yml (you create this)
└── .dockerignore
```

**Minimal docker-compose.ci.yml:**

```yaml
version: '3.8'

services:
  wordpress:
    image: wordpress:6.4-php8.2-apache
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_NAME: wordpress_test
      WORDPRESS_DB_USER: root
      WORDPRESS_DB_PASSWORD: root
    volumes:
      - .:/var/www/html/wp-content/plugins/campaignbridge
      - ./tests/php/wp-tests-config.php:/var/www/html/wp-tests-config.php
    depends_on:
      mysql:
        condition: service_healthy

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress_test
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  phpmyadmin:
    image: phpmyadmin:latest
    ports:
      - "8081:80"
    environment:
      PMA_HOST: mysql
      PMA_USER: root
      PMA_PASSWORD: root
    depends_on:
      - mysql
```

### Classic Setup Configuration

**File structure needed:**
```
.
├── .github/workflows/alternatives/classic-setup.yml
├── bin/install-wp-tests.sh
└── phpunit.xml.dist
```

**Minimal install-wp-tests.sh:**

```bash
#!/usr/bin/env bash

# Variables
DB_NAME=${1-wordpress_test}
DB_USER=${2-root}
DB_PASS=${3-''}
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

# Download WordPress test suite
download() {
    if [ ! -d /tmp/wordpress-tests-lib ]; then
        svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes
        svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data/ /tmp/wordpress-tests-lib/data
    fi
}

# Install WordPress
install_wp() {
    if [ ! -d /tmp/wordpress ]; then
        wget -nv -O /tmp/wordpress.tar.gz https://wordpress.org/latest.tar.gz
        tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C /tmp/wordpress
    fi
}

# Create test database
create_db() {
    mysql --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME"
}

download
install_wp
create_db
```

---

## 💡 Migration Tips

### From Classic to wp-env

**Advantages:**
- ⚡ 60% faster execution
- 🔧 Less maintenance
- 📦 Easier setup

**Migration steps:**
1. Add `.wp-env.json` to project root
2. Update `package.json` with wp-env scripts
3. Switch workflow to use `pnpm env:start`
4. Remove `install-wp-tests.sh` dependency

### From Docker Compose to wp-env

**Advantages:**
- ⚡ 40% faster execution
- 🎯 WordPress-specific optimizations
- 📘 Official WordPress tool

**Migration steps:**
1. Remove `docker-compose.ci.yml`
2. Add `.wp-env.json` configuration
3. Update workflow to use wp-env commands
4. Test locally with `pnpm env:start`

---

## 📚 Additional Resources

### wp-env Documentation
- [Official wp-env Guide](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
- [wp-env Configuration](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#wp-env-json)

### Docker Compose Documentation
- [Compose File Reference](https://docs.docker.com/compose/compose-file/)
- [WordPress Docker Images](https://hub.docker.com/_/wordpress)

### WordPress Testing
- [Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [WordPress Test Suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)

---

## 🆘 Support

**Questions about alternatives?**
1. Check the main CI-README.md
2. Review CI-TECHNICAL-GUIDE.md
3. Test locally before switching
4. Start with wp-env (recommended)

---

**Status:** Reference implementations only
**Recommendation:** Use main pipeline (wp-env optimized)
**Maintained:** As examples for specific use cases

---

**Last Updated:** 2024-11-11

