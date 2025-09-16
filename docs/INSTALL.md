# INSTALL

## Requirements
- PHP 8.2+
- Terraform 1.6+
- AWS credentials (`aws configure`)
- Docker
- Node.js (for dashboard), npm

## Local Dev (API)
- `bash scripts/setup.sh`
- Health: `curl localhost:8080/api/v1/health`

## Dashboard
- `cd frontend && npm install && npm run dev` → http://localhost:3000

## ECS Fargate Deploy
1. `bash scripts/deploy_all.sh`
2. Build & push API image:
   `bash scripts/build_api.sh $(terraform -chdir=infrastructure/terraform output -raw ecr_repo) latest`
3. Access ALB DNS from `terraform output alb_dns`

## Ansible
- `ansible-playbook -i ansible/inventory.ini ansible/site.yml`
