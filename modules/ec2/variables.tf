# variables.tf untuk EC2 module

variable "project_name" {
  description = "Name of the project"
  type        = string
}

variable "environment" {
  description = "Environment (dev, staging, prod)"
  type        = string
  default     = "dev"
}

variable "vpc_id" {
  description = "VPC ID where EC2 instances will be created"
  type        = string
}

variable "subnet_ids" {
  description = "List of subnet IDs where EC2 instances will be placed"
  type        = list(string)
}

variable "instance_count" {
  description = "Number of EC2 instances to create"
  type        = number
  default     = 1
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.micro"
}

variable "ami_id" {
  description = "AMI ID to use for the instance. If empty, will use latest Amazon Linux 2"
  type        = string
  default     = ""
}

variable "associate_public_ip" {
  description = "Whether to associate a public IP address with the instance"
  type        = bool
  default     = false
}

# Key Pair Variables
variable "create_key_pair" {
  description = "Whether to create a new key pair"
  type        = bool
  default     = false
}

variable "public_key" {
  description = "Public key content for SSH access (required if create_key_pair is true)"
  type        = string
  default     = ""
}

variable "existing_key_name" {
  description = "Name of existing key pair to use (required if create_key_pair is false)"
  type        = string
  default     = ""
}

# Storage Variables
variable "root_volume_type" {
  description = "Type of root volume (gp2, gp3, io1, io2)"
  type        = string
  default     = "gp3"
}

variable "root_volume_size" {
  description = "Size of root volume in GB"
  type        = number
  default     = 20
}

variable "delete_on_termination" {
  description = "Whether to delete root volume on instance termination"
  type        = bool
  default     = true
}

variable "encrypt_root_volume" {
  description = "Whether to encrypt root volume"
  type        = bool
  default     = true
}

variable "additional_ebs_volumes" {
  description = "List of additional EBS volumes to attach"
  type = list(object({
    device_name           = string
    volume_type           = string
    volume_size           = number
    delete_on_termination = bool
    encrypted             = bool
  }))
  default = []
}

variable "user_data_script" {
  description = "User data script to run on instance startup"
  type        = string
  default     = ""
}

variable "detailed_monitoring" {
  description = "Enable detailed monitoring"
  type        = bool
  default     = false
}

variable "create_eip" {
  description = "Whether to create and associate Elastic IP"
  type        = bool
  default     = false
}

variable "aws_sg_id" {
  description = "Security group ID for EC2 instances (if empty, will create new one)"
  type        = string
  default     = ""
}