# VeriBits AWS Deployment Guide

## Quick Start Deployment

### 1. Update Terraform Configuration

Edit `/infrastructure/terraform/terraform.tfvars` with your actual values:

```hcl
aws_region = "us-east-1"

# Generate a secure JWT secret (32+ characters)
jwt_secret = "<GENERATE-SECURE-VALUE>"

# Your existing After Dark Systems database details
existing_db_identifier = "afterdarksys-main-db"  # Your RDS identifier
existing_db_name       = "veribits"
existing_db_username   = "<YOUR-DB-USERNAME>"
existing_db_password   = "<YOUR-DB-PASSWORD>"

# Optional: After Dark Systems ID Verification API key
id_verify_api_key = ""

# Set to true to create new Route53 hosted zone
create_route53_zone = true
```

### 2. Deploy Infrastructure

```bash
cd infrastructure/terraform

# Initialize Terraform (downloads providers)
terraform init

# Preview changes
terraform plan -var-file=terraform.tfvars

# Deploy (type 'yes' when prompted)
terraform apply -var-file=terraform.tfvars
```

### 3. Get Name Servers for GoDaddy

After deployment completes, run:

```bash
terraform output name_servers
```

This will show output like:
```
[
  "ns-1234.awsdns-12.org",
  "ns-5678.awsdns-34.co.uk",
  "ns-910.awsdns-56.com",
  "ns-1112.awsdns-78.net"
]
```

### 4. Update GoDaddy DNS

1. Log in to GoDaddy
2. Go to your domain `veribits.com`
3. Find "Nameservers" or "DNS" settings
4. Change nameservers to "Custom"
5. Enter all 4 AWS nameservers from the output above
6. Save changes

**Note:** DNS propagation takes 24-48 hours

### 5. Get ALB URL for Testing

```bash
# Get the Application Load Balancer URL
terraform output alb_dns
```

You'll get something like:
```
veribits-alb-123456789.us-east-1.elb.amazonaws.com
```

### 6. Run Database Migrations

```bash
# Get database endpoint
DB_ENDPOINT=$(terraform output -raw db_endpoint)

# Run migrations
psql -h $DB_ENDPOINT -U <username> -d veribits -f ../../db/migrations/001_initial_schema.sql
psql -h $DB_ENDPOINT -U <username> -d veribits -f ../../db/migrations/002_additional_tools.sql
psql -h $DB_ENDPOINT -U <username> -d veribits -f ../../db/migrations/003_file_magic_table.sql
psql -h $DB_ENDPOINT -U <username> -d veribits -f ../../db/migrations/004_file_signature_table.sql
```

### 7. Build and Push Docker Image

```bash
# Get ECR repository URL
ECR_REPO=$(terraform output -raw ecr_repo)

# Build Docker image
cd ../../
docker build -t veribits-api ./app

# Tag for ECR
docker tag veribits-api:latest $ECR_REPO:latest

# Login to ECR
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin $ECR_REPO

# Push image
docker push $ECR_REPO:latest
```

### 8. Update ECS Service

```bash
# Update task definition to use new image
# This will trigger a rolling deployment
aws ecs update-service --cluster veribits-cluster --service veribits-api --force-new-deployment
```

### 9. Test via Direct ALB URL

Before DNS propagates, test using the ALB URL:

```bash
ALB_URL=$(terraform output -raw alb_dns)

# Test health endpoint
curl http://$ALB_URL/api/v1/health

# Should return: {"status":"ok"}
```

### 10. Test After DNS Propagation

Once DNS propagates (24-48 hours), test with domain:

```bash
# Test health endpoint
curl https://veribits.com/api/v1/health

# Test frontend
open https://veribits.com
```

## Troubleshooting

### Check if afterdarksys-vpc exists

```bash
aws ec2 describe-vpcs --filters "Name=tag:Name,Values=afterdarksys-vpc"
```

### Check database connectivity

```bash
aws rds describe-db-instances --db-instance-identifier afterdarksys-main-db
```

### View ECS logs

```bash
aws logs tail /ecs/veribits-api --follow
```

### Check DNS propagation

```bash
dig veribits.com NS
# or
nslookup -type=NS veribits.com
```

## Infrastructure Components Created

- **Route53 Hosted Zone** - DNS for veribits.com
- **Application Load Balancer** - HTTP/HTTPS traffic distribution
- **ECS Fargate Cluster** - Serverless container orchestration
- **ECS Service** - Runs API containers (2 tasks)
- **ElastiCache Redis** - Session storage and caching
- **ECR Repository** - Docker image storage
- **Security Groups** - Firewall rules for ALB, ECS, Redis
- **CloudWatch Logs** - Application logs
- **IAM Roles** - ECS task permissions

## Cost Estimate

- **ALB**: ~$20/month
- **ECS Fargate** (2 tasks @ 1vCPU, 2GB): ~$25/month
- **ElastiCache Redis** (t3.micro): ~$12/month
- **Route53** (1 hosted zone): ~$0.50/month
- **Data Transfer**: Variable

**Total**: ~$57-65/month (excluding existing database and VPC)

## Security Considerations

- ALB configured for HTTPS (certificate required - set up ACM)
- Security groups restrict traffic to necessary ports only
- ECS tasks run in private subnets (if available)
- Database accessible only from ECS security group
- JWT authentication on all API endpoints
- Rate limiting enabled on all endpoints

## Next Steps After Deployment

1. **Set up SSL/TLS Certificate**
   - Create ACM certificate for veribits.com
   - Validate via DNS or email
   - Update ALB to use HTTPS listener

2. **Configure CloudWatch Alarms**
   - ALB 5xx errors
   - ECS CPU/Memory thresholds
   - Database connections
   - Redis memory usage

3. **Set up CI/CD Pipeline**
   - GitHub Actions or AWS CodePipeline
   - Automated testing
   - Automated deployments

4. **Configure Backups**
   - RDS automated backups (already configured if using existing DB)
   - Export important data periodically

## Support

- Infrastructure issues: Check Terraform AWS Provider docs
- Application issues: Check CloudWatch logs
- Database issues: Check RDS console and logs
