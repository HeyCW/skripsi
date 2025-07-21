resource "aws_s3_bucket" "this" {
  bucket = var.bucket_name
  
  tags = merge({
    Name        = var.bucket_name
    Environment = var.environment
    ManagedBy   = "Terraform"
  }, var.additional_tags)
}

resource "aws_s3_bucket_versioning" "this" {
  bucket = aws_s3_bucket.this.id
  
  versioning_configuration {
    status = var.versioning_enabled ? "Enabled" : "Disabled"
  }
}

resource "aws_s3_bucket_public_access_block" "this" {
  bucket = aws_s3_bucket.this.id

  block_public_acls       = var.public_access_block.block_public_acls
  block_public_policy     = var.public_access_block.block_public_policy
  ignore_public_acls      = var.public_access_block.ignore_public_acls
  restrict_public_buckets = var.public_access_block.restrict_public_buckets
}

resource "aws_s3_bucket_server_side_encryption_configuration" "this" {
  bucket = aws_s3_bucket.this.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm     = var.server_side_encryption.sse_algorithm
      kms_master_key_id = var.server_side_encryption.kms_master_key_id
    }
  }
}

resource "aws_s3_bucket_lifecycle_configuration" "this" {
  count  = length(var.lifecycle_rules) > 0 ? 1 : 0
  bucket = aws_s3_bucket.this.id

  dynamic "rule" {
    for_each = var.lifecycle_rules
    content {
      id     = rule.value.id
      status = rule.value.status

      dynamic "transition" {
        for_each = rule.value.transitions
        content {
          days          = transition.value.days
          storage_class = transition.value.storage_class
        }
      }

      expiration {
        days = rule.value.expiration_days
      }
    }
  }
}
