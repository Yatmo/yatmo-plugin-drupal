#!/bin/sh
# Unit tests of the module with Drupal core's PHPUnit (drupal/core-dev installed the first time).
set -e
cd /opt/drupal
if [ ! -x vendor/bin/phpunit ]; then
  composer require --dev drupal/core-dev --no-interaction --quiet --with-all-dependencies
fi
vendor/bin/phpunit -c web/core web/modules/custom/yatmo_map/tests/src/Unit "$@"
