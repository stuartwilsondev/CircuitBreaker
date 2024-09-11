build:
	docker compose run --rm composer composer install

tests-all:
	make tests-unit

tests-unit:
	docker compose run --rm composer composer phpunit --stop-on-error --stop-on-failure --testsuite=unit

checks:
	docker compose run --rm composer composer phpstan
	docker compose run --rm composer composer rector

rector-fix:
	docker compose run --rm composer composer rector-fix