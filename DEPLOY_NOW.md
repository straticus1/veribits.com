# Deploy VeriBits to AWS - Quick Guide

## Step 1: Update Configuration (REQUIRED)

Edit this file: `/Users/ryan/development/veribits.com/infrastructure/terraform/terraform.tfvars`

Replace these placeholders with actual values:

```hcl
# Generate a secure JWT secret (32+ characters)
# Run: openssl rand -base64 32
jwt_secret = "TEMP-JWT-SECRET-PLEASE-CHANGE-ME-TO-SECURE-VALUE"

# Your nitetext-db database credentials
existing_db_username = "CHANGE-ME"  # Your PostgreSQL username
existing_db_password   = "CHANGE-ME"  # Your PostgreSQL password
```

**Quick command to generate JWT secret:**
```bash
openssl rand -base64 32
```

## Step 2: Deploy Infrastructure

```bash
cd /Users/ryan/development/veribits.com/infrastructure/terraform

# Preview what will be created (18 resources)
./tf plan -var-file=terraform.tfvars

# Deploy (type 'yes' when prompted)
./tf apply -var-file=terraform.tfvars
```

This creates:
- ✅ Route53 DNS zone for veribits.com
- ✅ Application Load Balancer
- ✅ ECS Fargate cluster (2 containers)
- ✅ ElastiCache Redis
- ✅ ECR repository
- ✅ Security groups
- ✅ Cognito user pool
- ✅ CloudWatch logs

**Cost: ~$57-65/month**

## Step 3: Get Name Servers for GoDaddy

After deployment completes (5-10 minutes):

```bash
cd /Users/ryan/development/veribits.com/infrastructure/terraform
./tf output name_servers
```

Copy all 4 name servers shown.

## Step 4: Update GoDaddy DNS

1. Go to GoDaddy.com → My Products
2. Find veribits.com → DNS
3. Change Nameservers → Use my own nameservers
4. Paste all 4 AWS nameservers
5. Save

**DNS propagation: 24-48 hours**

## Step 5: Test with Direct URL (Before DNS)

```bash
# Get ALB URL
./tf output alb_dns

# Test health endpoint
curl http://$(./tf output -raw alb_dns)/api/v1/health
```

Should return: `{"status":"ok"}`

## Step 6: Create Database and Run Migrations

```bash
# Get database endpoint
DB_HOST=$(./tf output -raw db_endpoint | cut -d: -f1)

# Connect to database
psql -h $DB_HOST -U <your-username> -d postgres

# In psql:
CREATE DATABASE veribits;
GRANT ALL PRIVILEGES ON DATABASE veribits TO <your-username>;
\q

# Run migrations
psql -h $DB_HOST -U <your-username> -d veribits -f /Users/ryan/development/veribits.com/db/migrations/001_initial_schema.sql
psql -h $DB_HOST -U <your-username> -d veribits -f /Users/ryan/development/veribits.com/db/migrations/002_additional_tools.sql
psql -h $DB_HOST -U <your-username> -d veribits -f /Users/ryan/development/veribits.com/db/migrations/003_file_magic_table.sql
psql -h $DB_HOST -U <your-username> -d veribits -f /Users/ryan/development/veribits.com/db/migrations/004_file_signature_table.sql
```

## Step 7: Build and Deploy Application

```bash
cd /Users/ryan/development/veribits.com

# Get ECR repository URL
ECR_REPO=$(cd infrastructure/terraform && ./tf output -raw ecr_repo)

# Login to ECR
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin $ECR_REPO

# Build Docker image
docker build -t veribits-api ./app

# Tag and push
docker tag veribits-api:latest $ECR_REPO:latest
docker push $ECR_REPO:latest

# Deploy to ECS
aws ecs update-service --cluster veribits-cluster --service veribits-api --force-new-deployment --region us-east-1
```

## Step 8: Monitor Deployment

```bash
# Watch ECS service
aws ecs describe-services --cluster veribits-cluster --services veribits-api --region us-east-1

# View logs
aws logs tail /ecs/veribits-api --follow --region us-east-1
```

## Step 9: Test API

```bash
# Get ALB URL
ALB_URL=$(cd infrastructure/terraform && ./tf output -raw alb_dns)

# Test health
curl http://$ALB_URL/api/v1/health

# Test after DNS propagates (24-48 hours)
curl https://veribits.com/api/v1/health
```

## What You Have Available:

**Existing Database:** nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com
**Existing VPC:** vpc-0c1b813880b3982a5
**New Domain:** veribits.com

## Terraform Wrapper

Use `./tf` instead of `terraform` to avoid macOS permission issues:
```bash
./tf init
./tf plan
./tf apply
./tf destroy
```

## Troubleshooting

**Database connection issues:**
```bash
# Check security group
aws rds describe-db-instances --db-instance-identifier nitetext-db --query 'DBInstances[0].VpcSecurityGroups'
```

**ECS not starting:**
```bash
# Check task failures
aws ecs list-tasks --cluster veribits-cluster --desired-status STOPPED
aws ecs describe-tasks --cluster veribits-cluster --tasks <task-id>
```

**DNS not resolving:**
```bash
# Check propagation
dig veribits.com NS
nslookup -type=NS veribits.com 8.8.8.8
```

## Summary

1. ✅ Terraform wrapper created (`./tf`)
2. ✅ Configuration file created (needs your DB credentials)
3. ✅ Using existing `nitetext-db` PostgreSQL database
4. ✅ Using existing VPC
5. ⏳ **ACTION REQUIRED:** Update `terraform.tfvars` with DB credentials and JWT secret
6. ⏳ **THEN RUN:** `./tf apply -var-file=terraform.tfvars`

After deployment, you'll get the name servers to configure in GoDaddy!
