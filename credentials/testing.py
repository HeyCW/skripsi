from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload
from google.oauth2 import service_account
import gspread

# ==== Konfigurasi Credentials ====
SERVICE_ACCOUNT_FILE = "./credentials/sheets-connection-471408-ef39a4069644.json"  # file service account
SCOPES = [
    "https://www.googleapis.com/auth/drive",
    "https://www.googleapis.com/auth/spreadsheets",
]

# Load credentials
creds = service_account.Credentials.from_service_account_file(
    SERVICE_ACCOUNT_FILE, scopes=SCOPES
)

# ==== Upload File ke Google Drive ====
def upload_to_drive(file_path, file_name, folder_id=None):
    drive_service = build("drive", "v3", credentials=creds)

    file_metadata = {"name": file_name}
    if folder_id:
        file_metadata["parents"] = [folder_id]  # harus list, bukan string

    media = MediaFileUpload(file_path, resumable=True)
    file = drive_service.files().create(
        body=file_metadata, media_body=media, fields="id, parents"
    ).execute()

    print(f"File uploaded to Drive, ID: {file.get('id')}, Parents: {file.get('parents')}")
    return file.get("id")

def list_files_in_folder(folder_id):
    
    drive_service = build('drive', 'v3', credentials=creds)

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



# ==== Tulis Data ke Google Sheets ====
def write_to_sheets(sheet_id, range_name, values):
    gc = gspread.authorize(creds)
    sh = gc.open_by_key(sheet_id)
    worksheet = sh.sheet1
    worksheet.update(range_name, values)
    print(f"Data written to Google Sheets {sheet_id}")

# ==== Contoh Pemakaian ====
if __name__ == "__main__":
    # Upload file ke Drive (pakai folder_id)
    
    print(list_files_in_folder('1VgIm1vMYkvfdBS26VhaX4rhhJzMaXjR4'))
    # uploaded_file_id = upload_to_drive(
    #     "./credentials/test.txt",
    #     "Test Upload.txt",
    #     folder_id="1VgIm1vMYkvfdBS26VhaX4rhhJzMaXjR4"  # folder ID dari link
    # )

    # Tulis data ke Google Sheets
    SHEET_ID = "1By9e0g-aFr3A37lvdSBNeE2BEwmzuTkePPm_Z2UzJYQ"  # ganti dengan ID Sheet
    data = [
        ["Nama", "Umur"],
        ["Charles", 21],
        ["Budi", 22],
    ]
    write_to_sheets(SHEET_ID, "A1", data)
