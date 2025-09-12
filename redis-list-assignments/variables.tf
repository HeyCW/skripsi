# =============================================================================
# GENERAL VARIABLES
# =============================================================================
variable "aws_region" {
  description = "AWS region for all resources"
  type        = string
  default     = "us-east-1"
}

variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be one of: dev, staging, prod."
  }
  default = "def"
}

variable "project_name" {
  description = "Enter your NRP:"
  type        = string
}

variable "additional_tags" {
  description = "Additional tags for all resources"
  type        = map(string)
}

# =============================================================================
# NETWORK VARIABLES
# =============================================================================
variable "vpc_name" {
  description = "Name of the VPC"
  type        = string
  default     = ""
}

locals {
  vpc_name = var.vpc_name != "" ? var.vpc_name : "vpc-${var.project_name}"
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
  type = list(object({
    id     = string
    status = string
    transitions = list(object({
      days          = number
      storage_class = string
    }))
    expiration_days = number
  }))
  default = []
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


# =============================================================================
# Cloud9 variables
# =============================================================================

variable "cloud9_environment_name" {
  description = "Name of the Cloud9 environment"
  type        = string
  default     = "my-cloud9-environment"
}

variable "cloud9_image_id" {
  description = "The identifier for the Amazon Machine Image (AMI) that's used to create the EC2 instance"
  type        = string
  default     = "amazonlinux-2-x86_64"
  
  validation {
    condition = contains([
      "amazonlinux-1-x86_64",
      "amazonlinux-2-x86_64", 
      "ubuntu-18.04-x86_64",
      "ubuntu-22.04-x86_64"
    ], var.cloud9_image_id)
    error_message = "The image_id must be one of the supported Cloud9 AMIs."
  }
  
}

variable "cloud9_instance_type" {
  description = "The type of instance to connect to the environment"
  type        = string
  default     = "t2.large"
  
  validation {
    condition = contains([
      "t2.micro", "t2.small", "t2.medium", "t2.large", "t2.xlarge", "t2.2xlarge",
      "t3.micro", "t3.small", "t3.medium", "t3.large", "t3.xlarge", "t3.2xlarge",
      "m4.large", "m4.xlarge", "m4.2xlarge", "m4.4xlarge", "m4.10xlarge",
      "m5.large", "m5.xlarge", "m5.2xlarge", "m5.4xlarge", "m5.8xlarge", "m5.12xlarge", "m5.16xlarge", "m5.24xlarge",
      "c4.large", "c4.xlarge", "c4.2xlarge", "c4.4xlarge", "c4.8xlarge",
      "c5.large", "c5.xlarge", "c5.2xlarge", "c5.4xlarge", "c5.9xlarge", "c5.12xlarge", "c5.18xlarge", "c5.24xlarge"
    ], var.cloud9_instance_type)
    error_message = "The instance_type must be a supported EC2 instance type for Cloud9."
  }
  
}


variable "cloud9_description" {
  description = "Description of the Cloud9 environment"
  type        = string
  default     = "My Cloud9 development environment"
  
}

variable "cloud9_automatic_stop_time_minutes" {
  description = "The number of minutes until the running instance is shut down after the environment has last been used"
  type        = number
  default     = 30
  
  validation {
    condition     = var.cloud9_automatic_stop_time_minutes >= 30 && var.cloud9_automatic_stop_time_minutes <= 20160
    error_message = "Automatic stop time must be between 30 minutes and 20160 minutes (2 weeks)."
  }
  
}

variable "cloud9_tags" {
  description = "A map of tags to assign to the Cloud9 environment"
  type        = map(string)
  default = {
    Environment = "development"
    Project     = "my-project"
  }
}
