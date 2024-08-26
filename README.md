# CircuitBreaker
PHP library implementing the CircuitBreaker pattern

Docker config added for ease of development and testing.

Install dependencies:
```shell
docker compose run --rm composer install
```

Run the tests (when they exist)
```shell
docker compose run --rm php vendor/bin/phpunit tests/
```