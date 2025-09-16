#!/usr/bin/env bash
set -euo pipefail
echo "[VeriBits] Terraform deploy (ECS Fargate + ALB + RDS + Redis + Cognito)"
cd infrastructure/terraform
terraform init
terraform apply -auto-approve
cd -
echo "[VeriBits] Build & push image to ECR:"
echo "  bash scripts/build_api.sh $(terraform -chdir=infrastructure/terraform output -raw ecr_repo) latest"
