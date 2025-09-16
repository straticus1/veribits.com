terraform {
  required_version = ">= 1.6.0"
  required_providers { aws = { source="hashicorp/aws" version=">=5.0" } }
}
provider "aws" { region = var.aws_region }
resource "aws_vpc" "main" { cidr_block="10.42.0.0/16" tags={Name="veribits-vpc"} }
resource "aws_subnet" "public_a" { vpc_id=aws_vpc.main.id cidr_block="10.42.1.0/24" availability_zone="${var.aws_region}a" map_public_ip_on_launch=true }
resource "aws_subnet" "public_b" { vpc_id=aws_vpc.main.id cidr_block="10.42.2.0/24" availability_zone="${var.aws_region}b" map_public_ip_on_launch=true }
resource "aws_internet_gateway" "gw" { vpc_id=aws_vpc.main.id }
resource "aws_route_table" "public" { vpc_id=aws_vpc.main.id }
resource "aws_route" "default" { route_table_id=aws_route_table.public.id destination_cidr_block="0.0.0.0/0" gateway_id=aws_internet_gateway.gw.id }
resource "aws_route_table_association" "a" { subnet_id=aws_subnet.public_a.id route_table_id=aws_route_table.public.id }
resource "aws_route_table_association" "b" { subnet_id=aws_subnet.public_b.id route_table_id=aws_route_table.public.id }
resource "aws_security_group" "alb_sg" { name="veribits-alb-sg" vpc_id=aws_vpc.main.id ingress{from_port=80 to_port=80 protocol="tcp" cidr_blocks=["0.0.0.0/0"]} egress{from_port=0 to_port=0 protocol="-1" cidr_blocks=["0.0.0.0/0"]} }
resource "aws_security_group" "ecs_sg" { name="veribits-ecs-sg" vpc_id=aws_vpc.main.id ingress{from_port=80 to_port=80 protocol="tcp" security_groups=[aws_security_group.alb_sg.id]} egress{from_port=0 to_port=0 protocol="-1" cidr_blocks=["0.0.0.0/0"]} }
resource "aws_lb" "app" { name="veribits-alb" load_balancer_type="application" security_groups=[aws_security_group.alb_sg.id] subnets=[aws_subnet.public_a.id,aws_subnet.public_b.id] }
resource "aws_lb_target_group" "app_tg" { name="veribits-tg" port=80 protocol="HTTP" target_type="ip" vpc_id=aws_vpc.main.id health_check{path="/api/v1/health"} }
resource "aws_lb_listener" "http" { load_balancer_arn=aws_lb.app.arn port=80 protocol="HTTP" default_action{type="forward" target_group_arn=aws_lb_target_group.app_tg.arn} }
resource "aws_ecr_repository" "api" { name="veribits-api" }
resource "aws_ecs_cluster" "main" { name="veribits-cluster" }
data "aws_iam_policy_document" "assume" { statement { actions=["sts:AssumeRole"] principals { type="Service" identifiers=["ecs-tasks.amazonaws.com"] } } }
resource "aws_iam_role" "task_exec" { name="veribits-task-exec" assume_role_policy=data.aws_iam_policy_document.assume.json }
resource "aws_iam_role_policy_attachment" "exec_attach" { role=aws_iam_role.task_exec.name policy_arn="arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy" }
resource "aws_ecs_task_definition" "api" {
  family="veribits-api" requires_compatibilities=["FARGATE"] network_mode="awsvpc" cpu="512" memory="1024" execution_role_arn=aws_iam_role.task_exec.arn
  container_definitions = jsonencode([{
    name="api", image="${aws_ecr_repository.api.repository_url}:latest", essential=true,
    portMappings=[{containerPort=80,protocol="tcp"}],
    environment=[{name="APP_ENV",value="prod"},{name="JWT_SECRET",value=var.jwt_secret}]
  }])
}
resource "aws_ecs_service" "api" {
  name="veribits-api-svc" cluster=aws_ecs_cluster.main.id task_definition=aws_ecs_task_definition.api.arn desired_count=1 launch_type="FARGATE"
  network_configuration { subnets=[aws_subnet.public_a.id,aws_subnet.public_b.id] security_groups=[aws_security_group.ecs_sg.id] assign_public_ip=true }
  load_balancer { target_group_arn=aws_lb_target_group.app_tg.arn container_name="api" container_port=80 }
  depends_on=[aws_lb_listener.http]
}
resource "aws_db_subnet_group" "db" { name="veribits-db-subnets" subnet_ids=[aws_subnet.public_a.id,aws_subnet.public_b.id] }
resource "aws_db_instance" "pg" { identifier="veribits-pg" engine="postgres" engine_version="15" instance_class="db.t3.micro" allocated_storage=20 username=var.db_username password=var.db_password db_subnet_group_name=aws_db_subnet_group.db.name publicly_accessible=true skip_final_snapshot=true }
resource "aws_elasticache_subnet_group" "redis" { name="veribits-redis-subnets" subnet_ids=[aws_subnet.public_a.id,aws_subnet.public_b.id] }
resource "aws_elasticache_cluster" "redis" { cluster_id="veribits-redis" engine="redis" node_type="cache.t3.micro" num_cache_nodes=1 subnet_group_name=aws_elasticache_subnet_group.redis.name port=6379 }
resource "aws_cognito_user_pool" "up" { name="veribits-users" }
resource "aws_cognito_user_pool_client" "upc" { name="veribits-web" user_pool_id=aws_cognito_user_pool.up.id explicit_auth_flows=["ALLOW_USER_PASSWORD_AUTH","ALLOW_REFRESH_TOKEN_AUTH"] generate_secret=false }

output "alb_dns" { value=aws_lb.app.dns_name }
output "ecr_repo" { value=aws_ecr_repository.api.repository_url }
output "db_endpoint" { value=aws_db_instance.pg.address }
output "redis_endpoint" { value=aws_elasticache_cluster.redis.configuration_endpoint }
output "cognito_user_pool_id" { value=aws_cognito_user_pool.up.id }
output "cognito_client_id" { value=aws_cognito_user_pool_client.upc.id }
