#!/usr/bin/env bash
# VeriBits Super Deploy Script
# Deploys to After Dark Systems VPC with full infrastructure setup

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Banner
echo -e "${BLUE}"
cat << "EOF"
╦  ╦┌─┐┬─┐┬┌┐ ┬┌┬┐┌─┐
╚╗╔╝├┤ ├┬┘│├┴┐│ │ └─┐
 ╚╝ └─┘┴└─┴└─┘┴ ┴ └─┘
Super Deploy to After Dark Systems VPC
EOF
echo -e "${NC}"

# Check prerequisites
log_info "Checking prerequisites..."

if ! command -v aws &> /dev/null; then
    log_error "AWS CLI not found. Please install it first."
    exit 1
fi

if ! command -v terraform &> /dev/null; then
    log_error "Terraform not found. Please install it first."
    exit 1
fi

if ! command -v docker &> /dev/null; then
    log_error "Docker not found. Please install it first."
    exit 1
fi

if ! command -v ansible-playbook &> /dev/null; then
    log_warn "Ansible not found. Will use direct deployment."
    USE_ANSIBLE=false
else
    USE_ANSIBLE=true
fi

# Check AWS credentials
log_info "Verifying AWS credentials..."
if ! aws sts get-caller-identity &> /dev/null; then
    log_error "AWS credentials not configured. Run 'aws configure' first."
    exit 1
fi

AWS_ACCOUNT=$(aws sts get-caller-identity --query Account --output text)
AWS_REGION=$(aws configure get region || echo "us-east-1")

log_success "AWS Account: $AWS_ACCOUNT"
log_success "AWS Region: $AWS_REGION"

# Environment variables
export TF_VAR_aws_region="$AWS_REGION"
export TF_VAR_jwt_secret="${JWT_SECRET:-$(openssl rand -base64 32)}"
export TF_VAR_db_password="${DB_PASSWORD:-$(openssl rand -base64 24)}"
export TF_VAR_id_verify_api_key="${ID_VERIFY_API_KEY:-}"

# Save generated secrets
if [ ! -f "$PROJECT_ROOT/.env.production" ]; then
    log_info "Creating .env.production with generated secrets..."
    cat > "$PROJECT_ROOT/.env.production" << EOF
JWT_SECRET=$TF_VAR_jwt_secret
DB_PASSWORD=$TF_VAR_db_password
ID_VERIFY_API_KEY=$TF_VAR_id_verify_api_key
AWS_REGION=$AWS_REGION
EOF
    chmod 600 "$PROJECT_ROOT/.env.production"
    log_success "Secrets saved to .env.production"
fi

# Deploy with Ansible if available
if [ "$USE_ANSIBLE" = true ]; then
    log_info "Deploying with Ansible..."
    cd "$PROJECT_ROOT"
    ansible-playbook ansible/deploy-veribits.yml
    log_success "Ansible deployment complete!"
else
    # Manual deployment
    log_info "Deploying infrastructure with Terraform..."
    cd "$PROJECT_ROOT/infrastructure/terraform"

    terraform init
    terraform plan -out=tfplan
    terraform apply tfplan

    # Get outputs
    ECR_REPO=$(terraform output -raw ecr_repo)
    ALB_DNS=$(terraform output -raw alb_dns)

    log_success "Infrastructure deployed!"
    log_info "ECR Repository: $ECR_REPO"
    log_info "ALB DNS: $ALB_DNS"

    # Build and push Docker image
    log_info "Building Docker image..."
    cd "$PROJECT_ROOT"

    # ECR login
    aws ecr get-login-password --region "$AWS_REGION" | \
        docker login --username AWS --password-stdin "$ECR_REPO"

    # Build
    docker build -t veribits-api -f docker/Dockerfile .
    docker tag veribits-api:latest "$ECR_REPO:latest"

    # Push
    log_info "Pushing image to ECR..."
    docker push "$ECR_REPO:latest"

    # Update ECS service
    log_info "Updating ECS service..."
    aws ecs update-service \
        --cluster veribits-cluster \
        --service veribits-api-svc \
        --force-new-deployment \
        --region "$AWS_REGION" > /dev/null

    log_info "Waiting for service to stabilize..."
    aws ecs wait services-stable \
        --cluster veribits-cluster \
        --services veribits-api-svc \
        --region "$AWS_REGION"

    log_success "Deployment complete!"
fi

# Display summary
echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}   VeriBits Deployment Successful!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo -e "${BLUE}URLs:${NC}"
echo "  https://veribits.com"
echo "  https://www.veribits.com"
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Verify DNS propagation for veribits.com"
echo "  2. Configure SSL certificate in AWS Certificate Manager"
echo "  3. Update ALB listener to use HTTPS"
echo "  4. Run database migrations: ./scripts/run-migrations.sh"
echo "  5. Test API: curl https://veribits.com/api/v1/health"
echo ""
echo -e "${YELLOW}Note: DNS may take up to 48 hours to fully propagate${NC}"
echo ""
