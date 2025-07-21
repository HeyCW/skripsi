variable "bucket_name" {
  description = "Name of the S3 bucket"
  type        = string
  validation {
    condition     = length(var.bucket_name) > 3 && length(var.bucket_name) <= 63
    error_message = "Bucket name must be between 3 and 63 characters."
  }
}

variable "environment" {
  description = "Environment name (e.g., dev, staging, prod)"
  type        = string
  default     = "dev"
}

variable "versioning_enabled" {
  description = "Enable versioning for the S3 bucket"
  type        = bool
  default     = true
}

variable "public_access_block" {
  description = "Block public access settings"
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

variable "server_side_encryption" {
  description = "Server-side encryption configuration"
  type = object({
    sse_algorithm     = string
    kms_master_key_id = string
  })
  default = {
    sse_algorithm     = "AES256"
    kms_master_key_id = null
  }
}

variable "lifecycle_rules" {
  description = "List of lifecycle rules"
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

variable "additional_tags" {
  description = "Additional tags to apply to the bucket"
  type        = map(string)
  default     = {}
}