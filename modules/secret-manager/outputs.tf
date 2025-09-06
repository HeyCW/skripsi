output "google_creds_secret_arn" {
  description = "ARN dari secret Google credentials"
  value       = aws_secretsmanager_secret.google_creds.arn
}

output "google_sheet_id_secret_arn" {
  description = "ARN dari secret Google Sheet ID"
  value       = aws_secretsmanager_secret.google_sheet_id.arn
}

output "google_drive_folder_id_secret_arn" {
  description = "ARN dari secret Google Drive Folder ID"
  value       = aws_secretsmanager_secret.google_drive_folder_id.arn
}

output "email_config_secret_arn" {
  description = "ARN dari secret Email configuration"
  value       = aws_secretsmanager_secret.email_config.arn
}


output "secret_names" {
  description = "Nama-nama semua secrets"
  value = {
    google_creds            = aws_secretsmanager_secret.google_creds.name
    google_sheet_id         = aws_secretsmanager_secret.google_sheet_id.name
    google_drive_folder_id  = aws_secretsmanager_secret.google_drive_folder_id.name
    email_config           = aws_secretsmanager_secret.email_config.name
  }
}
