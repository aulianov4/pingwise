#!/bin/bash
# PingWise agent installer. Run as root:
#   curl -fsSL https://example/agent/install.sh | sudo bash -s -- --token pw_srv_...
set -eu

PINGWISE_URL="__PINGWISE_URL__"
HEARTBEAT_URL="__HEARTBEAT_URL__"
HEARTBEAT_SCRIPT_URL="__HEARTBEAT_SCRIPT_URL__"

if [ "$(id -u)" -ne 0 ]; then
  echo "Запустите от root, например:" >&2
  echo "  curl -fsSL ${PINGWISE_URL}/agent/install.sh | sudo bash -s -- --token TOKEN" >&2
  exit 1
fi

TOKEN=""
while [ "$#" -gt 0 ]; do
  case "$1" in
    --token)
      TOKEN="${2:-}"
      shift 2
      ;;
    --token=*)
      TOKEN="${1#--token=}"
      shift
      ;;
    *)
      echo "Неизвестный аргумент: $1" >&2
      echo "Использование: install.sh --token pw_srv_..." >&2
      exit 1
      ;;
  esac
done

if [ -z "$TOKEN" ]; then
  echo "Нужен --token (выдаётся в панели PingWise при создании сервера)." >&2
  exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "Нужен curl." >&2
  exit 1
fi

if ! command -v systemctl >/dev/null 2>&1; then
  echo "Нужен systemd." >&2
  exit 1
fi

install -d -m 0755 /etc/pingwise
install -d -m 0755 /usr/local/lib/pingwise

umask 077
cat > /etc/pingwise/agent.env <<EOF
PINGWISE_URL=${PINGWISE_URL}
PINGWISE_HEARTBEAT_URL=${HEARTBEAT_URL}
PINGWISE_TOKEN=${TOKEN}
EOF
chmod 600 /etc/pingwise/agent.env

curl -fsSL "$HEARTBEAT_SCRIPT_URL" -o /usr/local/lib/pingwise/heartbeat.sh
chmod 755 /usr/local/lib/pingwise/heartbeat.sh

cat > /etc/systemd/system/pingwise-heartbeat.service <<'EOF'
[Unit]
Description=PingWise server heartbeat
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
EnvironmentFile=/etc/pingwise/agent.env
ExecStart=/usr/local/lib/pingwise/heartbeat.sh
Nice=10

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/pingwise-heartbeat.timer <<'EOF'
[Unit]
Description=PingWise server heartbeat timer

[Timer]
OnBootSec=1min
OnUnitActiveSec=10min
Persistent=true
Unit=pingwise-heartbeat.service

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now pingwise-heartbeat.timer

echo "Агент установлен. Отправляю первый пинг..."
if /usr/local/lib/pingwise/heartbeat.sh; then
  echo
  echo "Готово. Heartbeat каждые 10 минут (systemd timer pingwise-heartbeat.timer)."
  echo "Логи: journalctl -u pingwise-heartbeat.service"
  exit 0
fi

echo
echo "Агент установлен, но первый пинг не прошёл." >&2
echo "Проверьте токен и доступ до ${HEARTBEAT_URL}" >&2
echo "Повтор: /usr/local/lib/pingwise/heartbeat.sh" >&2
echo "Логи: journalctl -u pingwise-heartbeat.service" >&2
exit 1
