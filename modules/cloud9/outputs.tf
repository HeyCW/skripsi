output "cloud9_id" {
  description = "The ID of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.example.id
}

output "cloud9_arn" {
  description = "The ARN of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.example.arn
}

output "cloud9_type" {
  description = "The type of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.example.type
}

output "environment_name" {
  description = "The name of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.example.name
}

output "instance_type" {
  description = "The instance type of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.example.instance_type
}

output "environment_url" {
  description = "URL to access the Cloud9 Environment"
  value       = "https://${data.aws_region.current.id}.console.aws.amazon.com/cloud9/ide/${aws_cloud9_environment_ec2.example.id}"
}

# Data source untuk mendapatkan region saat ini
data "aws_region" "current" {}