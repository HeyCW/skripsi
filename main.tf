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

  project_name    =  var.project_name
  environment     = var.environment  
  vpc_name        = var.vpc_name
  vpc_cidr        = var.vpc_cidr
  vpc_azs         = var.vpc_azs
  private_subnets = var.private_subnets
  public_subnets  = var.public_subnets
}

module "bucket" {
  source = "./modules/s3"
  project_name              = var.project_name
  environment                = var.environment
  versioning_enabled         = true
  public_access_block        = var.public_access_block
  server_side_encryption     = var.server_side_encryption
  s3_lifecycle_rules         = var.s3_lifecycle_rules
  additional_tags            = var.additional_tags

}

module "ec2" {
  source = "./modules/ec2"

  project_name           = var.project_name
  environment            = var.environment
  aws_region             = var.aws_region
  vpc_id                 = module.network.vpc_id
  subnet_ids             = module.network.public_subnet_ids
  aws_sg_id              = module.network.web_sg_id
  instance_count         = var.instance_count
  instance_type          = var.instance_type
  ami_id                 = var.ami_id
  associate_public_ip    = var.associate_public_ip
  create_key_pair        = var.create_key_pair
  public_key             = var.public_key
  existing_key_name      = var.existing_key_name
  root_volume_type       = var.root_volume_type
  root_volume_size       = var.root_volume_size
  delete_on_termination  = var.delete_on_termination
  encrypt_root_volume    = var.encrypt_root_volume
  additional_ebs_volumes = var.additional_ebs_volumes
  user_data_script       = file(var.user_data_script)
  create_eip             = var.create_eip
}

module "cloud9" {
  source = "./modules/cloud9"

  project_name                =  var.project_name  
  environment_name            = var.cloud9_environment_name
  image_id                    = var.cloud9_image_id
  instance_type               = var.cloud9_instance_type
  description                 = var.cloud9_description
  automatic_stop_time_minutes = var.cloud9_automatic_stop_time_minutes
  subnet_id                   = module.network.public_subnet_ids[0]
  tags                        = var.cloud9_tags
  
}

data "http" "setup" {
  url = "https://raw.githubusercontent.com/HeyCW/skripsi/refs/heads/main/assignments/redis-lab/redis-timeseries/setup.sh"
}


resource "aws_s3_object" "setup" {
  bucket       = module.bucket.bucket_id
  key          = "redis-lab/redis-timeseries/setup.sh"
  content      = data.http.setup.response_body
  content_type = "application/x-httpd-php"
}

# locals {
#   # Debug user data script content
#   user_data_content = var.user_data_script != "" ? file(var.user_data_script) : var.user_data_script
#   user_data_encoded = var.user_data_script != "" ? base64encode(file(var.user_data_script)) : var.user_data_script
# }