#!/usr/bin/env bash
set -euo pipefail
echo "[VeriBits] Local dev setup"
cp -n app/config/.env.example app/config/.env || true
php -S 127.0.0.1:8080 -t app/public
