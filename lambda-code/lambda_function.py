import json
import boto3
import os
from io import BytesIO
from datetime import datetime
import base64

# Import library Google
from google.oauth2.service_account import Credentials
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseUpload

# Inisialisasi Klien AWS
s3_client = boto3.client('s3')
ses_client = boto3.client('ses', region_name=os.environ.get('AWS_REGION', 'us-east-1'))
sns_client = boto3.client('sns')
secrets_manager_client = boto3.client('secretsmanager')

def get_secret(secret_name):
    """Mengambil secret dari AWS Secrets Manager."""
    try:
        response = secrets_manager_client.get_secret_value(SecretId=secret_name)
        secret = json.loads(response['SecretString'])
        return secret
    except Exception as e:
        print(f"Error mengambil secret {secret_name}: {e}")
        raise e

def get_all_secrets():
    """Mengambil semua secrets yang diperlukan."""
    try:
        project_name = os.environ.get('PROJECT_NAME', 'skripsi')
        environment = os.environ.get('ENVIRONMENT', 'dev')
        
        # Ambil Google Sheet ID
        sheet_secret_name = f"{project_name}-{environment}-google-sheet-id"
        sheet_secret = get_secret(sheet_secret_name)
        
        # Ambil Email Config  
        email_secret_name = f"{project_name}-{environment}-email-config"
        email_secret = get_secret(email_secret_name)
        
        # Gabungkan semua secrets
        all_secrets = {
            'google_sheet_id': sheet_secret.get('sheet_id'),
            'sender_email': email_secret.get('sender_email'),
            'recipient_email': email_secret.get('recipient_email')
        }
        
        print(f"Secrets berhasil diambil: {list(all_secrets.keys())}")
        return all_secrets
        
    except Exception as e:
        print(f"Error mengambil secrets: {e}")
        raise e

def get_google_services():
    """Menginisialisasi layanan Google Sheets."""
    try:
        project_name = os.environ.get('PROJECT_NAME', 'skripsi')
        environment = os.environ.get('ENVIRONMENT', 'dev')
        
        # Ambil Google credentials dari secret manager
        creds_secret_name = f"{project_name}-{environment}-google-creds"
        creds_secret = get_secret(creds_secret_name)
        
        # Parse credentials JSON
        if 'credentials' in creds_secret:
            creds_json = creds_secret['credentials']
            if isinstance(creds_json, str):
                creds_data = json.loads(creds_json)
            else:
                creds_data = creds_json
        else:
            raise ValueError("Credentials not found in secret")
        
        creds = Credentials.from_service_account_info(
            creds_data,
            scopes=[
                'https://www.googleapis.com/auth/spreadsheets',
            ]
        )
        
        sheets_service = build('sheets', 'v4', credentials=creds)
        return sheets_service
        
    except Exception as e:
        print(f"Error menginisialisasi Google services: {e}")
        raise e

def process_log_content(log_content, filename):
    """
    Memproses isi file log untuk mendapatkan data.
    
    CATATAN: Sesuaikan fungsi ini dengan format log Anda
    """
    print(f"Memproses isi log dari file: {filename}")
    
    # Inisialisasi data default
    data = {
        "nrp": "N/A",
        "score": "N/A", 
        "filename": filename
    }
    
    try:
        # Contoh parsing - sesuaikan dengan format log Anda
        lines = log_content.strip().splitlines()
        
        for line in lines:
            line = line.strip()
            
            # Ekstrak NRP/NIM
            if "NRP:" in line or "nim:" in line.lower() or "NIM:" in line:
                try:
                    nrp = line.split(":")[1].strip().split(",")[0].split()[0]
                    data["nrp"] = nrp
                except:
                    pass
            
            # Ekstrak Score/Nilai
            if any(keyword in line.lower() for keyword in ["score:", "nilai:", "grade:"]):
                try:
                    score_part = line.split(":")[1].strip()
                    score = ''.join(filter(str.isdigit, score_part.split()[0]))
                    if score:
                        data["score"] = int(score)
                        # Tentukan status berdasarkan score
                        data["status"] = "Lulus" if int(score) >= 70 else "Tidak Lulus"
                except:
                    pass
            
            # Ekstrak status langsung jika ada
            if any(keyword in line.lower() for keyword in ["status:", "result:"]):
                try:
                    status = line.split(":")[1].strip()
                    data["status"] = status
                except:
                    pass
        
        print(f"Hasil parsing: {data}")
        return data
        
    except Exception as e:
        print(f"Error saat memproses log: {e}")
        return data

def update_google_sheet(service, sheet_id, data):
    """Menambahkan baris baru ke Google Sheet."""
    try:
        # Siapkan data untuk sheet - sesuaikan urutan kolom dengan sheet Anda
        timestamp = data.get('timestamp', datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
        values = [
            [
                timestamp,
                data.get('nrp', 'N/A'),
                data.get('score', 'N/A'),
                data.get('status', 'N/A'),
                data.get('filename', 'N/A')
            ]
        ]
        
        body = {
            'values': values,
            'majorDimension': 'ROWS'
        }
        
        # Tambahkan ke sheet
        result = service.spreadsheets().values().append(
            spreadsheetId=sheet_id,
            range='A:E',  # Sesuaikan dengan jumlah kolom
            valueInputOption='USER_ENTERED',
            insertDataOption='INSERT_ROWS',
            body=body
        ).execute()
        
        print(f"Data berhasil ditambahkan ke Google Sheet. Updated range: {result.get('updates', {}).get('updatedRange')}")
        return True
        
    except Exception as e:
        print(f"Error saat update Google Sheet: {e}")
        return False

def send_email_notification(sender_email, recipient_email, data, log_filename):
    """Mengirim notifikasi email menggunakan Amazon SES."""
    try:
        nrp = data.get('nrp', 'N/A')
        score = data.get('score', 'N/A')
        status = data.get('status', 'N/A')
        timestamp = data.get('timestamp', 'N/A')
        
        subject = f"Hasil Penilaian Otomatis - NRP: {nrp}"
        
        body_html = f"""
        <html>
        <head>
            <style>
                body {{ font-family: Arial, sans-serif; margin: 20px; }}
                .container {{ max-width: 600px; margin: 0 auto; }}
                .header {{ background-color: #f8f9fa; padding: 20px; border-radius: 8px; }}
                .content {{ padding: 20px; }}
                .data-table {{ width: 100%; border-collapse: collapse; margin: 20px 0; }}
                .data-table th, .data-table td {{ border: 1px solid #ddd; padding: 12px; text-align: left; }}
                .data-table th {{ background-color: #f2f2f2; }}
                .status-lulus {{ color: #28a745; font-weight: bold; }}
                .status-tidak-lulus {{ color: #dc3545; font-weight: bold; }}
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎓 Hasil Penilaian Otomatis</h1>
                    <p>Penilaian untuk file: <strong>{log_filename}</strong></p>
                </div>
                
                <div class="content">
                    <h2>Detail Hasil</h2>
                    <table class="data-table">
                        <tr>
                            <th>Field</th>
                            <th>Nilai</th>
                        </tr>
                        <tr>
                            <td>NRP</td>
                            <td>{nrp}</td>
                        </tr>
                        <tr>
                            <td>Skor</td>
                            <td>{score}</td>
                        </tr>
                        <tr>
                            <td>Status</td>
                            <td class="{'status-lulus' if 'lulus' in str(status).lower() else 'status-tidak-lulus'}">{status}</td>
                        </tr>
                        <tr>
                            <td>Waktu Proses</td>
                            <td>{timestamp}</td>
                        </tr>
                    </table>
                    <p><em>Email ini dikirim secara otomatis oleh sistem penilaian.</em></p>
                </div>
            </div>
        </body>
        </html>
        """
        
        ses_client.send_email(
            Destination={'ToAddresses': [recipient_email]},
            Message={
                'Body': {'Html': {'Charset': 'UTF-8', 'Data': body_html}},
                'Subject': {'Charset': 'UTF-8', 'Data': subject},
            },
            Source=sender_email
        )
        
        print(f"Email notifikasi berhasil dikirim ke {recipient_email}")
        return True
        
    except Exception as e:
        print(f"Error mengirim email: {e}")
        return False

def send_notification_with_attachment_via_sns(topic_arn, data, zip_file_path):
    """Send via SNS dengan size checking"""
    
    # Check file size
    file_size = os.path.getsize(zip_file_path)
    max_sns_size = 200 * 1024  # 200 KB (buffer untuk metadata)
    
    if file_size > max_sns_size:
        raise ValueError(f"File too large ({file_size} bytes) for SNS. Maximum allowed: {max_sns_size} bytes")
    
    # Small file - send via SNS
    with open(zip_file_path, 'rb') as f:
        file_content_base64 = base64.b64encode(f.read()).decode('utf-8')
    
    message = {
        'nrp': data.get('nrp'),
        'score': data.get('score'), 
        'status': data.get('status'),
        'file_attachment': file_content_base64,
        'file_name': os.path.basename(zip_file_path),
        'file_size': file_size
    }
    
    try:
        sns_client.publish(
            TopicArn=topic_arn,
            Message=json.dumps(message),
            Subject=f"Hasil Penilaian - {data.get('nrp')}"
        )
        print("File sent via SNS successfully")
        return True
    except Exception as e:
        print(f"SNS failed: {e}")
        return False

def lambda_handler(event, context):
    """Fungsi utama yang dieksekusi oleh AWS Lambda."""
    
    print("=== MULAI PROSES LAMBDA ===")
    print(f"Event: {json.dumps(event, indent=2)}")
    
    try:
        if 'Records' not in event or len(event['Records']) == 0:
            raise ValueError("Event tidak mengandung Records S3")
        
        s3_record = event['Records'][0]['s3']
        s3_bucket = s3_record['bucket']['name']
        s3_key = s3_record['object']['key']
        
        print(f"Mendeteksi file baru: s3://{s3_bucket}/{s3_key}")
        
        print("Mengambil secrets dari Secret Manager...")
        secrets = get_all_secrets()
        
        sheets_service = get_google_services()
        
        print("Membaca file dari S3...")
        try:
            response = s3_client.get_object(Bucket=s3_bucket, Key=s3_key)
            log_content = response['Body'].read().decode('utf-8')
            print(f"File berhasil dibaca. Ukuran: {len(log_content)} karakter")
        except Exception as e:
            print(f"Error membaca file dari S3: {e}")
            return {
                'statusCode': 500, 
                'body': json.dumps({'error': f'Gagal membaca file S3: {str(e)}'})
            }
        
        # 5. Proses konten log
        print("Memproses konten log...")
        processed_data = process_log_content(log_content, s3_key)
        
        # 6. Ambil konfigurasi dari secrets
        sheet_id = secrets.get('google_sheet_id')
        sender_email = secrets.get('sender_email')
        recipient_email = secrets.get('recipient_email')
        
        # Validasi konfigurasi
        missing_configs = []
        if not sheet_id: missing_configs.append('google_sheet_id')
        if not sender_email: missing_configs.append('sender_email')
        if not recipient_email: missing_configs.append('recipient_email')
        
        if missing_configs:
            raise ValueError(f"Konfigurasi tidak lengkap di Secret Manager: {', '.join(missing_configs)}")
        
        # 7. Jalankan semua aksi
        print("Menjalankan aksi...")
        
        # Update Google Sheet
        print("Updating Google Sheet...")
        sheet_result = update_google_sheet(sheets_service, sheet_id, processed_data)
        
        # Send Email Notification
        print("Sending email notification...")
        email_result = send_email_notification(
            sender_email, 
            recipient_email, 
            processed_data, 
            os.path.basename(s3_key)
        )
        
        # 8. Kumpulkan hasil
        results = {
            'file_processed': s3_key,
            'data_extracted': processed_data,
            'sheet_updated': sheet_result,
            'email_sent': email_result,
            'timestamp': datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }
        
        print(f"=== PROSES SELESAI ===")
        print(f"Hasil: {json.dumps(results, indent=2)}")
        
        return {
            'statusCode': 200,
            'body': json.dumps({
                'message': f'Proses untuk file {s3_key} berhasil diselesaikan!',
                'results': results
            }, indent=2)
        }
        
    except Exception as e:
        error_msg = f"Error dalam lambda_handler: {str(e)}"
        print(error_msg)
        
        return {
            'statusCode': 500,
            'body': json.dumps({
                'error': error_msg,
                'file': s3_key if 's3_key' in locals() else 'unknown'
            })
        }