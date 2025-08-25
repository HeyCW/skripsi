# 📋 Redis List Management - Latihan Mahasiswa

## 📋 Deskripsi Tugas
Anda akan melengkapi aplikasi manajemen list menggunakan **Redis List** data structure. Aplikasi ini dapat menambah, menghapus, dan menampilkan data dalam bentuk list dengan operasi LPUSH, RPUSH, LPOP, dan RPOP.

## 🎯 Tujuan Pembelajaran
- Memahami konsep **Redis List** dan operasi dasarnya
- Implementasi **CRUD operations** untuk Redis List (Create, Read, Delete)
- Mengintegrasikan **backend PHP** dengan **frontend JavaScript**  
- Menangani **real-time updates** dan **error handling**
- Membuat **interface yang responsif** untuk list management

## 📂 File Structure
```
├── index.html           # Frontend dengan TODO items
├── redis-list.php       # Backend PHP dengan TODO items  
├── README.md           # Instruksi ini
└── composer.json       # PHP dependencies (optional)
```

## ✅ TODO Items Yang Harus Dikerjakan

### 🌐 Frontend JavaScript (index.html)
- **TODO 1**: Implementasi function `handleAction()` - kirim request ke server
- **TODO 2**: Implementasi function `loadPeopleData()` - ambil data dari Redis
- **TODO 3**: Implementasi function `updateTable()` - display data dalam tabel
- **TODO 4**: Setup event listeners untuk buttons dan input field

### ⚙️ Backend PHP (redis-list.php)
- **TODO 5**: Setup Redis connection dan configuration
- **TODO 6**: Handler untuk LPUSH operation (tambah di awal list)
- **TODO 7**: Handler untuk RPUSH operation (tambah di akhir list)
- **TODO 8**: Handler untuk LPOP operation (hapus dari awal list)  
- **TODO 9**: Handler untuk RPOP operation (hapus dari akhir list)
- **TODO 10**: Handler untuk GET operation (retrieve all list data)

## 🚀 Cara Menjalankan

### 1. Start Web Server
```bash
# PHP Built-in Server
php -S localhost:8000

# Atau gunakan Apache/Nginx
```

### 2. Akses Aplikasi
Buka browser dan akses: `http://localhost:8000`


## 🎉 Selamat Mengerjakan!
Latihan ini akan membantu Anda memahami **Redis List operations** yang fundamental dalam aplikasi real-time dan caching. **Good luck!** 🚀