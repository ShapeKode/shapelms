#!/bin/zsh
#
# Runs the PHPUnit suite without Docker or wp-env.
#
# Downloads the WordPress test library and core into a temp folder, starts a
# throw-away MySQL 8 server (the one bundled with Local) on 127.0.0.1:33306
# and runs vendor/bin/phpunit against it. Safe to run repeatedly.
#
# Usage: tests/bin/run-local.sh [phpunit args]
#
set -e

PLUGIN_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="${SHAPELMS_TEST_WORK:-$HOME/.cache/shapelms-tests}"
PORT="${SHAPELMS_TEST_PORT:-33306}"
MYSQL_BASE="${SHAPELMS_MYSQL_BASE:-$(ls -d "$HOME/Library/Application Support/Local/lightning-services/mysql-8"*/bin/darwin 2>/dev/null | head -1)}"

if [ -z "$MYSQL_BASE" ] || [ ! -x "$MYSQL_BASE/bin/mysqld" ]; then
	echo "mysqld not found. Set SHAPELMS_MYSQL_BASE to a MySQL base dir (contains bin/mysqld)." >&2
	exit 1
fi

mkdir -p "$WORK/db"

if [ ! -d "$WORK/lib/includes" ]; then
	echo "Downloading WordPress test library…"
	VERSION=$(curl -s https://api.wordpress.org/core/version-check/1.7/ | php -r 'echo json_decode(stream_get_contents(STDIN))->offers[0]->version;')
	mkdir -p "$WORK/lib"
	svn export -q "https://develop.svn.wordpress.org/tags/$VERSION/tests/phpunit/includes/" "$WORK/lib/includes" || svn export -q "https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/" "$WORK/lib/includes"
	svn export -q "https://develop.svn.wordpress.org/tags/$VERSION/tests/phpunit/data/" "$WORK/lib/data" || svn export -q "https://develop.svn.wordpress.org/trunk/tests/phpunit/data/" "$WORK/lib/data"
	curl -sL "https://wordpress.org/wordpress-$VERSION.tar.gz" -o "$WORK/wp.tgz"
	mkdir -p "$WORK/wordpress" && tar -xzf "$WORK/wp.tgz" -C "$WORK/wordpress" --strip-components=1 && rm -f "$WORK/wp.tgz"
fi

cat > "$WORK/lib/wp-tests-config.php" <<PHP
<?php
define( 'ABSPATH', '$WORK/wordpress/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_TESTS_MULTISITE', false );
define( 'WP_DEBUG', true );
define( 'DB_NAME', 'shapelms_tests' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '127.0.0.1:$PORT' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
PHP

if [ ! -d "$WORK/db/data" ]; then
	echo "Initialising MySQL data dir…"
	"$MYSQL_BASE/bin/mysqld" --no-defaults --initialize-insecure --datadir="$WORK/db/data" --basedir="$MYSQL_BASE" --log-error="$WORK/db/init.log"
fi

STARTED=0
if ! "$MYSQL_BASE/bin/mysql" -uroot -h127.0.0.1 -P"$PORT" -e "SELECT 1" >/dev/null 2>&1; then
	echo "Starting MySQL on 127.0.0.1:$PORT…"
	"$MYSQL_BASE/bin/mysqld" --no-defaults --datadir="$WORK/db/data" --basedir="$MYSQL_BASE" --socket=/tmp/shapelms-tests-mysql.sock --bind-address=127.0.0.1 --port="$PORT" --pid-file="$WORK/db/mysqld.pid" --log-error="$WORK/db/error.log" --lower-case-table-names=2 --sql-mode="" --default-authentication-plugin=mysql_native_password &
	STARTED=1
	for i in $(seq 1 30); do
		"$MYSQL_BASE/bin/mysql" -uroot -h127.0.0.1 -P"$PORT" -e "SELECT 1" >/dev/null 2>&1 && break
		sleep 1
	done
fi

"$MYSQL_BASE/bin/mysql" -uroot -h127.0.0.1 -P"$PORT" -e "CREATE DATABASE IF NOT EXISTS shapelms_tests;"

set +e
cd "$PLUGIN_DIR"
WP_TESTS_DIR="$WORK/lib" vendor/bin/phpunit "$@"
STATUS=$?
set -e

if [ "$STARTED" = "1" ] && [ -f "$WORK/db/mysqld.pid" ]; then
	kill "$(cat "$WORK/db/mysqld.pid")" 2>/dev/null || true
fi

exit $STATUS
