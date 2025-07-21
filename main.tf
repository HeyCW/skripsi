# Configure AWS Provider
provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project     = var.project_name
      Environment = var.environment
      ManagedBy   = "terraform"
    }
  }
}

# Network Module
module "network" {
  source = "./modules/network"

  vpc_name        = var.vpc_name
  vpc_cidr        = var.vpc_cidr
  vpc_azs         = var.vpc_azs
  private_subnets = var.private_subnets
  public_subnets  = var.public_subnets
}

module "bucket" {
  source = "./modules/s3"

  bucket_name                = "${var.project_name}-${var.environment}-bucket"
  environment                = var.environment
  versioning_enabled         = true
  public_access_block        = var.public_access_block
  server_side_encryption     = var.server_side_encryption
  lifecycle_rules            = var.s3_lifecycle_rules
  additional_tags            = var.additional_tags
  
}

