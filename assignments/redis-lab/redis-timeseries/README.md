# Redis TimeSeries Temperature Analysis - Latihan Mahasiswa

## Deskripsi Tugas
Anda akan melengkapi aplikasi analisis data temperatur menggunakan **Redis TimeSeries**. Aplikasi ini dapat mengupload file CSV, menyimpan data sebagai time series, membuat aggregation yearly, dan menampilkan data dalam bentuk tabel interaktif.

## Tujuan Pembelajaran
- Memahami konsep **Redis TimeSeries** dan operasi dasarnya
- Implementasi **aggregation rules** untuk kompaksi data yearly
- Mengintegrasikan **backend PHP** dengan **frontend JavaScript**  
- Menangani **raw data** vs **aggregated data**
- Membuat **interface yang interaktif** untuk visualisasi data

## Setup Environment

## ✅ TODO Items Yang Harus Dikerjakan

### 🔧 Backend PHP (redis-timeseries.php)
- **TODO 1**: Lengkapi konfigurasi koneksi Redis
- **TODO 2**: Implementasi function `uploadCsv()` - buat TimeSeries dan rules
- **TODO 2B**: Proses CSV rows dan insert data ke TimeSeries  
- **TODO 3**: Implementasi function `getDataFromRedis()` - ambil raw data
- **TODO 4**: Lengkapi aggregation rules dengan window yearly
- **TODO 5**: Implementasi function untuk ambil aggregated data
- **TODO 6**: HTTP request handling dan API routing (POST/GET methods)

### 🌐 Frontend JavaScript (index.html)
- **TODO 6**: Implementasi function `loadData()` untuk fetch data
- **TODO 7**: Implementasi function `createTable()` untuk display data
- **TODO 8**: Event listeners untuk button RAW dan AGR
- **TODO 9**: Upload form handler dengan response JSON

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
Latihan ini akan membantu Anda memahami konsep **time series database** yang sangat penting di era big data dan IoT. **Good luck!** 🚀