# VeriBits Terraform Setup - Using Existing After Dark Systems Infrastructure

This Terraform configuration deploys VeriBits to the existing **afterdarksys-vpc** and uses the existing After Dark Systems RDS database.

## Architecture Changes

### What This Uses (Existing Resources):
- ✅ **VPC**: afterdarksys-vpc
- ✅ **Database**: Existing After Dark Systems RDS PostgreSQL instance
- ✅ **Subnets**: Existing public/private subnets in afterdarksys-vpc

### What This Creates (New Resources):
- Application Load Balancer (ALB)
- ECS Fargate cluster and services
- ElastiCache Redis cluster
- ECR repository for Docker images
- Security groups (ALB, ECS, Redis)
- Route53 zone (optional, if doesn't exist)
- CloudWatch log groups
- IAM roles for ECS tasks
- Cognito user pool

## Prerequisites

1. **Existing After Dark Systems Infrastructure**:
   - afterdarksys-vpc with subnets tagged as Type=public or Type=private
   - RDS PostgreSQL instance (identifier needed)
   - Database credentials

2. **AWS CLI Configured**:
   ```bash
   aws configure
   ```

3. **Terraform Installed** (v1.6.0+):
   ```bash
   terraform --version
   ```

## Setup Steps

### 1. Find Your Existing Database Identifier

List your RDS instances to find the identifier:
```bash
aws rds describe-db-instances --query 'DBInstances[*].[DBInstanceIdentifier,Engine,DBInstanceStatus]' --output table
```

Example output:
```
---------------------------------------------------
|           DescribeDBInstances                   |
+--------------------------------+--------+--------+
|  afterdarksys-main-db         | postgres | available |
+--------------------------------+--------+--------+
```

### 2. Create Database for VeriBits

Connect to your existing PostgreSQL instance and create a database:
```bash
# Connect to your database
psql -h <your-db-endpoint> -U <your-db-username> -d postgres

# Create veribits database
CREATE DATABASE veribits;

# Grant permissions (if using different user)
GRANT ALL PRIVILEGES ON DATABASE veribits TO <your-db-username>;

# Exit
\q
```

### 3. Configure Variables

Copy the example file:
```bash
cd infrastructure/terraform
cp terraform.tfvars.example terraform.tfvars
```

Edit `terraform.tfvars` with your values:
```hcl
aws_region = "us-east-1"

jwt_secret = "generate-a-secure-random-string-here"

# Update these with your existing database info
existing_db_identifier = "afterdarksys-main-db"  # From step 1
existing_db_name       = "veribits"              # Database created in step 2
existing_db_username   = "your_actual_username"  # Your DB username
existing_db_password   = "your_actual_password"  # Your DB password

id_verify_api_key = "your-id-verify-api-key"

create_route53_zone = false  # Set true if zone doesn't exist
```

### 4. Initialize Terraform

```bash
cd infrastructure/terraform
terraform init
```

### 5. Review the Plan

```bash
terraform plan -var-file=terraform.tfvars
```

**Review that it:**
- ✅ Uses existing VPC (data source)
- ✅ Uses existing database (data source)
- ✅ Creates new ALB, ECS, Redis, etc.
- ❌ Does NOT create a new RDS instance

### 6. Apply Configuration

```bash
terraform apply -var-file=terraform.tfvars
```

Type `yes` when prompted.

### 7. Get Outputs

```bash
terraform output
```

Important outputs:
- `alb_dns` - Your application load balancer DNS
- `db_endpoint` - Existing database endpoint (verified)
- `redis_endpoint` - New Redis endpoint
- `ecr_repo` - ECR repository URL for Docker images

## Database Setup

After deployment, run database migrations:

```bash
# Get DB endpoint
DB_HOST=$(terraform output -raw db_endpoint)

# Run migrations (adjust path to your migration script)
psql -h $DB_HOST -U <username> -d veribits -f ../../db/migrations/001_initial_schema.sql
```

## Security Groups

The configuration automatically:
1. Creates a security group rule to allow VeriBits ECS tasks to access the existing database
2. Adds the rule to your existing database security group
3. Does NOT modify other existing security group rules

## Cleanup

To remove only VeriBits resources (keeps existing database and VPC):

```bash
terraform destroy -var-file=terraform.tfvars
```

This will:
- ✅ Delete ALB, ECS, Redis, and other VeriBits resources
- ✅ Remove the security group rule added to the database
- ❌ Keep the existing database and VPC unchanged

## Troubleshooting

### Database Connection Issues

1. **Check security group rule was added**:
   ```bash
   aws ec2 describe-security-group-rules \
     --filters "Name=group-id,Values=$(aws rds describe-db-instances --db-instance-identifier <your-db-id> --query 'DBInstances[0].VpcSecurityGroups[0].VpcSecurityGroupId' --output text)"
   ```

2. **Verify database endpoint**:
   ```bash
   terraform output db_endpoint
   ```

3. **Test connection from ECS task** (after deployment):
   ```bash
   aws ecs execute-command --cluster veribits-cluster \
     --task <task-id> \
     --container api \
     --interactive \
     --command "/bin/sh"

   # Inside container:
   nc -zv $DB_HOST 5432
   ```

### VPC Not Found

If you get "VPC not found" error:
```bash
# Check if afterdarksys-vpc exists
aws ec2 describe-vpcs --filters "Name=tag:Name,Values=afterdarksys-vpc"

# If it has a different name, update afterdarksys.tf line 19-23
```

### Subnet Issues

If subnets aren't tagged properly:
```bash
# List all subnets in your VPC
aws ec2 describe-subnets --filters "Name=vpc-id,Values=<vpc-id>"

# The configuration falls back to using all subnets if no Type tags exist
```

## Cost Optimization

Since you're using existing infrastructure:
- ✅ **Saved**: No new RDS instance (~$30-100/month)
- ✅ **Saved**: No new VPC NAT Gateway costs
- **New costs**:
  - ECS Fargate: ~$15-30/month (2 tasks @ 1vCPU, 2GB)
  - ElastiCache Redis: ~$12/month (cache.t3.micro)
  - ALB: ~$20/month
  - **Total**: ~$47-62/month

## Next Steps

1. Build and push Docker image to ECR
2. Update ECS task definition with new image
3. Configure DNS (if using Route53)
4. Set up SSL certificate (ACM)
5. Configure monitoring and alarms

## Support

For issues with:
- **Terraform**: Check [Terraform AWS Provider docs](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)
- **After Dark Systems infrastructure**: Contact your infrastructure team
- **VeriBits application**: See main project README
