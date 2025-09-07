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
SECRET_NAME = os.environ.get('SECRET_NAME', 'my-project-dev-app-config')

def get_secret():
    """Mengambil secret dari AWS Secrets Manager."""
    try:
        response = secrets_manager_client.get_secret_value(SecretId=SECRET_NAME)
        secret = json.loads(response['SecretString'])
        return secret
    except Exception as e:
        print(f"Error mengambil secret: {e}")
        raise e

def get_google_services(secret):
    """Menginisialisasi layanan Google Drive dan Sheets."""
    try:
        # Ambil kredensial dari secret (sesuai struktur yang kita buat)
        if 'google_creds' in secret:
            # Jika menggunakan struktur terpisah
            creds_json = secret['google_creds']
            if isinstance(creds_json, str):
                creds_data = json.loads(creds_json)
            else:
                creds_data = creds_json
        else:
            # Fallback untuk struktur lama
            creds_data = json.loads(secret['credentials'])
        
        creds = Credentials.from_service_account_info(
            creds_data,
            scopes=[
                'https://www.googleapis.com/auth/spreadsheets',
                'https://www.googleapis.com/auth/drive'
            ]
        )
        
        sheets_service = build('sheets', 'v4', credentials=creds)
        drive_service = build('drive', 'v3', credentials=creds)
        return sheets_service, drive_service
        
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
        "nim": "N/A",
        "score": "N/A",
        "status": "Tidak Diketahui",
        "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "filename": filename
    }
    
    try:
        # Contoh parsing - sesuaikan dengan format log Anda
        lines = log_content.strip().splitlines()
        
        for line in lines:
            line = line.strip()
            
            # Ekstrak NIM
            if "NIM:" in line or "nim:" in line.lower():
                try:
                    nim = line.split(":")[1].strip().split(",")[0].split()[0]
                    data["nim"] = nim
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
                data.get('nim', 'N/A'),
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

def upload_to_google_drive(service, folder_id, filename, file_content):
    """Mengunggah file log ke folder Google Drive."""
    try:
        # Buat nama file yang unik dengan timestamp
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        unique_filename = f"{timestamp}_{filename}"
        
        file_metadata = {
            'name': unique_filename,
            'parents': [folder_id],
            'description': f'Log file uploaded at {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}'
        }
        
        # Upload file
        media = MediaIoBaseUpload(
            BytesIO(file_content.encode('utf-8')), 
            mimetype='text/plain',
            resumable=True
        )
        
        file = service.files().create(
            body=file_metadata,
            media_body=media,
            fields='id,name,webViewLink'
        ).execute()
        
        print(f"File berhasil diunggah ke Google Drive:")
        print(f"- File ID: {file.get('id')}")
        print(f"- File Name: {file.get('name')}")
        print(f"- Link: {file.get('webViewLink')}")
        
        return {
            'success': True,
            'file_id': file.get('id'),
            'file_name': file.get('name'),
            'link': file.get('webViewLink')
        }
        
    except Exception as e:
        print(f"Error saat upload ke Google Drive: {e}")
        return {'success': False, 'error': str(e)}

def send_email_notification(sender_email, recipient_email, data, log_filename, drive_result=None):
    """Mengirim notifikasi email menggunakan Amazon SES."""
    try:
        nim = data.get('nim', 'N/A')
        score = data.get('score', 'N/A')
        status = data.get('status', 'N/A')
        timestamp = data.get('timestamp', 'N/A')
        
        subject = f"Hasil Penilaian Otomatis - NIM: {nim}"
        
        # Buat link Google Drive jika ada
        drive_link = ""
        if drive_result and drive_result.get('success') and drive_result.get('link'):
            drive_link = f'<p><a href="{drive_result["link"]}" target="_blank">Lihat file di Google Drive</a></p>'
        
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
                            <td>NIM</td>
                            <td>{nim}</td>
                        </tr>
                        <tr>
                            <td>Skor</td>
                            <td>{score}</td>
                        </tr>
                        <tr>
                            <td>Status</td>
                            <td class="{'status-lulus' if 'lulus' in status.lower() else 'status-tidak-lulus'}">{status}</td>
                        </tr>
                        <tr>
                            <td>Waktu Proses</td>
                            <td>{timestamp}</td>
                        </tr>
                    </table>
                    
                    {drive_link}
                    
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

def lambda_handler(event, context):
    """Fungsi utama yang dieksekusi oleh AWS Lambda."""
    
    print("=== MULAI PROSES LAMBDA ===")
    print(f"Event: {json.dumps(event, indent=2)}")
    
    try:
        # 1. Validasi event
        if 'Records' not in event or len(event['Records']) == 0:
            raise ValueError("Event tidak mengandung Records S3")
        
        # 2. Ambil informasi file dari event S3
        s3_record = event['Records'][0]['s3']
        s3_bucket = s3_record['bucket']['name']
        s3_key = s3_record['object']['key']
        
        print(f"Mendeteksi file baru: s3://{s3_bucket}/{s3_key}")
        
        # 3. Ambil Kredensial & Konfigurasi
        print("Mengambil secrets...")
        secrets = get_secret()
        
        print("Menginisialisasi Google services...")
        sheets_service, drive_service = get_google_services(secrets)
        
        # 4. Baca konten file log dari S3
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
        drive_folder_id = secrets.get('google_drive_folder_id') 
        sender_email = secrets.get('sender_email')
        recipient_email = secrets.get('recipient_email')
        
        # Validasi konfigurasi
        missing_configs = []
        if not sheet_id: missing_configs.append('google_sheet_id')
        if not drive_folder_id: missing_configs.append('google_drive_folder_id')
        if not sender_email: missing_configs.append('sender_email')
        if not recipient_email: missing_configs.append('recipient_email')
        
        if missing_configs:
            raise ValueError(f"Konfigurasi tidak lengkap: {', '.join(missing_configs)}")
        
        # 7. Jalankan semua aksi
        print("Menjalankan aksi...")
        
        # Update Google Sheet
        print("Updating Google Sheet...")
        sheet_result = update_google_sheet(sheets_service, sheet_id, processed_data)
        
        # Upload ke Google Drive
        print("Uploading to Google Drive...")
        drive_result = upload_to_google_drive(drive_service, drive_folder_id, s3_key, log_content)
        
        # Kirim email notifikasi
        print("Sending email notification...")
        email_result = send_email_notification(
            sender_email, 
            recipient_email, 
            processed_data, 
            s3_key,
            drive_result
        )
        
        # 8. Kumpulkan hasil
        results = {
            'file_processed': s3_key,
            'data_extracted': processed_data,
            'sheet_updated': sheet_result,
            'drive_uploaded': drive_result.get('success', False) if isinstance(drive_result, dict) else drive_result,
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