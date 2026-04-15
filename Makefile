.PHONY: install test test-unit test-integration start lint build run

install:
	composer install

test:
	vendor/bin/phpunit

test-unit:
	vendor/bin/phpunit --testsuite unit

test-integration:
	vendor/bin/phpunit --testsuite integration

start:
	php -S localhost:8080 -t public

lint:
	vendor/bin/phpcs --standard=PSR12 src/ tests/

build:
	docker build -t chat-app .

run:
	docker run -p 8080:8080 chat-app
