.PHONY: default
default:
	@echo 'Usage: make [build|test]'

.PHONY: build
build:
	box compile

.PHONY: test
test:
	./vendor/bin/phpunit --colors=always ./tests
