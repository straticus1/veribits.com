# VeriBits - After Dark Systems VPC Deployment
# Uses existing afterdarksys-vpc infrastructure

terraform {
  required_version = ">= 1.6.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = ">=5.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

# Data sources - reference existing After Dark Systems VPC
data "aws_vpc" "afterdarksys" {
  filter {
    name   = "tag:Name"
    values = ["afterdarksys-vpc"]
  }
}

data "aws_subnets" "afterdarksys_public" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.afterdarksys.id]
  }

  filter {
    name   = "tag:Type"
    values = ["public"]
  }
}

data "aws_subnets" "afterdarksys_private" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.afterdarksys.id]
  }

  filter {
    name   = "tag:Type"
    values = ["private"]
  }
}

# If no tagged subnets exist, use all available subnets
data "aws_subnets" "afterdarksys_all" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.afterdarksys.id]
  }
}

locals {
  public_subnet_ids  = length(data.aws_subnets.afterdarksys_public.ids) > 0 ? data.aws_subnets.afterdarksys_public.ids : slice(data.aws_subnets.afterdarksys_all.ids, 0, 2)
  private_subnet_ids = length(data.aws_subnets.afterdarksys_private.ids) > 0 ? data.aws_subnets.afterdarksys_private.ids : slice(data.aws_subnets.afterdarksys_all.ids, 0, 2)
}

# Route53 - DNS for veribits.com
# Check if zone already exists, if not create it
data "aws_route53_zone" "veribits_existing" {
  name         = "veribits.com"
  private_zone = false
  count        = var.create_route53_zone ? 0 : 1
}

resource "aws_route53_zone" "veribits_new" {
  name  = "veribits.com"
  count = var.create_route53_zone ? 1 : 0

  tags = {
    Name        = "veribits.com"
    ManagedBy   = "Terraform"
    Project     = "VeriBits"
    Environment = "production"
  }
}

locals {
  route53_zone_id = var.create_route53_zone ? aws_route53_zone.veribits_new[0].zone_id : data.aws_route53_zone.veribits_existing[0].zone_id
  route53_name_servers = var.create_route53_zone ? aws_route53_zone.veribits_new[0].name_servers : data.aws_route53_zone.veribits_existing[0].name_servers
}

# Security Groups
resource "aws_security_group" "alb_sg" {
  name        = "veribits-alb-sg"
  description = "Security group for VeriBits ALB"
  vpc_id      = data.aws_vpc.afterdarksys.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTP"
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTPS"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
    description = "Allow all outbound"
  }

  tags = {
    Name = "veribits-alb-sg"
  }
}

resource "aws_security_group" "ecs_sg" {
  name        = "veribits-ecs-sg"
  description = "Security group for VeriBits ECS tasks"
  vpc_id      = data.aws_vpc.afterdarksys.id

  ingress {
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb_sg.id]
    description     = "HTTP from ALB"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
    description = "Allow all outbound"
  }

  tags = {
    Name = "veribits-ecs-sg"
  }
}

# Note: Using existing After Dark Systems RDS security group
# Security rule added via aws_security_group_rule.veribits_to_db below

resource "aws_security_group" "redis_sg" {
  name        = "veribits-redis-sg"
  description = "Security group for VeriBits Redis"
  vpc_id      = data.aws_vpc.afterdarksys.id

  ingress {
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [aws_security_group.ecs_sg.id]
    description     = "Redis from ECS"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "veribits-redis-sg"
  }
}

# Application Load Balancer
resource "aws_lb" "app" {
  name               = "veribits-alb"
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb_sg.id]
  subnets            = local.public_subnet_ids

  tags = {
    Name = "veribits-alb"
  }
}

resource "aws_lb_target_group" "app_tg" {
  name        = "veribits-tg"
  port        = 80
  protocol    = "HTTP"
  target_type = "ip"
  vpc_id      = data.aws_vpc.afterdarksys.id

  health_check {
    path                = "/api/v1/health"
    healthy_threshold   = 2
    unhealthy_threshold = 3
    timeout             = 5
    interval            = 30
    matcher             = "200"
  }

  tags = {
    Name = "veribits-tg"
  }
}

# ACM Certificate for SSL/TLS
resource "aws_acm_certificate" "veribits" {
  domain_name       = "veribits.com"
  validation_method = "DNS"
  subject_alternative_names = ["www.veribits.com"]

  lifecycle {
    create_before_destroy = true
  }

  tags = {
    Name = "veribits-ssl-cert"
  }
}

# Route53 DNS validation records for ACM certificate
resource "aws_route53_record" "cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.veribits.domain_validation_options : dvo.domain_name => {
      name   = dvo.resource_record_name
      record = dvo.resource_record_value
      type   = dvo.resource_record_type
    }
  }

  allow_overwrite = true
  name            = each.value.name
  records         = [each.value.record]
  ttl             = 60
  type            = each.value.type
  zone_id         = local.route53_zone_id
}

# Wait for certificate validation to complete
resource "aws_acm_certificate_validation" "veribits" {
  certificate_arn         = aws_acm_certificate.veribits.arn
  validation_record_fqdns = [for record in aws_route53_record.cert_validation : record.fqdn]
}

# HTTP Listener - Redirect to HTTPS
resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.app.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type = "redirect"

    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

# HTTPS Listener - Forward to target group
resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.app.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = aws_acm_certificate_validation.veribits.certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.app_tg.arn
  }
}

# Route53 DNS Records
resource "aws_route53_record" "veribits_root" {
  zone_id = local.route53_zone_id
  name    = "veribits.com"
  type    = "A"

  alias {
    name                   = aws_lb.app.dns_name
    zone_id                = aws_lb.app.zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "veribits_www" {
  zone_id = local.route53_zone_id
  name    = "www.veribits.com"
  type    = "A"

  alias {
    name                   = aws_lb.app.dns_name
    zone_id                = aws_lb.app.zone_id
    evaluate_target_health = true
  }
}

# ECR Repository
resource "aws_ecr_repository" "api" {
  name = "veribits-api"

  image_scanning_configuration {
    scan_on_push = true
  }

  tags = {
    Name = "veribits-api"
  }
}

# ECS Cluster
resource "aws_ecs_cluster" "main" {
  name = "veribits-cluster"

  setting {
    name  = "containerInsights"
    value = "enabled"
  }

  tags = {
    Name = "veribits-cluster"
  }
}

# IAM Role for ECS Task Execution
data "aws_iam_policy_document" "assume" {
  statement {
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "task_exec" {
  name               = "veribits-task-exec"
  assume_role_policy = data.aws_iam_policy_document.assume.json

  tags = {
    Name = "veribits-task-exec"
  }
}

resource "aws_iam_role_policy_attachment" "exec_attach" {
  role       = aws_iam_role.task_exec.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

# CloudWatch Log Group
resource "aws_cloudwatch_log_group" "app" {
  name              = "/ecs/veribits-api"
  retention_in_days = 7

  tags = {
    Name = "veribits-api-logs"
  }
}

# ECS Task Definition
resource "aws_ecs_task_definition" "api" {
  family                   = "veribits-api"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = "1024"
  memory                   = "2048"
  execution_role_arn       = aws_iam_role.task_exec.arn

  container_definitions = jsonencode([
    {
      name      = "api"
      image     = "${aws_ecr_repository.api.repository_url}:latest"
      essential = true

      portMappings = [
        {
          containerPort = 80
          protocol      = "tcp"
        }
      ]

      environment = [
        { name = "APP_ENV", value = "production" },
        { name = "JWT_SECRET", value = var.jwt_secret },
        { name = "DB_HOST", value = data.aws_db_instance.afterdarksys_pg.address },
        { name = "DB_PORT", value = "5432" },
        { name = "DB_NAME", value = var.existing_db_name },
        { name = "DB_USER", value = var.existing_db_username },
        { name = "DB_PASSWORD", value = var.existing_db_password },
        { name = "REDIS_HOST", value = aws_elasticache_cluster.redis.cache_nodes[0].address },
        { name = "REDIS_PORT", value = "6379" },
        { name = "ID_VERIFY_API_KEY", value = var.id_verify_api_key }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.app.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "api"
        }
      }
    }
  ])

  tags = {
    Name = "veribits-api"
  }
}

# ECS Service
resource "aws_ecs_service" "api" {
  name            = "veribits-api-svc"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.api.arn
  desired_count   = 2
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = local.private_subnet_ids
    security_groups  = [aws_security_group.ecs_sg.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app_tg.arn
    container_name   = "api"
    container_port   = 80
  }

  depends_on = [aws_lb_listener.http]

  tags = {
    Name = "veribits-api-svc"
  }
}

# Use existing After Dark Systems RDS PostgreSQL
data "aws_db_instance" "afterdarksys_pg" {
  db_instance_identifier = var.existing_db_identifier
}

# Update existing RDS security group to allow VeriBits ECS access
resource "aws_security_group_rule" "veribits_to_db" {
  type                     = "ingress"
  from_port                = 5432
  to_port                  = 5432
  protocol                 = "tcp"
  source_security_group_id = aws_security_group.ecs_sg.id
  security_group_id        = data.aws_db_instance.afterdarksys_pg.vpc_security_groups[0]
  description              = "Allow VeriBits ECS tasks to access database"
}

# ElastiCache Redis
resource "aws_elasticache_subnet_group" "redis" {
  name       = "veribits-redis-subnets"
  subnet_ids = local.private_subnet_ids

  tags = {
    Name = "veribits-redis-subnets"
  }
}

resource "aws_elasticache_cluster" "redis" {
  cluster_id           = "veribits-redis"
  engine               = "redis"
  node_type            = "cache.t3.micro"
  num_cache_nodes      = 1
  subnet_group_name    = aws_elasticache_subnet_group.redis.name
  security_group_ids   = [aws_security_group.redis_sg.id]
  port                 = 6379
  parameter_group_name = "default.redis7"

  tags = {
    Name = "veribits-redis"
  }
}

# Cognito User Pool
resource "aws_cognito_user_pool" "up" {
  name = "veribits-users"

  password_policy {
    minimum_length    = 8
    require_lowercase = true
    require_numbers   = true
    require_symbols   = true
    require_uppercase = true
  }

  auto_verified_attributes = ["email"]

  schema {
    name                = "email"
    attribute_data_type = "String"
    required            = true
    mutable             = false
  }

  tags = {
    Name = "veribits-users"
  }
}

resource "aws_cognito_user_pool_client" "upc" {
  name         = "veribits-web"
  user_pool_id = aws_cognito_user_pool.up.id

  explicit_auth_flows = [
    "ALLOW_USER_PASSWORD_AUTH",
    "ALLOW_REFRESH_TOKEN_AUTH",
    "ALLOW_USER_SRP_AUTH"
  ]

  generate_secret = false
}

# Outputs
output "alb_dns" {
  value       = aws_lb.app.dns_name
  description = "ALB DNS name"
}

output "certificate_arn" {
  value       = aws_acm_certificate.veribits.arn
  description = "ACM certificate ARN"
}

output "certificate_status" {
  value       = aws_acm_certificate.veribits.status
  description = "ACM certificate validation status"
}

output "veribits_domain" {
  value       = "https://veribits.com"
  description = "VeriBits domain"
}

output "ssl_info" {
  value = <<-EOT
    SSL Certificate configured for:
    - veribits.com
    - www.veribits.com

    HTTP (port 80) automatically redirects to HTTPS (port 443)
    TLS Policy: TLS 1.3 (ELBSecurityPolicy-TLS13-1-2-2021-06)
  EOT
  description = "SSL configuration details"
}

output "ecr_repo" {
  value       = aws_ecr_repository.api.repository_url
  description = "ECR repository URL"
}

output "db_endpoint" {
  value       = data.aws_db_instance.afterdarksys_pg.address
  description = "RDS endpoint (existing After Dark Systems database)"
  sensitive   = true
}

output "redis_endpoint" {
  value       = aws_elasticache_cluster.redis.cache_nodes[0].address
  description = "Redis endpoint"
  sensitive   = true
}

output "cognito_user_pool_id" {
  value       = aws_cognito_user_pool.up.id
  description = "Cognito User Pool ID"
}

output "cognito_client_id" {
  value       = aws_cognito_user_pool_client.upc.id
  description = "Cognito Client ID"
}

output "vpc_id" {
  value       = data.aws_vpc.afterdarksys.id
  description = "After Dark Systems VPC ID"
}

output "route53_zone_id" {
  value       = local.route53_zone_id
  description = "Route53 zone ID for veribits.com"
}

output "route53_name_servers" {
  value       = local.route53_name_servers
  description = "Route53 authoritative name servers for veribits.com"
}

output "dns_setup_instructions" {
  value = var.create_route53_zone ? join("\n", [
    "========================================",
    "IMPORTANT: DNS Setup Required",
    "========================================",
    "A new Route53 hosted zone has been created for veribits.com",
    "You MUST update your domain registrar with these name servers:",
    join("\n", local.route53_name_servers),
    "",
    "Steps:",
    "1. Log in to your domain registrar (where you bought veribits.com)",
    "2. Find the DNS/Nameserver settings",
    "3. Replace the current nameservers with the ones above",
    "4. Save changes",
    "",
    "DNS propagation can take 24-48 hours.",
    "========================================"
  ]) : "Using existing Route53 zone - no action needed"
}
