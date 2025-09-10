data "aws_caller_identity" "current" {}
data "aws_region" "current" {}

data "aws_iam_role" "lambda_execution_role" {
  name = "LabRole"
}

locals {
  function_name = var.lambda_function_name != null ? var.lambda_function_name : "${var.project_name}-${var.environment}-processor"
  layer_name    = "${var.project_name}-${var.environment}-google-api-layer"
  
  common_tags = merge(
    {
      Project     = var.project_name
      Environment = var.environment
      ManagedBy   = "terraform"
    },
    var.additional_tags
  )
}

# Lambda Layer (if create_layer is true)
resource "aws_lambda_layer_version" "python_library" {
count           = var.create_layer ? 1 : 0
  filename        = var.lambda_layer_filename
  layer_name      = local.layer_name
  source_code_hash = filebase64sha256(var.lambda_layer_filename)
  
  compatible_runtimes = [var.runtime]
  description         = "Dependencies layer for ${var.project_name}"
}

# Lambda Function
resource "aws_lambda_function" "main" {
  filename         = var.lambda_filename
  function_name    = var.lambda_function_name
  role            = data.aws_iam_role.lambda_execution_role.arn
  handler         = var.handler
  source_code_hash = filebase64sha256(var.lambda_filename)
  runtime         = var.runtime
  timeout         = var.timeout
  memory_size     = var.memory_size

  # Configure layers
  layers = var.create_layer ? [aws_lambda_layer_version.python_library[0].arn] : (
    var.existing_layer_arn != null ? [var.existing_layer_arn] : []
  )

  # Environment variables
  environment {
    variables = {
      S3_BUCKET_NAME = var.s3_bucket_name
      SECRET_NAME    = var.secret_name
      ENVIRONMENT    = var.environment
      PROJECT_NAME   = var.project_name
    }
  }
}

# S3 Bucket Notification
# resource "aws_s3_bucket_notification" "bucket_notification" {
#   bucket = var.s3_bucket_name

#   lambda_function {
#     lambda_function_arn = aws_lambda_function.main.arn
#     events              = ["s3:ObjectCreated:*"]
#     filter_prefix       = var.s3_filter_prefix
#     filter_suffix       = var.s3_filter_suffix
#   }

#   depends_on = [aws_lambda_permission.s3_invoke]
# }