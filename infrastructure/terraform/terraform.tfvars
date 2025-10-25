# VeriBits Terraform Configuration

# AWS Configuration
aws_region = "us-east-1"

# JWT Secret (generated secure random string)
jwt_secret = "veribits_prod_jwt_hK7mP9xL2nQ5wR8tY1vZ3aC6bN4jM0sD"

# Existing After Dark Systems Database Configuration
existing_db_identifier = "nitetext-db"
existing_db_name       = "veribits"
existing_db_username   = "nitetext"
existing_db_password   = "NiteText2025!SecureProd"

# After Dark Systems ID Verification API
id_verify_api_key = ""

# Route53 Configuration - set to true to create new zone
create_route53_zone = true
