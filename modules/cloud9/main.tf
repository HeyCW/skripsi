resource "aws_cloud9_environment_ec2" "example" {
  name                        = var.environment_name
  image_id                    = var.image_id
  instance_type               = var.instance_type
  description                 = var.description
  automatic_stop_time_minutes = var.automatic_stop_time_minutes
  subnet_id                   = var.subnet_id
  
  tags = var.tags
}