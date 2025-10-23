variable "aws_region" {
  type        = string
  default     = "us-east-1"
  description = "AWS region for deployment"
}

variable "jwt_secret" {
  type        = string
  description = "JWT secret for token signing"
  sensitive   = true
}

# Existing After Dark Systems Database Configuration
variable "existing_db_identifier" {
  type        = string
  description = "Identifier of the existing After Dark Systems RDS database instance"
  default     = "afterdarksys-main-db"
}

variable "existing_db_name" {
  type        = string
  description = "Database name in the existing RDS instance for VeriBits"
  default     = "veribits"
}

variable "existing_db_username" {
  type        = string
  description = "Database username for the existing After Dark Systems RDS instance"
  sensitive   = true
}

variable "existing_db_password" {
  type        = string
  description = "Database password for the existing After Dark Systems RDS instance"
  sensitive   = true
}

variable "id_verify_api_key" {
  type        = string
  description = "After Dark Systems ID Verification API key"
  sensitive   = true
  default     = ""
}

variable "create_route53_zone" {
  type        = bool
  default     = false
  description = "Set to true to create a new Route53 hosted zone for veribits.com. Set to false if zone already exists."
}
