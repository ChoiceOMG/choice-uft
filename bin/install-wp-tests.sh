#!/usr/bin/env bash
# Install WordPress and its test suite into a temp directory.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
#
# Examples:
#   bin/install-wp-tests.sh wordpress_test root ''
#   bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
#   bin/install-wp-tests.sh wordpress_test root '' localhost 6.4

if [ $# -lt 3 ]; then
    echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
    if [ $(which curl) ]; then
        curl -s "$1" > "$2"
    elif [ $(which wget) ]; then
        wget -nv -O "$2" "$1"
    fi
}

# Resolve the SVN tag to use for the test suite.
if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
    WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
    WP_TESTS_TAG="tags/$WP_VERSION"
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
    WP_TESTS_TAG="trunk"
else
    # Fetch the latest stable tag.
    download http://develop.svn.wordpress.org/tags/ /tmp/wp-tags.html
    WP_TESTS_TAG=$(grep -o '"tags/[0-9]*\.[0-9]*\.[0-9]*/"' /tmp/wp-tags.html | tail -1 | grep -o '[0-9]*\.[0-9]*\.[0-9]*')
    WP_TESTS_TAG="tags/$WP_TESTS_TAG"
fi

set -ex

install_wp() {
    if [ -d $WP_CORE_DIR ]; then
        return
    fi
    mkdir -p $WP_CORE_DIR
    if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
        svn export --quiet https://develop.svn.wordpress.org/trunk/src/ $WP_CORE_DIR
    else
        if [ $WP_VERSION == 'latest' ]; then
            local ARCHIVE_NAME='latest'
        else
            local ARCHIVE_NAME="wordpress-$WP_VERSION"
        fi
        download https://wordpress.org/${ARCHIVE_NAME}.tar.gz $TMPDIR/wordpress.tar.gz
        tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C $WP_CORE_DIR
    fi
}

install_test_suite() {
    if [[ $(uname -s) == 'Darwin' ]]; then
        local ioption='-i .bak'
    else
        local ioption='-i'
    fi

    if [ -d $WP_TESTS_DIR ]; then
        svn up --quiet $WP_TESTS_DIR
    else
        svn co --quiet https://develop.svn.wordpress.org/$WP_TESTS_TAG/tests/phpunit $WP_TESTS_DIR
    fi

    if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
        download https://develop.svn.wordpress.org/$WP_TESTS_TAG/wp-tests-config-sample.php "$WP_TESTS_DIR/wp-tests-config.php"
        WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s://$:/:")
        sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
        sed $ioption "s/youremptydbname/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed $ioption "s/yourusername/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed $ioption "s/yourpassword/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
    fi
}

install_db() {
    if [ ${SKIP_DB_CREATE} = "true" ]; then
        return
    fi
    local PARTS=(${DB_HOST//\:/ })
    local DB_HOSTNAME=${PARTS[0]}
    local DB_SOCK_OR_PORT=${PARTS[1]}
    local EXTRA=""

    if ! [ -z $DB_SOCK_OR_PORT ]; then
        if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
            EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
        else
            EXTRA=" --socket=$DB_SOCK_OR_PORT"
        fi
    elif ! [ -z $DB_HOSTNAME ]; then
        EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
    fi

    mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA \
        -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
}

install_wp
install_test_suite
install_db
