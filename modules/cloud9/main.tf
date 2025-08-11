resource "aws_cloud9_environment_ec2" "cloud9" {
  name                        = var.environment_name
  image_id                    = var.image_id
  instance_type               = var.instance_type
  description                 = var.description
  automatic_stop_time_minutes = var.automatic_stop_time_minutes
  subnet_id                   = var.subnet_id
  
  tags = var.tags
}

# Data source untuk mendapatkan EC2 instance yang dibuat oleh Cloud9
data "aws_instances" "cloud9_instance" {
  depends_on = [aws_cloud9_environment_ec2.cloud9]
  
  filter {
    name   = "tag:aws:cloud9:environment"
    values = [aws_cloud9_environment_ec2.cloud9.id]
  }
  
  filter {
    name   = "instance-state-name"
    values = ["running"]
  }
}


# Data source untuk mendapatkan detail instance
data "aws_instance" "cloud9_instance" {
  depends_on  = [data.aws_instances.cloud9_instance]
  instance_id = data.aws_instances.cloud9_instance.ids[0]
}