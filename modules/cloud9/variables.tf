variable "environment_name" {
  description = "Name of the Cloud9 environment"
  type        = string
  default     = "my-cloud9-environment"
}

variable "image_id" {
  description = "The identifier for the Amazon Machine Image (AMI) that's used to create the EC2 instance"
  type        = string
  default     = "amazonlinux-2-x86_64"
  
  validation {
    condition = contains([
      "amazonlinux-1-x86_64",
      "amazonlinux-2-x86_64", 
      "ubuntu-18.04-x86_64",
      "ubuntu-22.04-x86_64"
    ], var.image_id)
    error_message = "The image_id must be one of the supported Cloud9 AMIs."
  }
}

variable "instance_type" {
  description = "The type of instance to connect to the environment"
  type        = string
  default     = "t2.large"
  
  validation {
    condition = contains([
      "t2.micro", "t2.small", "t2.medium", "t2.large", "t2.xlarge", "t2.2xlarge",
      "t3.micro", "t3.small", "t3.medium", "t3.large", "t3.xlarge", "t3.2xlarge",
      "m4.large", "m4.xlarge", "m4.2xlarge", "m4.4xlarge", "m4.10xlarge",
      "m5.large", "m5.xlarge", "m5.2xlarge", "m5.4xlarge", "m5.8xlarge", "m5.12xlarge", "m5.16xlarge", "m5.24xlarge",
      "c4.large", "c4.xlarge", "c4.2xlarge", "c4.4xlarge", "c4.8xlarge",
      "c5.large", "c5.xlarge", "c5.2xlarge", "c5.4xlarge", "c5.9xlarge", "c5.12xlarge", "c5.18xlarge", "c5.24xlarge"
    ], var.instance_type)
    error_message = "The instance_type must be a supported EC2 instance type for Cloud9."
  }
}

variable "description" {
  description = "Description of the Cloud9 environment"
  type        = string
  default     = "My Cloud9 development environment"
}

variable "automatic_stop_time_minutes" {
  description = "The number of minutes until the running instance is shut down after the environment has last been used"
  type        = number
  default     = 30
  
  validation {
    condition     = var.automatic_stop_time_minutes >= 30 && var.automatic_stop_time_minutes <= 20160
    error_message = "Automatic stop time must be between 30 minutes and 20160 minutes (2 weeks)."
  }
}

variable "subnet_id" {
  description = "The ID of the subnet in Amazon VPC that AWS Cloud9 will use to communicate with the Amazon EC2 instance"
  type        = string
  default     = ""
}

variable "tags" {
  description = "A map of tags to assign to the resource"
  type        = map(string)
  default = {
    Environment = "development"
    Project     = "my-project"
  }
}