# output "debug_user_data_raw" {
#   description = "Raw user data content"
#   value       = local.user_data_content
# }

output "redis_endpoint" {
  description = "Redis endpoint"
  value       = module.ec2.public_ips[0]
}

output "bucket_name" {
  description = "The name of the S3 bucket"
  value       = module.bucket.bucket_name
}

output "cloud9_link" {
  description = "Link to access the Cloud9 environment"
  value       = module.cloud9.environment_url
}

output "cloud9_ec2_id" {
  description = "The ID of the EC2 instance running the Cloud9 Environment"
  value       = module.cloud9.cloud9_ec2_id
}