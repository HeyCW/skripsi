from google.oauth2.service_account import Credentials
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseUpload
from io import BytesIO
from datetime import datetime

class ServiceAccountUploader:
    def __init__(self):
        self.service = None
        self.setup_service()
    
    def setup_service(self):
        """Setup Google Drive service dengan Service Account"""
        try:
            CLIENT_SERVICE_FILE = './credentials/sheets-connection-471408-ef39a4069644.json'
            SCOPE = ['https://www.googleapis.com/auth/drive']
            
            creds = Credentials.from_service_account_file(CLIENT_SERVICE_FILE, scopes=SCOPE)
            self.service = build('drive', 'v3', credentials=creds)
            
            print("✅ Service Account berhasil diinisialisasi!")
            return True
            
        except Exception as e:
            print(f"❌ Error setup service: {e}")
            return False
    
    def show_setup_instructions(self):
        """Tampilkan instruksi setup shared folder"""
        print("=" * 70)
        print("📋 CARA SETUP SHARED FOLDER UNTUK SERVICE ACCOUNT")
        print("=" * 70)
        print("1. Buka Google Drive di browser (dengan akun Gmail biasa)")
        print("2. Klik 'New' → 'Folder'")
        print("3. Beri nama folder: 'Service Account Upload'")
        print("4. Klik kanan pada folder → 'Share'")
        print("5. Di kolom 'Add people and groups', masukkan:")
        print("   📧 sheets-connection@sheets-connection-471408.iam.gserviceaccount.com")
        print("6. Pilih permission: 'Editor' (bisa edit dan upload)")
        print("7. Klik 'Send'")
        print("8. Copy URL folder dari address bar:")
        print("   https://drive.google.com/drive/folders/FOLDER_ID_DISINI")
        print("9. Folder ID adalah bagian setelah '/folders/'")
        print("10. Paste folder ID ke dalam kode Python")
        print("=" * 70)
        print("💡 TIPS:")
        print("   - Folder ini akan tetap ada di Drive personal Anda")
        print("   - Service Account hanya bisa akses folder yang di-share")
        print("   - Gratis, tidak perlu Google Workspace")
        print("=" * 70)
    
    def test_folder_access(self, folder_id):
        """Test apakah Service Account bisa akses folder"""
        if not self.service:
            return False
        
        try:
            # Coba akses folder
            folder_info = self.service.files().get(
                fileId=folder_id,
                fields="id, name, owners, permissions"
            ).execute()
            
            print(f"✅ Folder berhasil diakses!")
            print(f"📁 Nama: {folder_info['name']}")
            print(f"🆔 ID: {folder_info['id']}")
            
            # List files dalam folder
            self.list_files_in_folder(folder_id)
            return True
            
        except Exception as e:
            print(f"❌ Tidak bisa akses folder: {e}")
            if "404" in str(e):
                print("💡 Kemungkinan:")
                print("   - Folder ID salah")
                print("   - Folder belum di-share ke Service Account")
                print("   - Service Account belum diberi permission")
            return False
    
    def upload_file(self, file_name, file_content, mime_type, folder_id):
        """Upload file ke shared folder"""
        if not self.service:
            print("❌ Service tidak tersedia")
            return None
        
        try:
            file_metadata = {
                "name": file_name,
                "parents": [folder_id]
            }
            
            media = MediaIoBaseUpload(
                BytesIO(file_content.encode("utf-8") if isinstance(file_content, str) else file_content),
                mimetype=mime_type,
                resumable=True
            )
            
            uploaded_file = self.service.files().create(
                body=file_metadata,
                media_body=media,
                fields="id, name, webViewLink, parents"
            ).execute()
            
            print(f"✅ File berhasil diupload!")
            print(f"📄 Nama: {uploaded_file['name']}")
            print(f"🆔 ID: {uploaded_file['id']}")
            print(f"🔗 Link: {uploaded_file['webViewLink']}")
            return uploaded_file
            
        except Exception as e:
            print(f"❌ Error upload: {e}")
            return None
    
    def list_files_in_folder(self, folder_id):
        """List semua files dalam folder"""
        if not self.service:
            return []
        
        try:
            query = f"'{folder_id}' in parents and trashed=false"
            results = self.service.files().list(
                q=query,
                fields="files(id, name, mimeType, webViewLink, modifiedTime)",
                orderBy="modifiedTime desc"
            ).execute()
            
            files = results.get("files", [])
            print(f"\n📁 Files dalam folder ({len(files)} files):")
            
            if not files:
                print("   (Folder kosong)")
            else:
                for i, f in enumerate(files, 1):
                    print(f"   {i}. {f['name']}")
                    print(f"      🔗 {f['webViewLink']}")
                    print(f"      📅 {f['modifiedTime']}")
            
            return files
            
        except Exception as e:
            print(f"❌ Error listing files: {e}")
            return []
    
    def create_subfolder(self, folder_name, parent_folder_id):
        """Buat subfolder dalam shared folder"""
        if not self.service:
            return None
        
        try:
            folder_metadata = {
                'name': folder_name,
                'mimeType': 'application/vnd.google-apps.folder',
                'parents': [parent_folder_id]
            }
            
            folder = self.service.files().create(
                body=folder_metadata,
                fields='id, name, webViewLink'
            ).execute()
            
            print(f"✅ Subfolder berhasil dibuat!")
            print(f"📁 Nama: {folder['name']}")
            print(f"🆔 ID: {folder['id']}")
            print(f"🔗 Link: {folder['webViewLink']}")
            return folder['id']
            
        except Exception as e:
            print(f"❌ Error membuat subfolder: {e}")
            return None

def main():
    print("🚀 SERVICE ACCOUNT UPLOADER")
    print("Menggunakan Shared Folder di Google Drive Biasa - GRATIS!")
    print("=" * 60)
    print("✅ Tidak perlu Google Workspace")
    print("✅ Tidak perlu login manual")
    print("✅ Upload otomatis selamanya")
    print("=" * 60)
    
    uploader = ServiceAccountUploader()
    
    if not uploader.service:
        print("❌ Tidak bisa setup service account")
        return
    
    print("\nPilih aksi:")
    print("1. Lihat instruksi setup shared folder")
    print("2. Test akses folder yang sudah di-share")
    print("3. Upload file ke shared folder")
    print("4. Demo lengkap (jika folder sudah ready)")
    
    choice = input("\nPilihan (1-4): ").strip()
    
    if choice == "1":
        uploader.show_setup_instructions()
    
    elif choice == "2":
        folder_id = input("Masukkan Folder ID: ").strip()
        if folder_id:
            uploader.test_folder_access(folder_id)
        else:
            print("❌ Folder ID tidak boleh kosong")
    
    elif choice == "3":
        folder_id = input("Masukkan Folder ID: ").strip()
        if not folder_id:
            print("❌ Folder ID tidak boleh kosong")
            return
        
        # Test akses dulu
        if uploader.test_folder_access(folder_id):
            file_name = input("Nama file (default: test_upload.txt): ").strip() or "test_upload.txt"
            
            content = f"""File ini diupload menggunakan Service Account
ke shared folder di Google Drive biasa.

✅ Metode: Service Account + Shared Folder
💰 Biaya: GRATIS (tidak perlu Google Workspace)
📅 Timestamp: {datetime.now()}
🔧 Python version: 3.x

Folder ini di-share dari Gmail biasa ke Service Account,
sehingga Service Account bisa upload file otomatis tanpa login manual.

Kelebihan metode ini:
- Tidak perlu login manual
- Tidak perlu Google Workspace
- File tersimpan di Google Drive personal
- Bisa diakses kapan saja
- Automasi penuh
"""
            
            result = uploader.upload_file(file_name, content, "text/plain", folder_id)
            
            if result:
                print(f"\n🎉 SUCCESS! File berhasil diupload ke shared folder")
    
    elif choice == "4":
        # Demo lengkap dengan folder ID yang sudah diketahui
        folder_id = input("Masukkan Folder ID yang sudah di-setup: ").strip()
        if not folder_id:
            print("❌ Folder ID tidak boleh kosong")
            return
        
        print(f"\n🔄 Menjalankan demo lengkap...")
        
        # 1. Test akses
        print(f"\n1️⃣ Testing folder access...")
        if not uploader.test_folder_access(folder_id):
            return
        
        # 2. Buat subfolder
        print(f"\n2️⃣ Membuat subfolder...")
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        subfolder_id = uploader.create_subfolder(f"Demo_{timestamp}", folder_id)
        
        # 3. Upload multiple files
        print(f"\n3️⃣ Upload multiple files...")
        files_to_upload = [
            ("readme.txt", "Ini adalah file README untuk demo upload"),
            ("log.txt", f"Log file created at {datetime.now()}"),
            ("data.json", '{"status": "success", "method": "service_account"}')
        ]
        
        upload_folder = subfolder_id if subfolder_id else folder_id
        
        for file_name, content in files_to_upload:
            print(f"\n   📤 Uploading {file_name}...")
            uploader.upload_file(file_name, content, "text/plain", upload_folder)
        
        # 4. List semua files
        print(f"\n4️⃣ Final file listing...")
        uploader.list_files_in_folder(folder_id)
        
        print(f"\n🎉 Demo selesai! Semua file berhasil diupload.")
    
    else:
        print("❌ Pilihan tidak valid")

if __name__ == "__main__":
    main()