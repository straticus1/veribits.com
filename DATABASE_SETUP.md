# Database Setup via Web Interface

## Quick Setup (Using Web Browser)

Since the database is only accessible from within the VPC, we'll use a temporary PHP script accessed through the ALB.

### 1. The script is already in place:
```
/app/public/setup-db.php
```

### 2. Access via ALB URL:

**Open in your browser:**
```
http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/setup-db.php?override=veribits2024
```

**Or if you want IP restriction, update the script first with your IP.**

### 3. Follow the on-screen steps:

1. **Test Database Connection** - Click to verify connectivity
2. **Create 'veribits' Database** - Click to create the database
3. **Run Migrations** - Click to create tables (file_magic_checks, file_signature_checks)
4. **Verify Tables** - Click to confirm tables were created

### 4. Delete the setup script:

**After completing setup, immediately delete the file:**

```bash
# Via Docker exec (once container is running)
docker exec <container-id> rm /var/www/html/setup-db.php

# Or rebuild and redeploy without the file
rm app/public/setup-db.php
docker build -t veribits-api ./app
# ... push to ECR and redeploy
```

**Or add to index.php to delete it:**

```php
// In app/public/index.php, add at the top:
if (file_exists(__DIR__ . '/setup-db.php')) {
    unlink(__DIR__ . '/setup-db.php');
}
```

---

## Alternative: Direct Database Access

If you want to access the database directly from your local machine:

### Temporarily Add Your IP to Security Group:

```bash
# Get your current IP
MY_IP=$(curl -s https://ifconfig.me)

# Get database security group ID
SG_ID=$(aws rds describe-db-instances \
  --db-instance-identifier nitetext-db \
  --query 'DBInstances[0].VpcSecurityGroups[0].VpcSecurityGroupId' \
  --output text)

# Add temporary rule
aws ec2 authorize-security-group-ingress \
  --group-id $SG_ID \
  --protocol tcp \
  --port 5432 \
  --cidr $MY_IP/32

# Now you can connect directly
psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
  -U nitetext -d postgres

# CREATE DATABASE veribits;
# \c veribits
# \i db/migrations/003_file_magic_table.sql
# \i db/migrations/004_file_signature_table.sql

# Remove temporary rule when done
aws ec2 revoke-security-group-ingress \
  --group-id $SG_ID \
  --protocol tcp \
  --port 5432 \
  --cidr $MY_IP/32
```

---

## Verification

After setup, verify tables were created:

```bash
# Via web interface
curl http://veribits-alb-1472450181.us-east-1.elb.amazonaws.com/setup-db.php?override=veribits2024 \
  -d "action=check_tables"

# Expected output:
# ✅ Tables in 'veribits' database:
#   - file_magic_checks
#   - file_signature_checks
#   - users (if exists from previous deployments)
#   - ...
```

---

## Security Notes:

1. **setup-db.php** includes IP whitelist (24.187.53.33)
2. Requires `?override=veribits2024` parameter
3. **MUST BE DELETED** after use
4. Only accessible via ALB (not direct internet)
5. Uses environment variables for DB credentials

---

## Troubleshooting:

### Script returns "Access denied"
- Add `?override=veribits2024` to URL
- Or update `$allowedIps` array in setup-db.php with your IP

### Can't connect to database
- Wait for ECS service to start (container must be running)
- Check ECS task is in RUNNING state
- Verify environment variables are set

### Tables already exist
- This is normal if migrations were run before
- Check "Verify Tables" step to confirm all tables present

---

## Next Steps After Database Setup:

1. ✅ Delete setup-db.php
2. Test API endpoints
3. Register a test user
4. Try file upload with trial account
5. Verify anonymous rate limiting works
6. Check billing/payment flows

