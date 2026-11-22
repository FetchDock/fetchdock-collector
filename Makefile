# Makefile
# Local ports avoid a fetchdock-server dev stack on 80/443/5432
DOCKER_COMPOSE_PREFIX ?= IMAGES_PREFIX=ghcr.io/fetchdock/fetchdock-collector XDEBUG_MODE=off HTTP_PORT=8080 HTTPS_PORT=8443 HTTP3_PORT=8443
CONTRACT := ../contracts/collector-openapi.json

up:
	$(DOCKER_COMPOSE_PREFIX) docker compose up --wait -d

logs:
	$(DOCKER_COMPOSE_PREFIX) docker compose logs -f

down:
	$(DOCKER_COMPOSE_PREFIX) docker compose down

clean:
	$(DOCKER_COMPOSE_PREFIX) docker compose down -v --remove-orphans

.PHONY: build
build:
	$(DOCKER_COMPOSE_PREFIX) docker compose build --pull

# Replaces all local data with mock data (src/DataFixtures)
seed:
	$(DOCKER_COMPOSE_PREFIX) docker compose exec -T api bin/console app:fixtures:seed --no-interaction

prep-test:
	$(DOCKER_COMPOSE_PREFIX) docker compose exec -T api bin/console -e test doctrine:database:drop --force --if-exists
	$(DOCKER_COMPOSE_PREFIX) docker compose exec -T api bin/console -e test doctrine:database:create
	$(DOCKER_COMPOSE_PREFIX) docker compose exec -T api bin/console -e test doctrine:migrations:migrate --no-interaction

test:
	$(DOCKER_COMPOSE_PREFIX) docker compose exec -T api bin/phpunit --colors=always --testdox

bake:
	$(DOCKER_COMPOSE_PREFIX) docker buildx bake --file compose.yaml --file compose.prod.yaml --file ./docker-bake.hcl

# Run a command in the api container (e.g. make api bin/console doctrine:migrations:diff)
Arguments := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

.PHONY: api
api:
	$(DOCKER_COMPOSE_PREFIX) docker compose run --entrypoint="" --rm -it api $(Arguments)

.PHONY: pwa
pwa:
	$(DOCKER_COMPOSE_PREFIX) docker compose run --entrypoint="" --rm -it pwa $(Arguments)

# Exports the contract to the workspace (FetchDock/fetchdock-workspace); run `make contracts:index` there afterwards
.PHONY: openapi
openapi:
	@mkdir -p $(dir $(CONTRACT))
	@if [ -n "$$($(DOCKER_COMPOSE_PREFIX) docker compose ps -q api 2>/dev/null)" ]; then \
		$(DOCKER_COMPOSE_PREFIX) docker compose exec -T api bin/console api:openapi:export > $(CONTRACT); \
	else \
		$(DOCKER_COMPOSE_PREFIX) docker compose run --rm --no-deps -T api bin/console api:openapi:export > $(CONTRACT); \
	fi
	@echo "Exported OpenAPI spec to $(CONTRACT)"
