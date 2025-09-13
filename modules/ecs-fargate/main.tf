# --- ECR ---
# resource "aws_ecr_repository" "php_app" {
#   name = "php-app"
# }

# resource "aws_ecr_repository" "redis_app" {
#   name = "redis-app"
# }

resource "aws_cloudwatch_log_group" "ecs_my_app_service" {
  name              = "/ecs/my-app-service"
  retention_in_days = 7
  tags = {
    Project     = "skripsi"
    Environment = "dev"
    ManagedBy   = "terraform"
  }
}

# Bucket untuk export CloudWatch Logs
resource "aws_s3_bucket" "ecs_logs" {
  bucket        = "my-log-bucket" 
  force_destroy = true

  tags = {
    Project     = "skripsi"
    Environment = "dev"
    ManagedBy   = "terraform"
  }
}

# Bucket policy supaya CloudWatch Logs bisa tulis
resource "aws_s3_bucket_policy" "ecs_logs_policy" {
  bucket = aws_s3_bucket.ecs_logs.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid       = "AllowCWLogsExport",
        Effect    = "Allow",
        Principal = {
          Service = "logs.amazonaws.com"
        },
        Action   = "s3:GetBucketAcl",
        Resource = "arn:aws:s3:::${aws_s3_bucket.ecs_logs.bucket}"
      },
      {
        Sid       = "AllowCWLogsWrite",
        Effect    = "Allow",
        Principal = {
          Service = "logs.amazonaws.com"
        },
        Action   = "s3:PutObject",
        Resource = "arn:aws:s3:::${aws_s3_bucket.ecs_logs.bucket}/*",
        Condition = {
          StringEquals = {
            "s3:x-amz-acl" = "bucket-owner-full-control"
          }
        }
      }
    ]
  })
}




# --- ECS Cluster ---
resource "aws_ecs_cluster" "this" {
  name = "ecs-lab-cluster"
}

# Task Definition (pakai LabRole)
resource "aws_ecs_task_definition" "this" {
  family                   = "my-app-task"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = "512"
  memory                   = "1024"

  execution_role_arn = "arn:aws:iam::${data.aws_caller_identity.current.account_id}:role/LabRole"
  task_role_arn      = "arn:aws:iam::${data.aws_caller_identity.current.account_id}:role/LabRole"

  container_definitions = jsonencode([
    {
      name  = "php-app"
      image = "562523702594.dkr.ecr.us-east-1.amazonaws.com/php-app:latest"
      essential = true
      memoryReservation = 512
      portMappings = [{ containerPort = 80, protocol = "tcp" }]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group" = "/ecs/my-app-service"
          "awslogs-region" = "us-east-1"
          "awslogs-stream-prefix" = "php-app"
        }
      }
    },
    {
      name  = "redis"
      image = "562523702594.dkr.ecr.us-east-1.amazonaws.com/redis-db:latest"
      essential = true
      memoryReservation = 256
      portMappings = [{ containerPort = 6379, protocol = "tcp" }]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group" = "/ecs/my-app-service"
          "awslogs-region" = "us-east-1"
          "awslogs-stream-prefix" = "redis"
        }
      }
    }
  ])

}


# ECS Service (tanpa ALB)
resource "aws_ecs_service" "this" {
  name            = "my-app-service"
  cluster         = aws_ecs_cluster.this.id
  task_definition = aws_ecs_task_definition.this.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets         = var.subnets
    security_groups = var.security_groups
    assign_public_ip = true
  }
}


# Dapetin account ID buat LabRole ARN
data "aws_caller_identity" "current" {}
