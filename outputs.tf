# output "debug_user_data_raw" {
#   description = "Raw user data content"
#   value       = local.user_data_content
# }

output "redis_endpoint" {
  description = "Redis endpoint"
  value       = module.ec2.public_ips[0]
}