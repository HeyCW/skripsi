# output "debug_user_data_raw" {
#   description = "Raw user data content"
#   value       = local.user_data_content
# }

output "redis_endpoint" {
  description = "Redis endpoint"
  value       = module.ec2.public_ips[0]
}

# output "cloud9_link" {
#   description = "Link to access the Cloud9 environment"
#   value       = module.cloud9.environment_url
# }

# output "cloud9_ec2_id" {
#   description = "The ID of the EC2 instance running the Cloud9 Environment"
#   value       = module.cloud9.cloud9_ec2_id
# }

output "lambda_function_arn" {
  value = module.lambda_processor.lambda_function_arn
}

output "lambda_function_name" {
  value = module.lambda_processor.lambda_function_name
}