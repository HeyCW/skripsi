output "vpc_id" {
  value = aws_vpc.aws_vpc.id
}

output "private_subnet_ids" {
  value = aws_subnet.private-subnet[*].id
}

output "public_subnet_ids" {
  value = aws_subnet.public-subnet[*].id
}