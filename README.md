## Pengujian Aplikasi

Pengujian dilakukan berdasarkan aspek kualitas sistem yang telah ditentukan pada tahap desain.

| No | Fitur | Skenario Pengujian | Input | Output yang Diharapkan | Hasil | Status |
|----|------|-------------------|-------|------------------------|-------|--------|
| 1 | Registrasi | User mendaftar akun baru | Data valid (nama, email, password) | Akun berhasil dibuat | Berhasil membuat akun | ✅ Lulus |
| 2 | Login | User login dengan akun terdaftar | Email & password benar | Masuk ke dashboard | Berhasil login | ✅ Lulus |
| 3 | Login (Error) | User login dengan data salah | Password salah | Muncul pesan error | Error tampil | ✅ Lulus |
| 4 | Pembagian Biaya | Admin input total biaya | Sewa, listrik, air, WiFi | Sistem hitung biaya per orang | Perhitungan sesuai | ✅ Lulus |
| 5 | Lihat Biaya | Penghuni melihat biaya bulanan | - | Data biaya tampil | Data tampil benar | ✅ Lulus |
| 6 | Reminder | Admin kirim pengingat | Klik tombol reminder | Notifikasi muncul | Notifikasi tampil | ✅ Lulus |
| 7 | Tracking Pembayaran | Admin update status bayar | Status: sudah/belum | Status tersimpan | Data terupdate | ✅ Lulus |
| 8 | Lihat Status | Penghuni cek pembayaran | - | Status tampil | Status sesuai | ✅ Lulus |
| 9 | Pengeluaran | Input pengeluaran | Data pengeluaran | Data tersimpan | Berhasil disimpan | ✅ Lulus |
| 10 | Dashboard | Tampilkan ringkasan | - | Total iuran & saldo tampil | Data sesuai | ✅ Lulus |
