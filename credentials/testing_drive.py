from google.oauth2.service_account import Credentials
from googleapiclient.discovery import build
import json

# Load credentials
with open('./credentials/sheets-connection-471408-ef39a4069644.json', 'r') as f:
    creds_data = json.load(f)

creds = Credentials.from_service_account_info(
    creds_data,
    scopes=['https://www.googleapis.com/auth/drive']
)

drive_service = build('drive', 'v3', credentials=creds)

# Test folder access
folder_id = "1uD_pYGORFq4xLRtTCwYG1MkHnpZ3Qmdl"
try:
    folder = drive_service.files().get(
        fileId=folder_id,
        fields='id,name,owners,permissions'
    ).execute()
    print(f"✅ Folder access OK: {folder['name']}")
    
    # List files in folder
    files = drive_service.files().list(
        q=f"'{folder_id}' in parents",
        fields='files(id,name)'
    ).execute()
    print(f"✅ Can list files: {len(files['files'])} files")
    
except Exception as e:
    print(f"❌ Folder access failed: {e}")