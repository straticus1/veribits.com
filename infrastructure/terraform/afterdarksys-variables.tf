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

variable "db_username" {
  type        = string
  default     = "veribits"
  description = "Database username"
}

variable "db_password" {
  type        = string
  description = "Database password"
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
