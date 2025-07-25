# ====================================
# Variables
# ====================================
variable "project_name" {
  description = "Name of the project"
  type        = string
  default     = "my-emr-project"
}

variable "environment" {
  description = "Environment name"
  type        = string
  default     = "dev"
}

variable "emr_release_label" {
  description = "EMR release version"
  type        = string
  default     = "emr-6.15.0"
}

variable "key_name" {
  description = "EC2 Key Pair name"
  type        = string
}

variable "master_instance_type" {
  description = "EC2 instance type for master node"
  type        = string
  default     = "m5.xlarge"
}

variable "core_instance_type" {
  description = "EC2 instance type for core nodes"
  type        = string
  default     = "m5.large"
}

variable "core_instance_count" {
  description = "Number of core instances"
  type        = number
  default     = 1
}


variable "log_bucket_name" {
  description = "S3 bucket name for EMR logs"
  type        = string
  default     = null
}

variable "infrastructure_config" {
  description = "Existing infrastructure configuration"
  type = object({
    subnet_id                = string
    master_security_group_id = string
    slave_security_group_id  = string
    instance_profile_arn     = string
    key_name                 = string
  })
}