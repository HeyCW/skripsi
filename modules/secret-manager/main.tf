resource "aws_secretsmanager_secret" "google_creds" {
  name        = "${var.project_name}-${var.environment}-google-creds"
  description = "Google Service Account credentials untuk akses Google Sheets dan Drive"
  
  tags = {
    Environment = var.environment
    Project     = var.project_name
    Type        = "google-credentials"
  }
}

resource "aws_secretsmanager_secret_version" "google_creds" {
  secret_id = aws_secretsmanager_secret.google_creds.id
  secret_string = jsonencode({
    credentials = "PASTE_YOUR_GOOGLE_CREDENTIALS_JSON_HERE"
  })
}

# Secret untuk Google Sheet ID
resource "aws_secretsmanager_secret" "google_sheet_id" {
  name        = "${var.project_name}-${var.environment}-google-sheet-id"
  description = "ID Google Sheet untuk menyimpan data"
  
  tags = {
    Environment = var.environment
    Project     = var.project_name
    Type        = "google-sheet-id"
  }
}

resource "aws_secretsmanager_secret_version" "google_sheet_id" {
  secret_id = aws_secretsmanager_secret.google_sheet_id.id
  secret_string = jsonencode({
    sheet_id = "YOUR_GOOGLE_SHEET_ID_HERE"
  })
}

# Secret untuk Google Drive Folder ID
resource "aws_secretsmanager_secret" "google_drive_folder_id" {
  name        = "${var.project_name}-${var.environment}-google-drive-folder-id"
  description = "ID folder Google Drive untuk upload file log"
  
  tags = {
    Environment = var.environment
    Project     = var.project_name
    Type        = "google-drive-folder-id"
  }
}

resource "aws_secretsmanager_secret_version" "google_drive_folder_id" {
  secret_id = aws_secretsmanager_secret.google_drive_folder_id.id
  secret_string = jsonencode({
    folder_id = "YOUR_GOOGLE_DRIVE_FOLDER_ID_HERE"
  })
}

# Secret untuk Email Configuration
resource "aws_secretsmanager_secret" "email_config" {
  name        = "${var.project_name}-${var.environment}-email-config"
  description = "Konfigurasi email untuk Amazon SES"
  
  tags = {
    Environment = var.environment
    Project     = var.project_name
    Type        = "email-config"
  }
}

resource "aws_secretsmanager_secret_version" "email_config" {
  secret_id = aws_secretsmanager_secret.email_config.id
  secret_string = jsonencode({
    sender_email    = "YOUR_SENDER_EMAIL@example.com"
    recipient_email = "YOUR_RECIPIENT_EMAIL@example.com"
  })
}
