#!/bin/bash
# PingWise heartbeat agent. Collects disk/RAM/load/uptime and POSTs JSON.
set -eu

ENV_FILE="${PINGWISE_ENV_FILE:-/etc/pingwise/agent.env}"

if [ -f "$ENV_FILE" ]; then
  # shellcheck disable=SC1090
  set -a
  . "$ENV_FILE"
  set +a
fi

TOKEN="${PINGWISE_TOKEN:-}"
URL="${PINGWISE_HEARTBEAT_URL:-}"

if [ -z "$TOKEN" ] || [ -z "$URL" ]; then
  echo "PingWise: задайте PINGWISE_TOKEN и PINGWISE_HEARTBEAT_URL в $ENV_FILE" >&2
  exit 1
fi

json_escape() {
  printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

hostname_value="$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo unknown)"
uptime_seconds="$(awk '{printf "%d", $1}' /proc/uptime)"

load1="$(awk '{print $1}' /proc/loadavg)"
load5="$(awk '{print $2}' /proc/loadavg)"
load15="$(awk '{print $3}' /proc/loadavg)"

if command -v nproc >/dev/null 2>&1; then
  cores="$(nproc)"
else
  cores="$(grep -c '^processor' /proc/cpuinfo 2>/dev/null || echo 1)"
fi

mem_total="$(awk '/^MemTotal:/ {print $2 * 1024}' /proc/meminfo)"
mem_avail="$(awk '/^MemAvailable:/ {print $2 * 1024}' /proc/meminfo)"
swap_total="$(awk '/^SwapTotal:/ {print $2 * 1024}' /proc/meminfo)"
swap_free="$(awk '/^SwapFree:/ {print $2 * 1024}' /proc/meminfo)"

if [ -z "$mem_avail" ]; then
  mem_avail="$(awk '/^MemFree:/ {print $2 * 1024}' /proc/meminfo)"
fi

df_opts=(-B1 --output=fstype,size,used,itotal,iused,target)
df_exclude=(-x tmpfs -x devtmpfs -x overlay -x squashfs -x ramfs -x iso9660 -x proc -x sysfs -x devpts -x cgroup -x cgroup2)

disks_json=""
sep=""

if df_out="$(df "${df_opts[@]}" "${df_exclude[@]}" 2>/dev/null)"; then
  while IFS= read -r line; do
    # skip header
    case "$line" in
      Type*|Filesystem*) continue ;;
    esac
    # fstype size used itotal iused mount...
    fstype="$(printf '%s' "$line" | awk '{print $1}')"
    total="$(printf '%s' "$line" | awk '{print $2}')"
    used="$(printf '%s' "$line" | awk '{print $3}')"
    itotal="$(printf '%s' "$line" | awk '{print $4}')"
    iused="$(printf '%s' "$line" | awk '{print $5}')"
    mount="$(printf '%s' "$line" | awk '{ $1=$2=$3=$4=$5=""; sub(/^ +/, ""); print }')"

    case "$total" in
      ''|*[!0-9]*) continue ;;
    esac

    mount_esc="$(json_escape "$mount")"
    fstype_esc="$(json_escape "$fstype")"
    itotal="${itotal:-0}"
    iused="${iused:-0}"
    case "$itotal" in
      ''|*[!0-9]*) itotal=0 ;;
    esac
    case "$iused" in
      ''|*[!0-9]*) iused=0 ;;
    esac

    disks_json="${disks_json}${sep}{\"mount\":\"${mount_esc}\",\"fstype\":\"${fstype_esc}\",\"total_bytes\":${total},\"used_bytes\":${used},\"inodes_total\":${itotal},\"inodes_used\":${iused}}"
    sep=","
  done <<EOF
$df_out
EOF
fi

if [ -z "$disks_json" ]; then
  disks_json='{"mount":"/","fstype":"unknown","total_bytes":0,"used_bytes":0,"inodes_total":0,"inodes_used":0}'
fi

hostname_esc="$(json_escape "$hostname_value")"
swap_total="${swap_total:-0}"
swap_free="${swap_free:-0}"

payload="{\"hostname\":\"${hostname_esc}\",\"uptime_seconds\":${uptime_seconds},\"cpu\":{\"load1\":${load1},\"load5\":${load5},\"load15\":${load15},\"cores\":${cores}},\"memory\":{\"total_bytes\":${mem_total},\"available_bytes\":${mem_avail},\"swap_total_bytes\":${swap_total},\"swap_free_bytes\":${swap_free}},\"disks\":[${disks_json}],\"agent_version\":\"1.0.0\"}"

attempt=1
max_attempts=3
sleep_s=2

while [ "$attempt" -le "$max_attempts" ]; do
  if curl -fsS -m 20 \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    --data "$payload" \
    "$URL"; then
    echo
    exit 0
  fi
  if [ "$attempt" -lt "$max_attempts" ]; then
    sleep "$sleep_s"
    sleep_s=$((sleep_s * 2))
  fi
  attempt=$((attempt + 1))
done

echo "PingWise: не удалось отправить heartbeat после ${max_attempts} попыток" >&2
exit 1
