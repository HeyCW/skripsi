# modules/lambda/variables.tf
variable "project_name" {
  description = "Project name"
  type        = string
}

variable "environment" {
  description = "Environment (dev, staging, prod)"
  type        = string
}

variable "lambda_function_name" {
  description = "Lambda function name"
  type        = string
  default     = null
}

variable "lambda_filename" {
  description = "Path to Lambda function zip file"
  type        = string
  default     = "lambda-function.zip"
}

variable "lambda_layer_filename" {
  description = "Path to Lambda layer zip file"
  type        = string
  default     = "google-api-layer.zip"
}

variable "handler" {
  description = "Lambda function handler"
  type        = string
  default     = "lambda_function.lambda_handler"
}

variable "runtime" {
  description = "Lambda runtime"
  type        = string
  default     = "python3.11"
}

variable "timeout" {
  description = "Lambda timeout in seconds"
  type        = number
  default     = 300
}

variable "memory_size" {
  description = "Lambda memory size in MB"
  type        = number
  default     = 256
}

variable "s3_bucket_name" {
  description = "S3 bucket name for trigger"
  type        = string
}

variable "s3_bucket_arn" {
  description = "S3 bucket ARN for trigger"
  type        = string
}

variable "secret_name" {
  description = "AWS Secrets Manager secret name"
  type        = string
}

variable "secret_arn" {
  description = "AWS Secrets Manager secret ARN"
  type        = string
}

variable "s3_filter_prefix" {
  description = "S3 object key prefix filter"
  type        = string
  default     = "logs/"
}

variable "s3_filter_suffix" {
  description = "S3 object key suffix filter"
  type        = string
  default     = ".log"
}

variable "create_layer" {
  description = "Whether to create Lambda layer"
  type        = bool
  default     = true
}

variable "existing_layer_arn" {
  description = "ARN of existing Lambda layer (if create_layer is false)"
  type        = string
  default     = null
}

variable "additional_tags" {
  description = "Additional tags to apply to all resources"
  type        = map(string)
  default     = {}
}

variable "log_retention_days" {
  description = "CloudWatch logs retention period in days"
  type        = number
  default     = 14
}

variable "reserved_concurrent_executions" {
  description = "Reserved concurrent executions for Lambda function"
  type        = number
  default     = -1
}