#!/usr/bin/env bash
#
# sync-production-db.sh — Mirror the ProjectHub production PostgreSQL schema(s)
# from the production host into the local PostgreSQL server, following the same
# dump/drop/restore workflow used by the bizrun-suite repository.
#
# What it does
# ------------
# 1. Reads local DB connection details from `.env` (DB_HOST/PORT/DATABASE/...)
# 2. Backs up the current local schemas to `tmp/db-sync/<timestamp>/local/`
# 3. Dumps the selected production schemas to `.../production/`
# 4. Drops the matching local schemas (except preserved patterns)
# 5. Restores the production dump into the local database
#
# Defaults
# --------
# - Production host: `fincosg3` (override with --prod-host or PROJECTHUB_PROD_HOST)
# - Database: value of DB_DATABASE in `.env` (typically `projecthub`)
# - Schema: value of DB_SCHEMA in `.env` (typically `root`)
#   Add --all-schemas to sync every non-system schema found in production, or
#   --schema NAME (repeatable) to pick specific ones.
# - Preserved local schema pattern: `test` (disable with --no-preserve-test)
#   A preserved schema is left untouched locally; if it also exists in
#   production, that production copy is skipped (not dumped/restored) rather
#   than aborting the sync.
# - `public` is always excluded intentionally
# - Only schemas being restored are dropped locally. Add --mirror to also drop
#   local schemas that no longer exist in production.
#
# Notes
# -----
# - This syncs the database only. Uploaded files on the `projecthub_private`
#   disk (storage/) are NOT copied.
# - Production credentials default to the local ones from `.env`. Override with
#   --prod-user / --prod-password / --prod-database or the PROJECTHUB_PROD_*
#   environment variables when they differ.
#
# Usage:
#   bash scripts/sync-production-db.sh --dry-run
#   bash scripts/sync-production-db.sh              # preview only
#   bash scripts/sync-production-db.sh --yes
#   bash scripts/sync-production-db.sh --yes --all-schemas --mirror
#   bash scripts/sync-production-db.sh --yes --prod-host fincosg3.fincosoft.com
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"

DRY_RUN=false
YES=false
MIRROR=false
ALL_SCHEMAS=false
PRESERVE_TEST_SCHEMA=true

PROD_HOST="${PROJECTHUB_PROD_HOST:-fincosg3}"
PROD_PORT="${PROJECTHUB_PROD_PORT:-}"
PROD_DB="${PROJECTHUB_PROD_DATABASE:-}"
PROD_USER="${PROJECTHUB_PROD_USERNAME:-}"
PROD_PASSWORD="${PROJECTHUB_PROD_PASSWORD:-}"

ENV_FILE="${REPO_ROOT}/.env"
BACKUP_ROOT="${REPO_ROOT}/tmp/db-sync"

LOCAL_HOST=""
LOCAL_PORT=""
LOCAL_DB=""
LOCAL_USER=""
LOCAL_PASSWORD=""
LOCAL_SCHEMA=""

declare -a USER_PRESERVE_PATTERNS=()
declare -a REQUESTED_SCHEMAS=()
declare -a PRESERVE_PATTERNS=()
declare -a SOURCE_SCHEMAS=()

usage() {
    cat <<'EOF'
Usage:
  scripts/sync-production-db.sh [options]

Synchronize the ProjectHub production PostgreSQL schema(s) from the production
host into the local PostgreSQL server. The script:

1. Backs up the current local schemas
2. Dumps the selected production schemas
3. Drops the local copies of those schemas (except preserved patterns)
4. Restores the production dump locally

Defaults:
  - Production host: fincosg3
  - Local connection/auth: inferred from .env
  - Production connection: same database/user/password as local unless overridden
  - Schema: DB_SCHEMA from .env (typically root)
  - Preserved local schemas: test (also skipped if present in production)
  - The PostgreSQL public schema is excluded intentionally

Options:
  --yes                       Execute destructive steps
  --dry-run                   Print the actions without changing anything
  --prod-host HOST            Override the production host
  --prod-port PORT            Override the production port
  --prod-database NAME        Override the production database name
  --prod-user NAME            Override the production DB user
  --prod-password VALUE       Override the production DB password
  --env-file PATH             Read local connection details from this file
  --backup-root PATH          Directory for dumps and schema manifests
  --schema NAME               Sync a specific schema; repeatable
  --all-schemas               Sync every non-system schema found in production
  --mirror                    Also drop local schemas absent from production
  --preserve-schema PATTERN   Preserve an extra local schema; repeatable
  --no-preserve-test          Do not preserve the local test schema
  --help                      Show this help

Examples:
  scripts/sync-production-db.sh --dry-run
  scripts/sync-production-db.sh --yes
  scripts/sync-production-db.sh --yes --all-schemas --mirror
  scripts/sync-production-db.sh --yes --schema root --preserve-schema scratch_*
EOF
}

log() {
    printf '[sync] %s\n' "$*"
}

warn() {
    printf '[sync][warn] %s\n' "$*" >&2
}

die() {
    printf '[sync][error] %s\n' "$*" >&2
    exit 1
}

get_env_value() {
    local file="$1"
    local key="$2"

    [[ -f "$file" ]] || return 1

    awk -F= -v wanted="$key" '
        $1 == wanted {
            sub(/^[^=]*=/, "", $0)
            sub(/[[:space:]]+$/, "", $0)
            if ($0 ~ /^".*"$/ || $0 ~ /^'\''.*'\''$/) {
                $0 = substr($0, 2, length($0) - 2)
            }
            print $0
            exit
        }
    ' "$file"
}

first_non_empty() {
    local value
    for value in "$@"; do
        if [[ -n "${value}" ]]; then
            printf '%s' "${value}"
            return 0
        fi
    done

    return 1
}

require_command() {
    local cmd="$1"
    command -v "$cmd" >/dev/null 2>&1 || die "Missing required command: ${cmd}"
}

schema_name_is_safe() {
    [[ "$1" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]
}

db_name_is_safe() {
    [[ "$1" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]
}

schema_matches_any_pattern() {
    local schema="$1"
    shift || true
    local pattern

    for pattern in "$@"; do
        if [[ "$schema" == $pattern ]]; then
            return 0
        fi
    done

    return 1
}

array_contains() {
    local needle="$1"
    shift || true
    local item

    for item in "$@"; do
        if [[ "$item" == "$needle" ]]; then
            return 0
        fi
    done

    return 1
}

read_command_lines() {
    local target_name="$1"
    shift

    local output
    output="$("$@")"

    if [[ -z "$output" ]]; then
        eval "${target_name}=()"
        return 0
    fi

    local old_ifs="$IFS"
    IFS=$'\n'
    # shellcheck disable=SC2206
    local items=($output)
    IFS="$old_ifs"

    eval "${target_name}=(\"\${items[@]}\")"
}

effective_preserve_patterns() {
    local patterns=()

    if [[ "$PRESERVE_TEST_SCHEMA" == "true" ]]; then
        patterns+=("test")
    fi

    if [[ ${#USER_PRESERVE_PATTERNS[@]} -gt 0 ]]; then
        patterns+=("${USER_PRESERVE_PATTERNS[@]}")
    fi

    if [[ ${#patterns[@]} -gt 0 ]]; then
        printf '%s\n' "${patterns[@]}"
    fi
}

parse_args() {
    while (($# > 0)); do
        case "$1" in
            --yes)
                YES=true
                shift
                ;;
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            --prod-host)
                [[ $# -ge 2 ]] || die "--prod-host requires a value"
                PROD_HOST="$2"
                shift 2
                ;;
            --prod-port)
                [[ $# -ge 2 ]] || die "--prod-port requires a value"
                PROD_PORT="$2"
                shift 2
                ;;
            --prod-database)
                [[ $# -ge 2 ]] || die "--prod-database requires a value"
                PROD_DB="$2"
                shift 2
                ;;
            --prod-user)
                [[ $# -ge 2 ]] || die "--prod-user requires a value"
                PROD_USER="$2"
                shift 2
                ;;
            --prod-password)
                [[ $# -ge 2 ]] || die "--prod-password requires a value"
                PROD_PASSWORD="$2"
                shift 2
                ;;
            --env-file)
                [[ $# -ge 2 ]] || die "--env-file requires a value"
                ENV_FILE="$2"
                shift 2
                ;;
            --backup-root)
                [[ $# -ge 2 ]] || die "--backup-root requires a value"
                BACKUP_ROOT="$2"
                shift 2
                ;;
            --schema)
                [[ $# -ge 2 ]] || die "--schema requires a value"
                REQUESTED_SCHEMAS+=("$2")
                shift 2
                ;;
            --all-schemas)
                ALL_SCHEMAS=true
                shift
                ;;
            --mirror)
                MIRROR=true
                shift
                ;;
            --preserve-schema)
                [[ $# -ge 2 ]] || die "--preserve-schema requires a value"
                USER_PRESERVE_PATTERNS+=("$2")
                shift 2
                ;;
            --no-preserve-test)
                PRESERVE_TEST_SCHEMA=false
                shift
                ;;
            --help|-h)
                usage
                exit 0
                ;;
            *)
                die "Unknown option: $1"
                ;;
        esac
    done
}

run_with_password() {
    local password="$1"
    shift

    if [[ "$DRY_RUN" == "true" ]]; then
        printf '+ PGPASSWORD=***'
        local arg
        for arg in "$@"; do
            printf ' %q' "$arg"
        done
        printf '\n'
        return 0
    fi

    PGPASSWORD="$password" "$@"
}

write_list_file() {
    local path="$1"
    shift || true

    if [[ "$DRY_RUN" == "true" ]]; then
        log "Would write schema list: ${path}"
        return 0
    fi

    mkdir -p "$(dirname "$path")"
    printf '%s\n' "$@" >"$path"
}

load_config() {
    [[ -f "$ENV_FILE" ]] || die "Missing ${ENV_FILE} (use --env-file to point elsewhere)"

    LOCAL_HOST="$(first_non_empty "$(get_env_value "$ENV_FILE" DB_HOST || true)" "127.0.0.1")"
    LOCAL_PORT="$(first_non_empty "$(get_env_value "$ENV_FILE" DB_PORT || true)" "5432")"
    LOCAL_DB="$(first_non_empty "$(get_env_value "$ENV_FILE" DB_DATABASE || true)" "projecthub")"
    LOCAL_USER="$(get_env_value "$ENV_FILE" DB_USERNAME || true)"
    LOCAL_PASSWORD="$(get_env_value "$ENV_FILE" DB_PASSWORD || true)"
    LOCAL_SCHEMA="$(first_non_empty "$(get_env_value "$ENV_FILE" DB_SCHEMA || true)" "root")"

    [[ -n "$LOCAL_USER" ]] || die "DB_USERNAME not found in ${ENV_FILE}"
    db_name_is_safe "$LOCAL_DB" || die "Unsafe local database name: ${LOCAL_DB}"
    schema_name_is_safe "$LOCAL_SCHEMA" || die "Unsafe DB_SCHEMA value: ${LOCAL_SCHEMA}"

    PROD_PORT="$(first_non_empty "$PROD_PORT" "$LOCAL_PORT")"
    PROD_DB="$(first_non_empty "$PROD_DB" "$LOCAL_DB")"
    PROD_USER="$(first_non_empty "$PROD_USER" "$LOCAL_USER")"
    PROD_PASSWORD="$(first_non_empty "$PROD_PASSWORD" "$LOCAL_PASSWORD" || true)"

    db_name_is_safe "$PROD_DB" || die "Unsafe production database name: ${PROD_DB}"

    if [[ ${#REQUESTED_SCHEMAS[@]} -gt 0 ]]; then
        local schema
        for schema in "${REQUESTED_SCHEMAS[@]}"; do
            schema_name_is_safe "$schema" || die "Unsafe schema name requested: ${schema}"
            [[ "$schema" != "public" ]] || die "Refusing to sync the public schema"
        done
    fi
}

list_app_schemas() {
    local host="$1"
    local port="$2"
    local db="$3"
    local user="$4"
    local password="$5"

    db_name_is_safe "$db" || die "Refusing unexpected database name: ${db}"

    PGPASSWORD="$password" psql \
        -h "$host" \
        -p "$port" \
        -U "$user" \
        -d "$db" \
        -At \
        -v ON_ERROR_STOP=1 \
        -c "select schema_name
              from information_schema.schemata
             where schema_name <> 'information_schema'
               and schema_name not like 'pg_%'
               and schema_name <> 'public'
             order by schema_name"
}

local_database_exists() {
    PGPASSWORD="$LOCAL_PASSWORD" psql \
        -h "$LOCAL_HOST" \
        -p "$LOCAL_PORT" \
        -U "$LOCAL_USER" \
        -d postgres \
        -At \
        -v ON_ERROR_STOP=1 \
        -c "select 1 from pg_database where datname = '${LOCAL_DB}'" | grep -q '^1$'
}

create_local_database() {
    log "Local database ${LOCAL_DB} does not exist; creating it"

    run_with_password \
        "$LOCAL_PASSWORD" \
        psql \
        -h "$LOCAL_HOST" \
        -p "$LOCAL_PORT" \
        -U "$LOCAL_USER" \
        -d postgres \
        -v ON_ERROR_STOP=1 \
        -c "CREATE DATABASE \"${LOCAL_DB}\""
}

dump_selected_schemas() {
    local host="$1"
    local port="$2"
    local db="$3"
    local user="$4"
    local password="$5"
    local output="$6"
    shift 6
    local schemas=("$@")

    if ((${#schemas[@]} == 0)); then
        warn "No schemas to dump for ${db} on ${host}; skipping ${output}"
        return 0
    fi

    local args=(
        pg_dump
        -h "$host"
        -p "$port"
        -U "$user"
        -d "$db"
        -Fc
        --no-owner
        --no-privileges
        -f "$output"
    )

    local schema
    for schema in "${schemas[@]}"; do
        schema_name_is_safe "$schema" || die "Refusing unexpected schema name: ${schema}"
        args+=(--schema="$schema")
    done

    run_with_password "$password" "${args[@]}"
}

drop_local_schema() {
    local schema="$1"

    schema_name_is_safe "$schema" || die "Refusing unexpected schema name: ${schema}"

    run_with_password \
        "$LOCAL_PASSWORD" \
        psql \
        -h "$LOCAL_HOST" \
        -p "$LOCAL_PORT" \
        -U "$LOCAL_USER" \
        -d "$LOCAL_DB" \
        -v ON_ERROR_STOP=1 \
        -c "DROP SCHEMA IF EXISTS \"${schema}\" CASCADE"
}

restore_dump_locally() {
    local dump_file="$1"

    run_with_password \
        "$LOCAL_PASSWORD" \
        pg_restore \
        -h "$LOCAL_HOST" \
        -p "$LOCAL_PORT" \
        -U "$LOCAL_USER" \
        -d "$LOCAL_DB" \
        --no-owner \
        --no-privileges \
        --clean \
        --if-exists \
        "$dump_file"
}

# Selects the production schemas to sync. Prints one "sync <schema>" or
# "skip <schema>" line per selected schema so callers can split the result
# without bash 4 namerefs (macOS ships bash 3.2).
classify_source_schemas() {
    local schema

    for schema in "${SOURCE_SCHEMAS[@]}"; do
        if ((${#REQUESTED_SCHEMAS[@]} > 0)); then
            array_contains "$schema" "${REQUESTED_SCHEMAS[@]}" || continue
        elif [[ "$ALL_SCHEMAS" != "true" ]]; then
            [[ "$schema" == "$LOCAL_SCHEMA" ]] || continue
        fi

        if schema_matches_any_pattern "$schema" "${PRESERVE_PATTERNS[@]+"${PRESERVE_PATTERNS[@]}"}"; then
            printf 'skip %s\n' "$schema"
        else
            printf 'sync %s\n' "$schema"
        fi
    done
}

join_or_none() {
    if (($# == 0)); then
        printf '<none>'
    else
        printf '%s' "$*"
    fi
}

main() {
    parse_args "$@"

    require_command psql
    require_command pg_dump
    require_command pg_restore

    load_config

    read_command_lines PRESERVE_PATTERNS effective_preserve_patterns

    log "Production: ${PROD_USER}@${PROD_HOST}:${PROD_PORT}/${PROD_DB}"
    log "Local:      ${LOCAL_USER}@${LOCAL_HOST}:${LOCAL_PORT}/${LOCAL_DB}"

    if ((${#REQUESTED_SCHEMAS[@]} > 0)); then
        log "Requested schemas: ${REQUESTED_SCHEMAS[*]}"
    elif [[ "$ALL_SCHEMAS" == "true" ]]; then
        log "Requested schemas: <all non-system production schemas>"
    else
        log "Requested schemas: ${LOCAL_SCHEMA} (from DB_SCHEMA)"
    fi

    if ((${#PRESERVE_PATTERNS[@]} > 0)); then
        log "Preserved local schema patterns: ${PRESERVE_PATTERNS[*]}"
    else
        log "Preserved local schema patterns: none"
    fi

    read_command_lines SOURCE_SCHEMAS list_app_schemas \
        "$PROD_HOST" "$PROD_PORT" "$PROD_DB" "$PROD_USER" "$PROD_PASSWORD"

    ((${#SOURCE_SCHEMAS[@]} > 0)) || die "No source schemas found on ${PROD_HOST} for ${PROD_DB}; aborting"

    local local_db_exists=true
    local local_schemas=()
    if local_database_exists; then
        read_command_lines local_schemas list_app_schemas \
            "$LOCAL_HOST" "$LOCAL_PORT" "$LOCAL_DB" "$LOCAL_USER" "$LOCAL_PASSWORD"
    else
        local_db_exists=false
        warn "Local database ${LOCAL_DB} not found; it will be created"
    fi

    local classified=()
    read_command_lines classified classify_source_schemas

    local sync_schemas=()
    local skipped_schemas=()
    local entry
    for entry in "${classified[@]+"${classified[@]}"}"; do
        case "$entry" in
            sync\ *) sync_schemas+=("${entry#sync }") ;;
            skip\ *) skipped_schemas+=("${entry#skip }") ;;
        esac
    done

    ((${#sync_schemas[@]} > 0)) || die "No production schemas matched the selection; nothing to sync"

    local drop_schemas=()
    local schema
    for schema in "${local_schemas[@]+"${local_schemas[@]}"}"; do
        if schema_matches_any_pattern "$schema" "${PRESERVE_PATTERNS[@]+"${PRESERVE_PATTERNS[@]}"}"; then
            continue
        fi

        if array_contains "$schema" "${sync_schemas[@]}" || [[ "$MIRROR" == "true" ]]; then
            drop_schemas+=("$schema")
        fi
    done

    log "  production schemas: ${#SOURCE_SCHEMAS[@]} -> ${SOURCE_SCHEMAS[*]}"
    log "  local schemas:      ${#local_schemas[@]} -> $(join_or_none "${local_schemas[@]+"${local_schemas[@]}"}")"
    log "  will sync:          ${sync_schemas[*]}"
    log "  will drop locally:  $(join_or_none "${drop_schemas[@]+"${drop_schemas[@]}"}")"
    if ((${#skipped_schemas[@]} > 0)); then
        log "  skipped (preserved, left untouched): ${skipped_schemas[*]}"
    fi

    if [[ "$YES" != "true" && "$DRY_RUN" != "true" ]]; then
        die "Preview complete. Re-run with --yes to perform the backup/drop/restore workflow."
    fi

    local timestamp
    timestamp="$(date +%Y%m%d-%H%M%S)"

    local local_dir="${BACKUP_ROOT}/${timestamp}/local"
    local production_dir="${BACKUP_ROOT}/${timestamp}/production"

    if [[ "$DRY_RUN" != "true" ]]; then
        mkdir -p "$local_dir" "$production_dir"
    else
        log "Would create backup directories under ${BACKUP_ROOT}/${timestamp}"
    fi

    write_list_file "${local_dir}/${LOCAL_DB}.schemas.txt" "${local_schemas[@]+"${local_schemas[@]}"}"
    write_list_file "${production_dir}/${PROD_DB}.schemas.txt" "${SOURCE_SCHEMAS[@]}"

    if [[ "$local_db_exists" == "true" ]]; then
        dump_selected_schemas \
            "$LOCAL_HOST" \
            "$LOCAL_PORT" \
            "$LOCAL_DB" \
            "$LOCAL_USER" \
            "$LOCAL_PASSWORD" \
            "${local_dir}/${LOCAL_DB}.dump" \
            "${local_schemas[@]+"${local_schemas[@]}"}"
    else
        create_local_database
    fi

    dump_selected_schemas \
        "$PROD_HOST" \
        "$PROD_PORT" \
        "$PROD_DB" \
        "$PROD_USER" \
        "$PROD_PASSWORD" \
        "${production_dir}/${PROD_DB}.dump" \
        "${sync_schemas[@]}"

    for schema in "${drop_schemas[@]+"${drop_schemas[@]}"}"; do
        drop_local_schema "$schema"
    done

    restore_dump_locally "${production_dir}/${PROD_DB}.dump"

    log "Backups written to ${BACKUP_ROOT}/${timestamp}"
    log "Sync complete"
}

main "$@"
