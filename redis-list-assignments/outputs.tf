# output "debug_user_data_raw" {
#   description = "Raw user data content"
#   value       = local.user_data_content
# }

output "redis_endpoint" {
  description = "Redis endpoint"
  value       = module.ec2.public_ips[0]
}

output "cloud9_link" {
  description = "Link to access the Cloud9 environment"
  value       = module.cloud9.environment_url
}

output "cloud9_ip" {
  description = "IP address for Cloud9" 
  value = module.cloud9.cloud9_ip
}

output "bucket_name" {
  value = module.bucket.bucket_name
}

