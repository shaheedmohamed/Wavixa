#!/bin/bash
# Watches instances/*.env — starts a wavixa-callbridge@<phone> unit per file.
# Laravel CallBridgeManager drops an env file when a CRM session needs calls.
DIR=/var/www/Wavixa/whatsapp-callbridge/instances
while true; do
  for f in "$DIR"/*.env; do
    [ -e "$f" ] || continue
    name=$(basename "$f" .env)
    systemctl is-active --quiet "wavixa-callbridge@$name" \
      || systemctl start "wavixa-callbridge@$name"
  done
  sleep 10
done
