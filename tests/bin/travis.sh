#!/usr/bin/env bash
# usage: travis.sh before|after

if [ $1 == 'before' ]; then

	composer self-update
	composer install --no-interaction

elif [ $1 == 'during' ]; then

	## Only run on latest stable PHP box (defined in .travis.yml).
	if [[ ${TRAVIS_PHP_VERSION} == ${PHP_LATEST_STABLE} ]]; then

		# lint
		find -L . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n 1 -P 4 php -l;

		# phpcs
		composer run-script phpcs

	fi

fi