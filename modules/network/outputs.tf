output "vpc_id" {
  value = aws_vpc.aws_vpc.id
}

output "private_subnet_ids" {
  value = aws_subnet.private-subnet[*].id
}

output "public_subnet_ids" {
  value = aws_subnet.public-subnet[*].id
}

output "web_sg_id" {
  description = "ID of the EC2 security group"
  value       = aws_security_group.web.id
}