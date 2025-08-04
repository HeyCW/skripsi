# ====================================
# EMR Configuration - Main File
# ====================================
# Variables are defined in variables.tf

# ====================================
# Data Sources for Existing Resources
# ====================================
data "aws_iam_role" "emr_service_role" {
  name = "EMR_DefaultRole"
}

# Conditional data source for existing S3 bucket
data "aws_s3_bucket" "emr_bucket" {
  count  = var.log_bucket_name != null ? 1 : 0
  bucket = var.log_bucket_name
}

# Create S3 bucket if log_bucket_name is null
resource "aws_s3_bucket" "emr_bucket" {
  count  = var.log_bucket_name == null ? 1 : 0
  bucket = "${var.project_name}-${var.environment}-emr-logs-${random_string.bucket_suffix[0].result}"

  tags = {
    Name        = "${var.project_name}-${var.environment}-emr-bucket"
    Environment = var.environment
    Project     = var.project_name
  }
}

resource "random_string" "bucket_suffix" {
  count   = var.log_bucket_name == null ? 1 : 0
  length  = 8
  special = false
  upper   = false
}

# Local to determine bucket name
locals {
  bucket_name = var.log_bucket_name != null ? data.aws_s3_bucket.emr_bucket[0].bucket : aws_s3_bucket.emr_bucket[0].bucket
}

data "aws_subnet" "emr_subnet" {
  id = var.infrastructure_config.subnet_id
}

data "aws_security_group" "master_sg" {
  id = var.infrastructure_config.master_security_group_id
}

data "aws_security_group" "slave_sg" {
  id = var.infrastructure_config.slave_security_group_id
}

data "aws_iam_instance_profile" "emr_profile" {
  name = split("/", var.infrastructure_config.instance_profile_arn)[1]
}

# ====================================
# Bootstrap Script
# ====================================
resource "aws_s3_object" "bootstrap_script" {
  bucket  = local.bucket_name
  key     = "bootstrap/install-python-packages.sh"
  content = <<-EOF
#!/bin/bash
# Install additional Python packages
sudo python3 -m pip install --upgrade pip
sudo python3 -m pip install pandas numpy matplotlib seaborn scikit-learn boto3 s3fs
# Install additional system packages if needed
sudo yum update -y
EOF

  tags = {
    Environment = var.environment
    Project     = var.project_name
  }
}

# ====================================
# EMR Cluster
# ====================================
resource "aws_emr_cluster" "cluster" {
  name          = "${var.project_name}-${var.environment}-cluster"
  release_label = var.emr_release_label
  applications  = ["Spark", "Hadoop", "Hive", "Livy", "JupyterEnterpriseGateway"]

  service_role = data.aws_iam_role.emr_service_role.arn

  termination_protection            = false
  keep_job_flow_alive_when_no_steps = false

  # Logging configuration
  log_uri = var.log_bucket_name != null ? "s3://${local.bucket_name}/logs/" : null

  # EC2 attributes
  ec2_attributes {
    key_name                          = var.infrastructure_config.key_name
    subnet_id                         = data.aws_subnet.emr_subnet.id
    emr_managed_master_security_group = data.aws_security_group.master_sg.id
    emr_managed_slave_security_group  = data.aws_security_group.slave_sg.id
    instance_profile                  = data.aws_iam_instance_profile.emr_profile.arn
  }

  # Master instance group
  master_instance_group {
    instance_type = var.master_instance_type
    name          = "master"

    ebs_config {
      size                 = 100
      type                 = "gp3"
      volumes_per_instance = 1
    }
  }

  # Core instance group with auto scaling
  core_instance_group {
    instance_type  = var.core_instance_type
    instance_count = var.core_instance_count
    name           = "core"

    ebs_config {
      size                 = 100
      type                 = "gp3"
      volumes_per_instance = 1
    }

    autoscaling_policy = jsonencode({
      Constraints = {
        MinCapacity = var.core_instance_count
        MaxCapacity = var.core_instance_count * 2
      }
      Rules = [
        {
          Name        = "ScaleOutMemoryPercentage"
          Description = "Scale out if YARNMemoryAvailablePercentage is less than 15"
          Action = {
            SimpleScalingPolicyConfiguration = {
              AdjustmentType    = "CHANGE_IN_CAPACITY"
              ScalingAdjustment = 1
              CoolDown          = 300
            }
          }
          Trigger = {
            CloudWatchAlarmDefinition = {
              ComparisonOperator = "LESS_THAN"
              EvaluationPeriods  = 1
              MetricName         = "YARNMemoryAvailablePercentage"
              Namespace          = "AWS/ElasticMapReduce"
              Period             = 300
              Statistic          = "AVERAGE"
              Threshold          = 15.0
              Unit               = "PERCENT"
              Dimensions = {
                JobFlowId = "$${emr.clusterId}"
              }
            }
          }
        },
        {
          Name        = "ScaleInMemoryPercentage"
          Description = "Scale in if YARNMemoryAvailablePercentage is greater than 75"
          Action = {
            SimpleScalingPolicyConfiguration = {
              AdjustmentType    = "CHANGE_IN_CAPACITY"
              ScalingAdjustment = -1
              CoolDown          = 300
            }
          }
          Trigger = {
            CloudWatchAlarmDefinition = {
              ComparisonOperator = "GREATER_THAN"
              EvaluationPeriods  = 1
              MetricName         = "YARNMemoryAvailablePercentage"
              Namespace          = "AWS/ElasticMapReduce"
              Period             = 300
              Statistic          = "AVERAGE"
              Threshold          = 75.0
              Unit               = "PERCENT"
              Dimensions = {
                JobFlowId = "$${emr.clusterId}"
              }
            }
          }
        }
      ]
    })
  }

  # Bootstrap actions
  bootstrap_action {
    path = "s3://${local.bucket_name}/bootstrap/install-python-packages.sh"
    name = "Install Python packages"
    args = []
  }

  # Configurations
  configurations_json = jsonencode([
    {
      Classification = "spark-defaults"
      Properties = {
        "spark.sql.adaptive.enabled"                    = "true"
        "spark.sql.adaptive.coalescePartitions.enabled" = "true"
        "spark.sql.adaptive.skewJoin.enabled"           = "true"
        "spark.serializer"                              = "org.apache.spark.serializer.KryoSerializer"
        "spark.sql.hive.metastorePartitionPruning"      = "true"
        "spark.sql.execution.arrow.pyspark.enabled"     = "true"
        "spark.dynamicAllocation.enabled"               = "true"
        "spark.dynamicAllocation.minExecutors"          = "1"
        "spark.dynamicAllocation.maxExecutors"          = "10"
      }
    },
    {
      Classification = "spark-env"
      Configurations = [
        {
          Classification = "export"
          Properties = {
            "PYSPARK_PYTHON" = "/usr/bin/python3"
          }
        }
      ]
    },
    {
      Classification = "jupyter-s3-conf"
      Properties = {
        "s3.persistence.enabled" = "true"
        "s3.persistence.bucket"  = local.bucket_name
      }
    }
  ])

  tags = {
    Name        = "${var.project_name}-${var.environment}-emr-cluster"
    Environment = var.environment
    Project     = var.project_name
  }

  depends_on = [
    aws_s3_object.bootstrap_script
  ]
}