# CircuitBreaker

A framework-agnostic PHP library that implements the Circuit Breaker pattern for fault-tolerant service communication. 

This pattern is commonly used to handle service failures gracefully, prevent cascading failures, overloading downstream 
services, and allow services to recover more effectively. The library is designed for use in any PHP project, whether 
it is a microservice, monolithic application, or integrated into existing codebases.


### Objectives

Support Configurable States: Implement the `CLOSED`, `OPEN`, and `HALF_OPEN` states with configurable parameters like 
failure thresholds and retry timeouts.


### Scope and Features

#### Core Features:
**State Management**: Implement the three primary states of a circuit breaker:
 - `CLOSED`: Normal operation, all requests pass through.
 - `OPEN`: Requests are blocked after reaching the failure threshold.
 - `HALF_OPEN`: A trial state after a timeout, allowing limited requests to check if the service has recovered.



**Failure Threshold**: The circuit breaker opens after a configurable number of consecutive failures.

**Retry Timeout**: The circuit breaker transitions to HALF_OPEN after a configurable timeout period.

**State Transition Logging**: Provide logging options to track state transitions and failure counts.

**Custom Error Handling**: Allow customisable error responses or fallback logic when the circuit breaker is open.

#### Pluggable Storage Backends:
In-Memory Storage: A default storage option for single-instance applications.
Custom Storage Support: Allow other storage solutions (e.g., Redis, database).

#### Ease of Integration:
The library should be easy to integrate into any PHP project via Composer.
It should not require any specific framework or dependencies.



## Getting Started

Docker config added for ease of development and testing.
You'll need docker installed and running


If you're able to run make, you can use the following commands:

To build the project:
```shell
make build
```

Run the tests:
```shell
make tests-all
```

Run code quality checks (phpstan and rector):
```shell
make checks
```

Otherwise

Install dependencies:
```shell
docker compose run --rm composer composer install
```

Run the tests:
```shell
docker compose run --rm php vendor/bin/phpunit tests/
```




