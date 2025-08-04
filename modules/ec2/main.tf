# main.tf untuk EC2 module

# Data source untuk mendapatkan AMI terbaru
data "aws_ami" "amazon_linux" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["amzn2-ami-hvm-*-x86_64-gp2"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}

# Key pair untuk SSH access
resource "aws_key_pair" "ec2_key" {
  count      = var.create_key_pair ? 1 : 0
  key_name   = "${var.project_name}-${var.environment}-key"
  public_key = var.public_key

  tags = {
    Name        = "${var.project_name}-${var.environment}-key"
    Environment = var.environment
    Project     = var.project_name
    Terraform   = "true"
  }
}

# EC2 Instances
resource "aws_instance" "ec2" {
  count = var.instance_count

  ami                    = var.ami_id != "" ? var.ami_id : data.aws_ami.amazon_linux.id
  instance_type          = var.instance_type
  key_name               = var.create_key_pair ? aws_key_pair.ec2_key[0].key_name : var.existing_key_name
  vpc_security_group_ids = [var.aws_sg_id]
  subnet_id              = var.subnet_ids[count.index % length(var.subnet_ids)]

  associate_public_ip_address = var.associate_public_ip

  # Root volume configuration
  root_block_device {
    volume_type           = var.root_volume_type
    volume_size           = var.root_volume_size
    delete_on_termination = var.delete_on_termination
    encrypted             = var.encrypt_root_volume

    tags = {
      Name        = "${var.project_name}-${var.environment}-root-${count.index + 1}"
      Environment = var.environment
      Project     = var.project_name
      Terraform   = "true"
    }
  }

  # Additional EBS volumes
  dynamic "ebs_block_device" {
    for_each = var.additional_ebs_volumes
    content {
      device_name           = ebs_block_device.value.device_name
      volume_type           = ebs_block_device.value.volume_type
      volume_size           = ebs_block_device.value.volume_size
      delete_on_termination = ebs_block_device.value.delete_on_termination
      encrypted             = ebs_block_device.value.encrypted
    }
  }

  # User data script
  user_data = var.user_data_script

  # Monitoring
  monitoring = var.detailed_monitoring

  tags = {
    Name        = "${var.project_name}-${var.environment}-ec2-${count.index + 1}"
    Environment = var.environment
    Project     = var.project_name
    Terraform   = "true"
  }
}

# Elastic IP untuk instance (opsional)
resource "aws_eip" "ec2_eip" {
  count    = var.create_eip ? var.instance_count : 0
  instance = aws_instance.ec2[count.index].id
  domain   = "vpc"

  tags = {
    Name        = "${var.project_name}-${var.environment}-eip-${count.index + 1}"
    Environment = var.environment
    Project     = var.project_name
    Terraform   = "true"
  }

  depends_on = [aws_instance.ec2]
}