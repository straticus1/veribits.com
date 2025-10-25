# 🚀 Complete VeriBits Deployment Guide

## ✅ Current Status:

1. ✅ **All code complete** - Trial system, file signature validator, file magic analyzer
2. ✅ **Infrastructure deployed** - ALB, ECS, Route53, Redis, Security Groups
3. ✅ **Database script ready** - Web-based setup tool created
4. ⏳ **YOU:** Updating DNS in GoDaddy
5. ⏳ **NEXT:** Build and deploy Docker container

---

## 🔑 GoDaddy DNS Update (You're doing this now):

**Nameservers:**
```
ns-103.awsdns-12.com
ns-1381.awsdns-44.org
ns-1749.awsdns-26.co.uk
ns-820.awsdns-38.net
```

---

## 📦 Step-by-Step Deployment:

### Step 1: Build Docker Image

```bash
cd /Users/ryan/development/veribits.com

# Build the container
docker build -t veribits-api ./app
```

**Dockerfile should contain:**
- PHP 8.2+ with extensions (pdo_pgsql, redis, gd, mbstring)
- Composer dependencies
- All source code from `/app/src`
- Public files from `/app/public`

### Step 2: Push to ECR

```bash
# Login to AWS ECR
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin \
  515966511618.dkr.ecr.us-east-1.amazonaws.com

# Tag for ECR
docker tag veribits-api:latest \
  515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api:latest

# Push to ECR
docker push 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api:latest
```

### Step 3: Deploy to ECS

```bash
# Update ECS service with new image
aws ecs update-service \
  --cluster veribits-cluster \
  --service veribits-api \
  --force-new-deployment \
  --region us-east-1

# Monitor deployment
watch -n 5 'aws ecs describe-services \
  --cluster veribits-cluster \
  --services veribits-api \
  --region us-east-1 \
  --query "services[0].deployments[]"'
```

### Step 4: Setup Database via Web

**Once ECS is running, open in browser:**

```
http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/setup-db.php?override=veribits2024
```

**Click buttons in order:**
1. ✅ Test Database Connection
2. ✅ Create 'veribits' Database
3. ✅ Run Migrations
4. ✅ Verify Tables

**Then immediately delete the script:**
```bash
# Rebuild without setup-db.php
rm app/public/setup-db.php
docker build -t veribits-api ./app
docker tag veribits-api:latest 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api:latest
docker push 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api:latest

# Redeploy
aws ecs update-service \
  --cluster veribits-cluster \
  --service veribits-api \
  --force-new-deployment \
  --region us-east-1
```

### Step 5: Test Everything

```bash
# Test health endpoint
curl http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/api/v1/health

# Should return: {"status":"ok"}

# Test trial status
curl http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/api/v1/limits/anonymous

# Should show 5 free scans remaining
```

---

## 🎯 Features to Test:

### 1. Anonymous Trial System
```bash
# Check trial status
curl http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/api/v1/limits/anonymous

# Upload a file (counts toward trial)
curl -X POST \
  -F "file=@test.jpg" \
  http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/api/v1/file-magic

# After 5 uploads, should require registration
```

### 2. File Magic Number Detection
```bash
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.jpg" \
  http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/api/v1/file-magic
```

### 3. File Signature Verification
```bash
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@document.pdf" \
  -F "signature=@document.pdf.sig" \
  -F "public_key=<PGP_KEY>" \
  http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/api/v1/verify/file-signature
```

---

## 📊 What's Deployed:

### Infrastructure:
- ✅ **Route53:** DNS management
- ✅ **ALB:** Load balancing and SSL termination point
- ✅ **ECS Fargate:** Serverless containers (2 tasks)
- ✅ **ElastiCache Redis:** Session storage and rate limiting
- ✅ **ECR:** Docker image repository
- ✅ **Cognito:** User authentication (optional, JWT used instead)
- ✅ **CloudWatch:** Logging and monitoring
- ✅ **Security Groups:** Network access controls

### Features:
- ✅ **Trial System:** 5 free scans, 50MB limit, 30-day window
- ✅ **Payment Model:** Pay-per-scan, Monthly, Annual, Enterprise
- ✅ **File Magic Analyzer:** 40+ file types detected
- ✅ **File Signature Validator:** PGP, JAR, AIR, macOS, hash files
- ✅ **Base64 Encoder/Decoder**
- ✅ **IP Calculator**
- ✅ **DNS Validator**
- ✅ **PGP Key Validator**
- ✅ **Steganography Detector**

### Pricing:
- **Trial:** 5 free scans under 50MB
- **Pay-per-scan:** $0.10/scan
- **Monthly:** $9.99/month (100 scans)
- **Annual:** $99/year (1,500 scans, save 17%)
- **Enterprise:** Custom pricing

---

## 🔐 Environment Variables (Set in ECS Task Definition):

```
DB_HOST=nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com
DB_PORT=5432
DB_NAME=veribits
DB_USER=nitetext
DB_PASS=NiteText2025!SecureProd
JWT_SECRET=veribits_prod_jwt_hK7mP9xL2nQ5wR8tY1vZ3aC6bN4jM0sD
REDIS_HOST=(from terraform output)
```

---

## 📝 Monitoring:

### CloudWatch Logs:
```bash
# Tail API logs
aws logs tail /ecs/veribits-api --follow --region us-east-1

# Search for errors
aws logs tail /ecs/veribits-api --filter-pattern "ERROR" --region us-east-1
```

### ECS Service Health:
```bash
# Check running tasks
aws ecs list-tasks --cluster veribits-cluster --region us-east-1

# Describe service
aws ecs describe-services \
  --cluster veribits-cluster \
  --services veribits-api \
  --region us-east-1
```

### ALB Health:
```bash
# Check target health
aws elbv2 describe-target-health \
  --target-group-arn $(aws elbv2 describe-target-groups \
    --query "TargetGroups[?contains(TargetGroupName, 'veribits')].TargetGroupArn" \
    --output text) \
  --region us-east-1
```

---

## 🚨 Troubleshooting:

### ALB returns 503:
- Wait for ECS tasks to start (takes 2-3 minutes)
- Check ECS service is running
- Verify health check endpoint works

### Database connection errors:
- Verify security group allows ECS → RDS
- Check environment variables in task definition
- Test connection via setup-db.php

### Redis connection errors:
- Wait for ElastiCache cluster to finish creating (~15 min)
- Check security group allows ECS → Redis
- Verify REDIS_HOST environment variable

### File uploads fail:
- Check file size limit (50MB for anonymous)
- Verify trial scans not exceeded
- Check ALB request size limits

---

## 💰 Cost Breakdown:

**Monthly Costs:**
- ALB: $20/month
- ECS Fargate (2 tasks): $25/month
- ElastiCache Redis: $12/month
- Route53 zone: $0.50/month
- Data transfer: ~$5-10/month
- **Total: ~$62-67/month**

**Potential Revenue (Conservative):**
- 1,000 trial users/month
- 10% convert to paid (100 users)
- Average $5/user/month
- **Revenue: $500/month**
- **Profit: $433-438/month**

---

## ✅ Final Checklist:

- [x] Infrastructure deployed to AWS
- [x] Trial & payment system implemented
- [x] File signature validator added
- [x] File magic number analyzer added
- [x] Anonymous constraints configured
- [x] Database setup script created
- [ ] **DNS updated in GoDaddy** (you're doing this)
- [ ] Docker image built and pushed
- [ ] ECS service deployed
- [ ] Database created and migrated
- [ ] setup-db.php deleted
- [ ] Health endpoint tested
- [ ] Trial system tested
- [ ] SSL certificate added (ACM)
- [ ] Monitoring configured
- [ ] Backups verified

---

## 🎉 Next Steps:

1. **Wait for DNS propagation** (24-48 hours)
2. **Test via https://veribits.com**
3. **Add SSL certificate via ACM**
4. **Set up billing integration** (Stripe/PayPal)
5. **Configure monitoring alerts**
6. **Marketing and launch!**

---

## 📞 Quick Reference:

**ALB URL:** http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com
**Domain:** veribits.com (after DNS propagates)
**Health Check:** /api/v1/health
**Trial Status:** /api/v1/limits/anonymous
**Database Host:** nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com
**ECR Repo:** 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api

**AWS Console:**
- [ECS](https://console.aws.amazon.com/ecs/home?region=us-east-1#/clusters/veribits-cluster)
- [ALB](https://console.aws.amazon.com/ec2/v2/home?region=us-east-1#LoadBalancers)
- [Route53](https://console.aws.amazon.com/route53/v2/hostedzones#)

---

**🚀 Everything is ready for deployment! Just build the Docker image and deploy to ECS!**
