#!/usr/bin/env bash
# Runs on the CI runner: connects to the server and runs deploy-remote.sh there.
# Authenticates with SSH_KEY when it is set, otherwise with SSH_PASSWORD.
# Usage: deploy.sh <environment> <branch> <commit>

set -euo pipefail

ENVIRONMENT="${1:?environment required}"
BRANCH="${2:?branch required}"
COMMIT="${3:?commit required}"
SSH_PORT="${SSH_PORT:-22}"

for name in SSH_HOST SSH_USERNAME DEPLOY_PATH; do
    if [ -z "${!name:-}" ]; then
        echo "::error::Set $name for the '$ENVIRONMENT' environment" >&2
        exit 1
    fi
done

if [ -z "${SSH_KEY:-}" ] && [ -z "${SSH_PASSWORD:-}" ]; then
    echo "::error::Set SSH_KEY or SSH_PASSWORD for the '$ENVIRONMENT' environment" >&2
    exit 1
fi

mkdir -p ~/.ssh
chmod 700 ~/.ssh

if [ -n "${SSH_KNOWN_HOSTS:-}" ]; then
    printf '%s\n' "$SSH_KNOWN_HOSTS" > ~/.ssh/known_hosts
else
    ssh-keyscan -p "$SSH_PORT" -H "$SSH_HOST" > ~/.ssh/known_hosts 2>/dev/null
fi

chmod 600 ~/.ssh/known_hosts

ssh_cmd=(ssh -p "$SSH_PORT")

if [ -n "${SSH_KEY:-}" ]; then
    printf '%s\n' "$SSH_KEY" > ~/.ssh/deploy_key
    chmod 600 ~/.ssh/deploy_key
    ssh_cmd+=(-i ~/.ssh/deploy_key -o IdentitiesOnly=yes -o BatchMode=yes)
else
    command -v sshpass >/dev/null || sudo apt-get install -y -qq sshpass
    export SSHPASS="$SSH_PASSWORD"
    ssh_cmd=(sshpass -e "${ssh_cmd[@]}" -o PreferredAuthentications=password -o PubkeyAuthentication=no)
fi

"${ssh_cmd[@]}" "$SSH_USERNAME@$SSH_HOST" \
    "bash -s -- '$DEPLOY_PATH' '$BRANCH' '$COMMIT' '${PHP_BIN:-php}' '${COMPOSER_CMD:-}'" < scripts/deploy-remote.sh
