#!/bin/sh
# Installs Drupal on SQLite with drush and geofield, enables yatmo_map, configures the demo key (YATMO_KEY
# of the container) and creates a "Property" content type with a geofield, an address, two listings,
# the Yatmo formatters on the field and the Yatmo blocks on property pages. Idempotent enough to rerun.
set -e
cd /opt/drupal
if [ ! -x vendor/bin/drush ]; then
  composer require drush/drush drupal/geofield --no-interaction --quiet
fi
if ! vendor/bin/drush status --field=bootstrap 2>/dev/null | grep -q Successful; then
  vendor/bin/drush site:install standard --db-url=sqlite://localhost/sites/default/files/.ht.sqlite \
    --site-name="Maison Demo" --account-name=admin --account-pass=admin --yes --quiet
fi
vendor/bin/drush en geofield yatmo_map --yes --quiet
vendor/bin/drush php:script /yatmo-dev/setup.php
vendor/bin/drush cache:rebuild --quiet
# drush ran as root: give the database and the files back to Apache.
chown -R www-data:www-data web/sites/default/files
echo "Drupal $(vendor/bin/drush status --field=drupal-version), yatmo_map enabled: http://localhost:8090/node/1 (admin / admin)"
