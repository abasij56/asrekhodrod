#!/usr/bin/env bash
# FTP deploy for asrekhodro-theme with folder swap + rollback.
#
# Flow:
#   1) mirror upload to THEME_NAME__new
#   2) rename live -> THEME_NAME__prev , __new -> live
#   3) curl HEALTH_URL ; on failure rename prev back to live
#
# Required env: FTP_HOST FTP_USER FTP_PASSWORD HEALTH_URL
# Optional:
#   FTP_PORT           default 21
#   FTP_REMOTE_THEMES  default public_html/wp-content/themes
#   THEME_NAME         default asrekhodro-theme
#   LOCAL_THEME        default ./asrekhodro-theme
#   FTP_SSL            default yes (explicit FTPS, like FileZilla)

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

LIVE="$FTP_REMOTE_THEMES/$THEME_NAME"
NEW="$FTP_REMOTE_THEMES/${THEME_NAME}__new"
PREV="$FTP_REMOTE_THEMES/${THEME_NAME}__prev"
FAILED="$FTP_REMOTE_THEMES/${THEME_NAME}__failed"

log() { printf '[ftp-deploy] %s\n' "$*"; }
die() { log "ERROR: $*"; exit 1; }

[[ -n "$FTP_HOST" ]] || die "FTP_HOST required"
[[ -n "$FTP_USER" ]] || die "FTP_USER required"
[[ -n "$FTP_PASSWORD" ]] || die "FTP_PASSWORD required"
[[ -n "$HEALTH_URL" ]] || die "HEALTH_URL required"
[[ -d "$LOCAL_THEME" ]] || die "LOCAL_THEME missing: $LOCAL_THEME"
command -v lftp >/dev/null || die "lftp not installed"

if [[ "$FTP_SSL" == "yes" ]]; then
  SSL_OPTS='set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate no;'
else
  SSL_OPTS='set ftp:ssl-allow no;'
fi

ftp_cmd() {
  # usage: ftp_cmd "fail" | "nofail"   then lftp script body on stdin
  local mode="$1"
  local fail_exit=true
  [[ "$mode" == "nofail" ]] && fail_exit=false
  local body
  body="$(cat)"
  set +e
  lftp -u "$FTP_USER","$FTP_PASSWORD" -p "$FTP_PORT" "ftp://$FTP_HOST" <<EOF
set cmd:fail-exit $fail_exit
set net:max-retries 2
set net:timeout 60
set mirror:parallel-transfer-count 4
$SSL_OPTS
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

rollback() {
  log "ROLLBACK: restoring $PREV -> $LIVE"
  ftp_cmd nofail <<EOF
rm -r $FAILED
mv $LIVE $FAILED
mv $PREV $LIVE
EOF
}

SWAPPED=0
on_error() {
  local code=$?
  log "FAILED (exit $code)"
  if [[ "$SWAPPED" -eq 1 ]]; then
    rollback
  else
    log "Live theme was not swapped"
    ftp_cmd nofail <<EOF
rm -r $NEW
EOF
  fi
  exit "$code"
}
trap on_error ERR

log "Remove leftover staging (if any)"
ftp_cmd nofail <<EOF
rm -r $NEW
rm -r $FAILED
EOF

log "Upload -> $NEW"
ftp_cmd fail <<EOF
mkdir -p $NEW
mirror -R --delete --verbose=1 \
  --exclude-glob .git/ \
  --exclude-glob .cursor/ \
  --exclude-glob tools/ \
  $LOCAL_THEME/ $NEW/
EOF

log "Swap: $LIVE -> $PREV , $NEW -> $LIVE"
ftp_cmd fail <<EOF
rm -r $PREV
mv $LIVE $PREV
mv $NEW $LIVE
EOF
SWAPPED=1

log "Health check: $HEALTH_URL"
if ! health_ok; then
  die "Health check failed"
fi

log "Cleanup previous backup"
ftp_cmd nofail <<EOF
rm -r $PREV
rm -r $FAILED
EOF

trap - ERR
log "OK: theme deployed via FTP"
