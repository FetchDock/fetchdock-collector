#!/bin/sh
set -e

# In production, refuse to start with a missing or placeholder Mercure JWT key: anyone knowing the key can issue
# themselves subscriber tokens. Set CADDY_MERCURE_JWT_SECRET to a strong secret.
if [ "${APP_ENV:-}" = 'prod' ]; then
	for key_var in MERCURE_PUBLISHER_JWT_KEY MERCURE_SUBSCRIBER_JWT_KEY; do
		eval "key_value=\${$key_var:-}"
		if [ -z "$key_value" ] || [ "$key_value" = '!ChangeThisMercureHubJWTSecretKey!' ]; then
			echo "ERROR: $key_var is empty or the default placeholder. Set CADDY_MERCURE_JWT_SECRET to a strong secret." >&2
			exit 1
		fi
	done
fi

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=${DB_WAIT_ATTEMPTS:-60}
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q 'SELECT 1' 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		# Only the php node runs migrations; the worker waits until they are applied
		NODE_MODE=${NODE_MODE:-php}
		if [ "$NODE_MODE" = "php" ]; then
			echo "[migration-coordinator] NODE_MODE=php -> running migrations (if any)"
			if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
				if ! php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing; then
					echo "[migration-coordinator] ERROR: doctrine migrations failed" >&2
					exit 1
				fi
			else
				echo "[migration-coordinator] no migration files found, skipping"
			fi

			# Staging is re-seeded with mock data on every start (ADR 0014). app:fixtures:seed refuses to run
			# unless COLLECTOR_ALLOW_FIXTURES=1, which production never sets.
			if [ "${COLLECTOR_SEED_ON_START:-0}" = '1' ]; then
				echo "[fixtures] COLLECTOR_SEED_ON_START=1 -> replacing all data with mock data"
				php bin/console app:fixtures:seed --no-interaction
			fi
		elif [ "$NODE_MODE" = "worker" ]; then
			MIGRATION_POLL_INTERVAL=${MIGRATION_POLL_INTERVAL:-2}
			MIGRATION_TIMEOUT=${MIGRATION_TIMEOUT:-300}
			START_TIME=$(date +%s)
			echo "[migration-coordinator] NODE_MODE=worker -> waiting for migrations (timeout ${MIGRATION_TIMEOUT}s)"
			until php bin/console doctrine:migrations:up-to-date --no-interaction >/dev/null 2>&1; do
				NOW=$(date +%s)
				if [ $((NOW - START_TIME)) -ge "$MIGRATION_TIMEOUT" ]; then
					echo "[migration-coordinator] TIMEOUT waiting for migrations after ${MIGRATION_TIMEOUT}s" >&2
					exit 2
				fi
				sleep "$MIGRATION_POLL_INTERVAL"
			done
			echo "[migration-coordinator] migrations are up to date -> proceeding"
		else
			echo "[migration-coordinator] NODE_MODE=${NODE_MODE} -> skipping migrations"
		fi
	fi

	if ! { setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var 2>/dev/null && \
	       setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var 2>/dev/null; }; then
		echo "WARNING: setfacl failed (ACLs may not be supported on this filesystem). Falling back to chmod." >&2
		chown -R "$(whoami)":www-data var
		chmod -R 775 var
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
