# =============================================================================
# GENERAL VARIABLES
# =============================================================================
variable "aws_region" {
  description = "AWS region for all resources"
  type        = string
  default     = "us-west-1"
}

variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be one of: dev, staging, prod."
  }
}

variable "project_name" {
  description = "Project name for resource naming"
  type        = string
  default     = "skripsi"
}

variable "additional_tags" {
  description = "Additional tags for all resources"
  type        = map(string)
  default     = {}
}

# =============================================================================
# NETWORK VARIABLES
# =============================================================================
variable "vpc_name" {
  description = "Name of the VPC"
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR block for VPC"
  type        = string
  validation {
    condition     = can(cidrhost(var.vpc_cidr, 0))
    error_message = "VPC CIDR must be a valid IPv4 CIDR block."
  }
}

variable "vpc_azs" {
  description = "List of availability zones"
  type        = list(string)
}

variable "private_subnets" {
  description = "List of private subnet CIDR blocks"
  type        = list(string)
}

variable "public_subnets" {
  description = "List of public subnet CIDR blocks"
  type        = list(string)
}

# =============================================================================
# S3 VARIABLES
# =============================================================================
variable "enable_s3_versioning" {
  description = "Enable S3 bucket versioning"
  type        = bool
  default     = true
}

variable "server_side_encryption" {
  description = "Server-side encryption configuration for S3 buckets"
  type = object({
    sse_algorithm     = string
    kms_master_key_id = optional(string, null)
  })
  default = {
    sse_algorithm     = "AES256"
    kms_master_key_id = null
  }
}

variable "public_access_block" {
  description = "Public access block configuration for S3 buckets"
  type = object({
    block_public_acls       = bool
    block_public_policy     = bool
    ignore_public_acls      = bool
    restrict_public_buckets = bool
  })
  default = {
    block_public_acls       = true
    block_public_policy     = true
    ignore_public_acls      = true
    restrict_public_buckets = true
  }
  
}

variable "s3_lifecycle_rules" {
  description = "S3 lifecycle rules configuration"
  type = object({
    raw_data_to_ia_days       = optional(number, 30)
    raw_data_to_glacier_days  = optional(number, 90)
    processed_data_to_ia_days = optional(number, 60)
    temp_data_expiry_days     = optional(number, 7)
    logs_expiry_days          = optional(number, 90)
  })
  default = {}
}

variable "kms_deletion_window" {
  description = "KMS key deletion window in days"
  type        = number
  default     = 7
  validation {
    condition     = var.kms_deletion_window >= 7 && var.kms_deletion_window <= 30
    error_message = "KMS deletion window must be between 7 and 30 days."
  }
}