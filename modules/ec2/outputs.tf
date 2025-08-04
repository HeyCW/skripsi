# outputs.tf untuk EC2 module

output "instance_ids" {
  description = "List of instance IDs"
  value       = aws_instance.ec2[*].id
}

output "instance_arns" {
  description = "List of instance ARNs"
  value       = aws_instance.ec2[*].arn
}

output "private_ips" {
  description = "List of private IP addresses"
  value       = aws_instance.ec2[*].private_ip
}

output "public_ips" {
  description = "List of public IP addresses"
  value       = aws_instance.ec2[*].public_ip
}

output "elastic_ips" {
  description = "List of Elastic IP addresses"
  value       = var.create_eip ? aws_eip.ec2_eip[*].public_ip : []
}

output "key_pair_name" {
  description = "Name of the key pair"
  value       = var.create_key_pair ? aws_key_pair.ec2_key[0].key_name : var.existing_key_name
}

output "instance_public_dns" {
  description = "List of public DNS names"
  value       = aws_instance.ec2[*].public_dns
}

output "instance_private_dns" {
  description = "List of private DNS names"
  value       = aws_instance.ec2[*].private_dns
}

output "availability_zones" {
  description = "List of availability zones where instances are launched"
  value       = aws_instance.ec2[*].availability_zone
}

output "subnet_ids_used" {
  description = "List of subnet IDs where instances are placed"
  value       = aws_instance.ec2[*].subnet_id
}