import json
import boto3
import os
from io import BytesIO
from datetime import datetime

# Import library Google
from google.oauth2.service_account import Credentials
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseUpload

# Inisialisasi Klien AWS
s3_client = boto3.client('s3')
ses_client = boto3.client('ses', region_name=os.environ.get('AWS_REGION', 'ap-southeast-1'))
secrets_manager_client = boto3.client('secretsmanager')

# Nama secret yang disimpan di AWS Secrets Manager
# Sesuaikan dengan nama secret dari Terraform module kita
SECRET_NAME = os.environ.get('SECRET_NAME', 'skripsi-dev-google-drive-folder-id')

def get_secret():
    """Mengambil secret dari AWS Secrets Manager."""
    try:
        response = secrets_manager_client.get_secret_value(SecretId=SECRET_NAME)
        secret = json.loads(response['SecretString'])
        return secret
    except Exception as e:
        print(f"Error mengambil secret: {e}")
        raise e


def lambda_handler():
    # TODO implement
    print("Mengambil secrets...")
    secrets = get_secret()
    return {
        'statusCode': 200,
        'body': json.dumps(secrets)
    }
