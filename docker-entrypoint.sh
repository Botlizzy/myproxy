#!/bin/sh
set -eu

# Guarantee that Apache starts with exactly one MPM even if the base image
# or a cached layer enabled another MPM module.
a2dismod mpm_event mpm_worker mpm_prefork >/dev/null 2>&1 || true
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork >/dev/null

PORT_VALUE="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf
apache2ctl -t
exec apache2-foreground
