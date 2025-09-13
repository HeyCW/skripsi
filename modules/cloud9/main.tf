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

resource "aws_security_group" "cloud9_web_sg" {
  name_prefix = "${var.environment_name}-web-"
  description = "Security group for Cloud9 environment with HTTP access"
  vpc_id      = data.aws_subnet.cloud9_subnet.vpc_id

  ingress {
    from_port   = 8080
    to_port     = 8080
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTP access from internet"
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTPS access from internet"
  }

  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "SSH access for Cloud9"
  }

  ingress {
    from_port   = 8000
    to_port     = 8999
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "Development ports"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
    description = "All outbound traffic"
  }

  tags = merge(var.tags, {
    Name = "${var.environment_name}-web-sg"
  })

  lifecycle {
    create_before_destroy = true
  }
}


resource "aws_network_interface_sg_attachment" "cloud9_sg_attachment" {
  depends_on           = [data.aws_instance.cloud9_instance]
  security_group_id    = aws_security_group.cloud9_web_sg.id
  network_interface_id = data.aws_instance.cloud9_instance.network_interface_id
}