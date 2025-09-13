output "cloud9_id" {
  description = "The ID of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.cloud9.id
}

output "cloud9_arn" {
  description = "The ARN of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.cloud9.arn
}

output "cloud9_type" {
  description = "The type of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.cloud9.type
}

output "environment_name" {
  description = "The name of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.cloud9.name
}

output "instance_type" {
  description = "The instance type of the Cloud9 Environment"
  value       = aws_cloud9_environment_ec2.cloud9.instance_type
}

# Data source untuk mendapatkan region saat ini
data "aws_region" "current" {}

output "environment_url" {
  description = "URL to access the Cloud9 Environment"
  value       = "https://${data.aws_region.current.id}.console.aws.amazon.com/cloud9/ide/${aws_cloud9_environment_ec2.cloud9.id}"
}

output "cloud9_ec2_id" {
  description = "The ID of the EC2 instance running the Cloud9 Environment"
  value       = data.aws_instance.cloud9_instance.id
}


output "cloud9_ip" {
  description = "IP for Cloud9"
  value = data.aws_instance.cloud9_instance.public_ip
}