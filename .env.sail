###############################################################################
# Docker (Laravel Sail) Overrides
###############################################################################
# Append these to your .env when running with Sail:
#   cp .env.example .env && cat .env.sail >> .env && php artisan key:generate
#
# These override host-based defaults (127.0.0.1) with Docker service names.
###############################################################################

# Database — points to the 'mysql' container
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=crurated_erp
DB_USERNAME=sail
DB_PASSWORD=password

# Redis — points to the 'redis' container
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Meilisearch — points to the 'meilisearch' container
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_NO_ANALYTICS=false

# Uncomment for production-like queue/cache behavior in Docker:
# QUEUE_CONNECTION=redis
# CACHE_STORE=redis
# SESSION_DRIVER=redis
