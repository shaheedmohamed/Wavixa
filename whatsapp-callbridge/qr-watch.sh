#!/bin/bash
cd /var/www/Wavixa/whatsapp-callbridge
LAST=""
while true; do
  if [ -f out/qr.txt ]; then
    H=$(md5sum out/qr.txt | cut -d' ' -f1)
    if [ "$H" != "$LAST" ]; then
      LAST="$H"
      node -e "require('qrcode').toFile('/var/www/Wavixa/backend/public/cb-qr-7f3a.png', require('fs').readFileSync('out/qr.txt','utf8'), {scale:8,margin:2}).then(()=>console.log('QR png updated', new Date().toISOString()))"
    fi
  fi
  sleep 2
done
