#!/usr/bin/env bash
# FTP deploy for asrekhodro-theme with rollback.
#
# Incremental (default after first success):
#   - reads last deployed git SHA from remote
#   - uploads only files changed since that SHA
#   - on health-check failure, restores those files from backup
#
# Full deploy (first run, missing SHA, or FORCE_FULL_DEPLOY=1):
#   - mirror to THEME_NAME__new, swap with live, health-check, rollback swap
#
# Required env: FTP_HOST FTP_USER FTP_PASSWORD HEALTH_URL
# Optional:
#   FTP_PORT FTP_REMOTE_THEMES THEME_NAME LOCAL_THEME FTP_SSL FORCE_FULL_DEPLOY

set -euo pipefail

FTP_HOST="${FTP_HOST:-}"
FTP_USER="${FTP_USER:-}"
FTP_PASSWORD="${FTP_PASSWORD:-}"
FTP_PORT="${FTP_PORT:-21}"
FTP_REMOTE_THEMES="${FTP_REMOTE_THEMES:-public_html/wp-content/themes}"
THEME_NAME="${THEME_NAME:-asrekhodro-theme}"
LOCAL_THEME="${LOCAL_THEME:-./asrekhodro-theme}"
HEALTH_URL="${HEALTH_URL:-}"
FTP_SSL="${FTP_SSL:-yes}"
FORCE_FULL_DEPLOY="${FORCE_FULL_DEPLOY:-0}"
# From GitHub push event: commit before this push (enables incremental even before remote SHA exists)
BEFORE_SHA="${BEFORE_SHA:-}"

LIVE="$FTP_REMOTE_THEMES/$THEME_NAME"
NEW="$FTP_REMOTE_THEMES/${THEME_NAME}__new"
PREV="$FTP_REMOTE_THEMES/${THEME_NAME}__prev"
FAILED="$FTP_REMOTE_THEMES/${THEME_NAME}__failed"
# Outside public_html so it is not web-accessible
DEPLOY_STATE_DIR="${DEPLOY_STATE_DIR:-asrekhodro-deploy}"
SHA_FILE="$DEPLOY_STATE_DIR/${THEME_NAME}-deploy-sha.txt"
BACKUP_DIR="$(mktemp -d)"
HEAD_SHA="$(git rev-parse HEAD)"

log() { printf '[ftp-deploy] %s\n' "$*"; }
die() { log "ERROR: $*"; exit 1; }

# Remove leftover staging dirs / old SHA files that sat under the web root
cleanup_web_root_artifacts() {
  log "Cleaning leftover theme staging / old public SHA files"
  ftp_cmd nofail <<EOF
rm -r $NEW
rm -r $PREV
rm -r $FAILED
rm $FTP_REMOTE_THEMES/${THEME_NAME}-deploy-sha.txt
rm $FTP_REMOTE_THEMES/.${THEME_NAME}-deploy-sha
EOF
}

cleanup() { rm -rf "$BACKUP_DIR"; }
trap cleanup EXIT

[[ -n "$FTP_HOST" ]] || die "FTP_HOST required"
[[ -n "$FTP_USER" ]] || die "FTP_USER required"
[[ -n "$FTP_PASSWORD" ]] || die "FTP_PASSWORD required"
[[ -n "$HEALTH_URL" ]] || die "HEALTH_URL required"
[[ -d "$LOCAL_THEME" ]] || die "LOCAL_THEME missing: $LOCAL_THEME"
command -v lftp >/dev/null || die "lftp not installed"
command -v git >/dev/null || die "git not installed"

if [[ "$FTP_SSL" == "yes" ]]; then
  SSL_OPTS='set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate no;'
else
  SSL_OPTS='set ftp:ssl-allow no;'
fi

ftp_cmd() {
  local mode="$1"
  local fail_exit=true
  [[ "$mode" == "nofail" ]] && fail_exit=false
  local body
  body="$(cat)"
  set +e
  lftp <<EOF
set cmd:fail-exit $fail_exit
set net:max-retries 2
set net:timeout 60
set mirror:parallel-transfer-count 8
set ftp:use-mdtm false
$SSL_OPTS
open -p $FTP_PORT -u "$FTP_USER","$FTP_PASSWORD" ftp://$FTP_HOST
$body
bye
EOF
  local rc=$?
  set -e
  if [[ "$mode" == "fail" && "$rc" -ne 0 ]]; then
    return "$rc"
  fi
  return 0
}

health_ok() {
  local code
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 -L "$HEALTH_URL" || true)"
  log "Health HTTP $code"
  [[ "$code" =~ ^(200|301|302)$ ]]
}

write_remote_sha() {
  local tmp
  tmp="$(mktemp)"
  printf '%s\n' "$HEAD_SHA" >"$tmp"
  # Directory may already exist (550 File exists) — ignore that, then put.
  ftp_cmd nofail <<EOF
mkdir -p $DEPLOY_STATE_DIR
EOF
  ftp_cmd fail <<EOF
put $tmp -o $SHA_FILE
EOF
  rm -f "$tmp"
  log "Wrote remote deploy SHA $HEAD_SHA -> $SHA_FILE"
}

read_remote_sha() {
  # mktemp creates the file; lftp get fails with "File exists" — use a new path.
  local tmp
  tmp="$(mktemp -u)"
  set +e
  lftp <<EOF >/dev/null
set cmd:fail-exit true
set net:timeout 30
$SSL_OPTS
open -p $FTP_PORT -u "$FTP_USER","$FTP_PASSWORD" ftp://$FTP_HOST
get $SHA_FILE -o $tmp
bye
EOF
  local rc=$?
  set -e
  if [[ "$rc" -ne 0 || ! -f "$tmp" ]]; then
    rm -f "$tmp"
    echo ""
    return 0
  fi
  tr -d '[:space:]' <"$tmp"
  rm -f "$tmp"
}

# ---------- full deploy (swap) ----------
full_deploy() {
  log "FULL deploy (mirror + swap)"
  local SWAPPED=0

  rollback_full() {
    log "ROLLBACK full: restoring $PREV -> $LIVE"
    ftp_cmd nofail <<EOF
rm -r $FAILED
mv $LIVE $FAILED
mv $PREV $LIVE
EOF
  }

  on_error_full() {
    local code=$?
    log "FAILED full deploy (exit $code)"
    if [[ "$SWAPPED" -eq 1 ]]; then
      rollback_full
    else
      ftp_cmd nofail <<EOF
rm -r $NEW
EOF
    fi
    exit "$code"
  }
  trap on_error_full ERR

  ftp_cmd nofail <<EOF
rm -r $NEW
rm -r $FAILED
EOF

  log "Upload -> $NEW"
  ftp_cmd fail <<EOF
mkdir -p $NEW
mirror -R --delete \
  --exclude-glob .git/ \
  --exclude-glob .cursor/ \
  --exclude-glob tools/ \
  --exclude-glob .deploy-sha \
  $LOCAL_THEME/ $NEW/
EOF

  log "Swap live"
  ftp_cmd fail <<EOF
rm -r $PREV
mv $LIVE $PREV
mv $NEW $LIVE
EOF
  SWAPPED=1

  if ! health_ok; then
    die "Health check failed"
  fi

  ftp_cmd nofail <<EOF
rm -r $PREV
rm -r $FAILED
EOF
  write_remote_sha
  trap - ERR
  trap cleanup EXIT
  log "OK: full deploy"
}

# ---------- incremental deploy ----------
incremental_deploy() {
  local old_sha="$1"
  log "INCREMENTAL deploy since $old_sha"

  if ! git cat-file -e "${old_sha}^{commit}" 2>/dev/null; then
    log "Old SHA not in git history; falling back to full deploy"
    full_deploy
    return
  fi

  local mapfile_changes=()
  local status path rel remote_path local_path

  while IFS=$'\t' read -r status path; do
    [[ -n "${path:-}" ]] || continue
    # path is like asrekhodro-theme/foo.php
    rel="${path#asrekhodro-theme/}"
    [[ "$rel" == "$path" && "$path" != asrekhodro-theme/* ]] && continue
    mapfile_changes+=("$status|$rel")
  done < <(git diff --name-status --no-renames "$old_sha" HEAD -- "asrekhodro-theme/")

  if [[ "${#mapfile_changes[@]}" -eq 0 ]]; then
    log "No theme file changes; updating SHA only"
    write_remote_sha
    log "OK: nothing to upload"
    return
  fi

  log "Changed files: ${#mapfile_changes[@]}"

  # Backup current remote versions of files we will overwrite/delete
  log "Backing up remote files for rollback"
  local cmds=""
  for item in "${mapfile_changes[@]}"; do
    status="${item%%|*}"
    rel="${item#*|}"
    remote_path="$LIVE/$rel"
    case "$status" in
      A) ;; # new file, nothing to backup
      M|D)
        mkdir -p "$BACKUP_DIR/$(dirname "$rel")"
        cmds+="get $remote_path -o $BACKUP_DIR/$rel"$'\n'
        ;;
    esac
  done
  if [[ -n "$cmds" ]]; then
    ftp_cmd nofail <<EOF
$cmds
EOF
  fi

  local deployed=0
  rollback_inc() {
    log "ROLLBACK incremental"
    local restore=""
    local f
    # restore backed-up files
    while IFS= read -r -d '' f; do
      rel="${f#$BACKUP_DIR/}"
      restore+="mkdir -p $LIVE/$(dirname "$rel")"$'\n'
      restore+="put $f -o $LIVE/$rel"$'\n'
    done < <(find "$BACKUP_DIR" -type f -print0 2>/dev/null || true)

    # remove files that were newly added in this deploy
    for item in "${mapfile_changes[@]}"; do
      status="${item%%|*}"
      rel="${item#*|}"
      if [[ "$status" == "A" ]]; then
        restore+="rm $LIVE/$rel"$'\n'
      fi
    done

    if [[ -n "$restore" ]]; then
      ftp_cmd nofail <<EOF
$restore
EOF
    fi
  }

  on_error_inc() {
    local code=$?
    log "FAILED incremental (exit $code)"
    if [[ "$deployed" -eq 1 ]]; then
      rollback_inc
    fi
    exit "$code"
  }
  trap on_error_inc ERR

  log "Uploading changed files to live"
  # mkdir may return 550 "File exists" on cPanel even with -p; do it nofail first.
  local mkdir_cmds="" put_cmds="" rm_cmds=""
  for item in "${mapfile_changes[@]}"; do
    status="${item%%|*}"
    rel="${item#*|}"
    local_path="$LOCAL_THEME/$rel"
    case "$status" in
      A|M)
        [[ -f "$local_path" ]] || die "Missing local file: $local_path"
        mkdir_cmds+="mkdir -p $LIVE/$(dirname "$rel")"$'\n'
        put_cmds+="put $local_path -o $LIVE/$rel"$'\n'
        ;;
      D)
        rm_cmds+="rm $LIVE/$rel"$'\n'
        ;;
    esac
  done
  if [[ -n "$mkdir_cmds" ]]; then
    ftp_cmd nofail <<EOF
$mkdir_cmds
EOF
  fi
  if [[ -n "$put_cmds$rm_cmds" ]]; then
    ftp_cmd fail <<EOF
$put_cmds
$rm_cmds
EOF
  fi
  deployed=1

  if ! health_ok; then
    die "Health check failed"
  fi

  write_remote_sha
  trap - ERR
  trap cleanup EXIT
  log "OK: incremental deploy (${#mapfile_changes[@]} files)"
}

# ---------- choose mode ----------
cleanup_web_root_artifacts

REMOTE_SHA="$(read_remote_sha || true)"
OLD_SHA="$REMOTE_SHA"
if [[ -z "$OLD_SHA" && -n "$BEFORE_SHA" && "$BEFORE_SHA" != "0000000000000000000000000000000000000000" ]]; then
  OLD_SHA="$BEFORE_SHA"
  log "No remote SHA; using push before SHA"
fi

log "Base SHA: ${OLD_SHA:-<none>}"
log "HEAD SHA: $HEAD_SHA"

if [[ "$FORCE_FULL_DEPLOY" == "1" || -z "$OLD_SHA" ]]; then
  full_deploy
else
  incremental_deploy "$OLD_SHA"
fi
