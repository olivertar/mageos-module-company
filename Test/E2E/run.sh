#!/bin/bash
# Runs e2e.php with outgoing mail redirected to Mailpit; the original SMTP settings are put back afterwards.
#   bash app/code/MageOS/Company/Test/E2E/run.sh
# E2E_SMTP_HOST / E2E_SMTP_PORT: Mailpit SMTP server (default mailbox:1025). See e2e.php for the other variables.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../../../../../.." || exit 1
SMTP_HOST="${E2E_SMTP_HOST:-mailbox}"
SMTP_PORT="${E2E_SMTP_PORT:-1025}"
PATHS="transport host port auth ssl"

declare -A ORIG
for p in $PATHS; do
    if value="$(php bin/magento config:show "system/smtp/$p" 2>/dev/null)"; then
        ORIG[$p]="$value"
    else
        ORIG[$p]="__unset__"
    fi
done

restore() {
    for p in $PATHS; do
        if [ "${ORIG[$p]}" = "__unset__" ]; then
            php -r 'require "app/bootstrap.php";
                \Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getObjectManager()
                    ->get(\Magento\Framework\App\Config\Storage\WriterInterface::class)->delete($argv[1]);' "system/smtp/$p"
        else
            php bin/magento config:set "system/smtp/$p" "${ORIG[$p]}" >/dev/null
        fi
    done
    php bin/magento cache:clean config >/dev/null
    echo "smtp restored -> $(php bin/magento config:show system/smtp/host 2>/dev/null || echo default)"
}
trap restore EXIT

# plain config:set (no --lock-env): changing env.php desyncs the config hash and breaks the storefront
php bin/magento config:set system/smtp/transport smtp >/dev/null
php bin/magento config:set system/smtp/host "$SMTP_HOST" >/dev/null
php bin/magento config:set system/smtp/port "$SMTP_PORT" >/dev/null
php bin/magento config:set system/smtp/auth none >/dev/null
php bin/magento config:set system/smtp/ssl none >/dev/null
php bin/magento cache:clean config >/dev/null
echo "smtp -> $(php bin/magento config:show system/smtp/host):$(php bin/magento config:show system/smtp/port)"

php -d memory_limit=2G "$SCRIPT_DIR/e2e.php"
rc=$?
restore
trap - EXIT
exit $rc
