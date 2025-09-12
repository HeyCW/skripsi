variable "random_string" {
  description = "Random String untuk memberi nama unik ke bucket"
  type = string
}

variable "project_name" {
  description = "Name of the project"
  type        = string
  default     = "my-emr-project"
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

variable "additional_tags" {
  description = "Additional tags to apply to the bucket"
  type        = map(string)
  default     = {}
}