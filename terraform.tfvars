# =============================================================================
# GENERAL CONFIGURATION
# =============================================================================
aws_region   = "us-east-1"
environment  = "dev"

# Additional tags untuk semua resources
additional_tags = {
  Owner      = "student"
  University = "Universitas Kristen Petra"
  Course     = "pdds"
}

# =============================================================================
# NETWORK CONFIGURATION
# =============================================================================
vpc_cidr        = "10.0.0.0/16"
vpc_azs         = ["us-east-1a", "us-east-1b"]
private_subnets = ["10.0.1.0/24", "10.0.2.0/24"]
public_subnets  = ["10.0.101.0/24", "10.0.102.0/24"]

# =============================================================================
# S3 CONFIGURATION
# =============================================================================
enable_s3_versioning = true

# Encryption settings for S3 buckets
server_side_encryption = {
  kms_master_key_id = null
  sse_algorithm     = "AES256"
}

# Public access block settings for S3 buckets
public_access_block = {
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# KMS key deletion window
kms_deletion_window = 7