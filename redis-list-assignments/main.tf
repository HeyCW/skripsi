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
  source = "../modules/network"

  project_name    =  var.project_name
  environment     = var.environment  
  vpc_name        = var.vpc_name
  vpc_cidr        = var.vpc_cidr
  vpc_azs         = var.vpc_azs
  private_subnets = var.private_subnets
  public_subnets  = var.public_subnets
}

resource "random_string" "bucket_suffix" {
  length  = 8
  special = false
  upper   = false
}

module "bucket" {
  source = "../modules/s3"
  random_string = random_string.bucket_suffix.result
  project_name              = var.project_name
  environment                = var.environment
  versioning_enabled         = true
  public_access_block        = var.public_access_block
  server_side_encryption     = var.server_side_encryption
  s3_lifecycle_rules         = var.s3_lifecycle_rules
  additional_tags            = var.additional_tags

}

module "ec2" {
  source = "../modules/ec2"

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
  source = "../modules/cloud9"

  project_name                =  var.project_name  
  environment_name            = var.cloud9_environment_name
  image_id                    = var.cloud9_image_id
  instance_type               = var.cloud9_instance_type
  description                 = var.cloud9_description
  automatic_stop_time_minutes = var.cloud9_automatic_stop_time_minutes
  subnet_id                   = module.network.public_subnet_ids[0]
  tags                        = var.cloud9_tags 
}

module "ecs-fargate" {
  source = "../modules/ecs-fargate"
  subnets = [ module.network.public_subnet_ids[0], module.network.public_subnet_ids[1] ]
  security_groups = [ module.network.web_sg_id ]
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

# module "secret-manager" {
#   source = "./modules/secret-manager"
  
#   project_name = var.project_name
#   environment  = var.environment
  
#   secrets = {
#     google-sheet-id = {
#       description = "ID Google Sheet untuk menyimpan data"
#       secret_value = {
#         sheet_id = "YOUR_GOOGLE_SHEET_ID_HERE"
#       }
#     }
    
#     email-config = {
#       description = "Konfigurasi email untuk Amazon SES"
#       secret_value = {
#         sender_email    = "YOUR_SENDER_EMAIL@example.com"
#         recipient_email = "YOUR_RECIPIENT_EMAIL@example.com"
#       }
#     }
#   }

#   recovery_window_in_days = 0
# }

module "lambda_processor" {
  source = "../modules/lambda"

  # Required variables
  project_name   = "log-processor"
  environment    = "dev"
  
  # Lambda configuration
  lambda_filename       = "../lambda-code/lambda_function.zip"
  lambda_layer_filename = "../lambda-code/python.zip"
  handler              = "lambda_function.lambda_handler"
  runtime              = "python3.11"
  timeout              = 300
  memory_size          = 512
  
  # S3 configuration
  s3_bucket_name = module.bucket.bucket_name
  s3_bucket_arn  = module.bucket.bucket_arn
  s3_filter_prefix = "answer/"
  s3_filter_suffix = ".json"
  
  # Secrets Manager configuration
  secret_name = "google-api-credentials"
  secret_arn  = "arn:aws:secretsmanager:us-east-1:123456789012:secret:google-api-credentials-AbCdEf"
  
  # Layer configuration
  create_layer = true
  # existing_layer_arn = "arn:aws:lambda:us-east-1:123456789012:layer:my-existing-layer:1"
  
  # Additional tags
  additional_tags = {
  }
}

# locals {
#   # Debug user data script content
#   user_data_content = var.user_data_script != "" ? file(var.user_data_script) : var.user_data_script
#   user_data_encoded = var.user_data_script != "" ? base64encode(file(var.user_data_script)) : var.user_data_script
# }