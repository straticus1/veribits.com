#!/usr/bin/env bash
set -euo pipefail
REPO_URL="$1"
TAG="${2:-latest}"
docker build -t veribits-api:$TAG -f docker/Dockerfile .
docker tag veribits-api:$TAG "$REPO_URL:$TAG"
echo "Login: aws ecr get-login-password | docker login --username AWS --password-stdin $(dirname $REPO_URL)"
echo "Push:  docker push $REPO_URL:$TAG"
