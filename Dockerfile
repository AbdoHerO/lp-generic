# lp_tifaw — production image
#
# php:8.2-apache is multi-arch, so this builds unchanged on the ARM (A1.Flex)
# and x86 Oracle Cloud shapes. The image is built on the VPS by Jenkins, so it
# is always native — there is no cross-build or QEMU step.
FROM php:8.2-apache

# pdo_mysql is the only extension the application actually misses: fileinfo
# (admin_upload_image MIME checks), mbstring, session and json are bundled.
# opcache is not required but the app has no build step, so compiling every
# request is pure waste.
#
# remoteip matters more than it looks: without it every lead row records the
# Nginx container's address instead of the visitor's, and leads.ip becomes a
# column of one repeated value.
RUN set -eux; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql opcache; \
    a2enmod rewrite headers remoteip

COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-lp-tifaw.ini
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh    /usr/local/bin/lp-entrypoint
RUN chmod +x /usr/local/bin/lp-entrypoint

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html
COPY docker/health.php /var/www/html/health.php

# uploads/ is gitignored, so the checkout carries no images and this directory
# exists only to give the named volume a mount point with the right owner.
# Everything under it is admin-uploaded and lives in the volume, which is
# therefore the only copy — see DEPLOYMENT.md for the backup command.
RUN set -eux; \
    mkdir -p /var/www/html/uploads /var/lib/php/sessions; \
    chown -R www-data:www-data /var/www/html/uploads /var/lib/php/sessions; \
    chmod 700 /var/lib/php/sessions

# Checks MySQL too — an Apache that answers while the database is unreachable
# is a 500 page, not a healthy service.
HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=3 \
  CMD php -r '$b=@file_get_contents("http://127.0.0.1/health.php"); exit($b==="ok"?0:1);'

ENTRYPOINT ["/usr/local/bin/lp-entrypoint"]
CMD ["apache2-foreground"]
