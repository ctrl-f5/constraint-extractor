GID := $(shell id -g)
UID := $(shell id -u)
TTY := $(shell [ -t 0 ] && echo "-it")
UNAME := $(shell uname)
SSH_SOCK := $(if $(filter Darwin,$(UNAME)),/run/host-services/ssh-auth.sock,$(SSH_AUTH_SOCK))
DOCKER = docker run $(TTY) --rm -u $(UID):$(GID) -v $(PWD):/app -v $(SSH_SOCK):/ssh-agent -e SSH_AUTH_SOCK=/ssh-agent -w /app
PHP = constraint-extractor-php
PHP_LOW = constraint-extractor-php-low
XDEBUG=0

php:
	docker build -t $(PHP) --build-arg XDEBUG=$(XDEBUG) -f Dockerfile .
	docker build -t $(PHP_LOW) --build-arg XDEBUG=$(XDEBUG) --build-arg PHP_VERSION=8.1 -f Dockerfile .
.PHONY: php

bash:
	$(DOCKER) $(PHP) bash
.PHONY: bash

install: php composer-high
.PHONY: install

composer-high:
	$(DOCKER) $(PHP) composer update --no-scripts --prefer-stable --no-plugins
.PHONY: composer-high

composer-low:
	$(DOCKER) $(PHP_LOW) composer update --prefer-lowest --prefer-stable --no-scripts --no-plugins
.PHONY: composer-low

test:
	$(DOCKER) $(PHP) vendor/bin/phpunit --colors=auto tests
.PHONY: test

test-versions:
	make composer-low
	$(DOCKER) $(PHP_LOW) vendor/bin/phpunit --colors=auto tests
	make composer-high
	$(DOCKER) $(PHP) vendor/bin/phpunit --colors=auto tests
.PHONY: test
