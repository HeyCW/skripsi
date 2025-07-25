output "cluster_id" {
  description = "EMR cluster ID"
  value       = aws_emr_cluster.cluster.id
}

output "cluster_master_public_dns" {
  description = "EMR cluster master public DNS"
  value       = aws_emr_cluster.cluster.master_public_dns
}