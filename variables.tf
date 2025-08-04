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


# =============================================================================
# EC2 VARIABLES
# =============================================================================


variable "instance_count" {
  description = "Number of EC2 instances to create"
  type        = number
  default     = 1
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.micro"
}

variable "ami_id" {
  description = "AMI ID to use for the instance. If empty, will use latest Amazon Linux 2"
  type        = string
  default     = ""
}

variable "associate_public_ip" {
  description = "Whether to associate a public IP address with the instance"
  type        = bool
  default     = true
}

variable "create_key_pair" {
  description = "Whether to create a new key pair"
  type        = bool
  default     = false
}

variable "public_key" {
  description = "Public key content for SSH access (required if create_key_pair is true)"
  type        = string
  default     = ""
}

variable "existing_key_name" {
  description = "Name of existing key pair to use (required if create_key_pair is false)"
  type        = string
  default     = ""
}

variable "root_volume_type" {
  description = "Root volume type"
  type        = string
  default     = "gp3"
}
variable "root_volume_size" {
  description = "Size of root volume in GB"
  type        = number
  default     = 20
}

variable "delete_on_termination" {
  description = "Whether to delete root volume on instance termination"
  type        = bool
  default     = true
}

variable "encrypt_root_volume" {
  description = "Whether to encrypt the root volume"
  type        = bool
  default     = true
}

variable "additional_ebs_volumes" {
  description = "List of additional EBS volumes to attach to the instance"
  type = list(object({
    device_name           = string
    volume_type           = string
    volume_size           = number
    delete_on_termination = bool
    encrypted             = bool
  }))
  default = []
}

variable "user_data_script" {
  description = "User data script to run on instance startup"
  type        = string
  default     = "/scripts/pdds/redis/script.sh"
}

variable "create_eip" {
  description = "Whether to create an Elastic IP for the instance"
  type        = bool
  default     = false
}