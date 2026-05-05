# Catatan Revisi Diagram Warung Baleganjur

Dokumen ini digunakan sebagai acuan saat memperbaiki diagram dan narasi laporan agar konsisten dengan rancangan final sistem.

## 1. Prinsip Umum

- Nama aktor eksternal menggunakan istilah **Pelanggan**, bukan Customer.
- Aktor internal terdiri dari **Admin**, **Kasir**, dan **Koki**.
- Use case menggambarkan fungsi sistem dari sudut pandang pengguna, bukan struktur tabel database.
- Relasi `include` dan `extend` hanya digunakan jika benar-benar membantu menjelaskan hubungan antar use case.
- Fitur teknis kecil seperti logout, cetak struk, atau detail proses validasi tidak wajib dijadikan use case utama, kecuali memang dibutuhkan untuk memperjelas diagram.

## 2. Keputusan Use Case Diagram

Use case final dibagi menjadi dua kelompok utama:

### Fitur Pelanggan

- Melakukan Pemesanan Melalui QR Meja
- Melihat Status Pesanan
- Mengubah Pesanan
- Membatalkan Pesanan
- Membuat Waiting List / Booking Meja

### Fitur Internal

- Melakukan Login
- Melihat Dashboard
- Mengelola Pesanan
- Mengelola Kitchen Display
- Mengelola Pembayaran
- Mengelola Waiting List
- Mengelola Menu dan Addon
- Mengelola Meja dan QR Code
- Mengelola User dan Hak Akses
- Mengelola Data Referensi

## 3. Relasi Include dan Extend

### Extend pada Fitur Pelanggan

Relasi `extend` digunakan untuk fitur opsional yang muncul pada kondisi tertentu.

- **Mengubah Pesanan** `extend` **Melihat Status Pesanan**  
  Digunakan karena pelanggan hanya dapat mengubah pesanan melalui halaman status pesanan apabila status pesanan masih memungkinkan.

- **Membatalkan Pesanan** `extend` **Melihat Status Pesanan**  
  Digunakan karena pembatalan hanya tersedia pada kondisi tertentu, misalnya pesanan belum diproses lebih lanjut.

### Include pada Fitur Internal

Jika diagram memakai relasi ke login, arah panah `include` harus menuju **Melakukan Login**. Artinya fitur internal membutuhkan autentikasi sebelum dapat digunakan.

Catatan: secara UML yang lebih ketat, login dapat dijelaskan sebagai pra-kondisi. Namun, untuk kebutuhan revisi laporan, relasi `include` ke login masih dapat digunakan selama arahnya benar.

## 4. Catatan Khusus

### Waiting List

Waiting list pada sistem ini dapat dianggap sebagai fitur mandiri karena memiliki halaman dan alur sendiri, yaitu:

- melihat kondisi meja,
- memilih meja,
- memilih menu,
- mengirim booking,
- melihat status waiting list.

Oleh karena itu, **Membuat Waiting List / Booking Meja** boleh dihubungkan langsung ke aktor **Pelanggan** tanpa harus menjadi `extend` dari pemesanan QR meja.

### Pembayaran

Use case **Mengelola Pembayaran** tetap valid walaupun tidak ada tabel `pembayarans` tersendiri. Use case menjelaskan proses bisnis kasir, sedangkan data pembayaran disimpan di tabel `pesanans` karena satu pesanan hanya memiliki satu pembayaran.

Kolom pembayaran yang berada di tabel `pesanans` antara lain:

- `metode_pembayaran`
- `dibayar`
- `kembalian`
- `referensi_pembayaran`
- `waktu_selesai`

### Login

Login digunakan oleh aktor internal untuk mengakses fitur sesuai hak akses. Pada expanded use case, fitur internal sebaiknya memiliki pra-kondisi:

> Pengguna telah login dan memiliki hak akses terhadap fitur yang digunakan.

## 5. Checklist Konsistensi Diagram

Gunakan checklist berikut saat memperbaiki diagram lain:

- Use case, activity diagram, dan sequence diagram memakai nama proses yang sama.
- Aktor eksternal ditulis sebagai **Pelanggan**.
- Aktor internal ditulis sebagai **Admin**, **Kasir**, dan **Koki**.
- Fitur pelanggan tidak membutuhkan login.
- Fitur internal membutuhkan login atau hak akses.
- Mengubah dan membatalkan pesanan dijelaskan sebagai proses lanjutan dari melihat status pesanan.
- Waiting list dijelaskan sebagai proses mandiri, bukan tabel tersendiri.
- Pembayaran dijelaskan sebagai proses bisnis kasir, walaupun datanya tersimpan pada tabel pesanan.
- Data pembayaran tidak perlu dipisahkan menjadi tabel tersendiri untuk ruang lingkup sistem saat ini.

## 6. Implikasi ke Diagram Lain

### Activity Diagram

Activity diagram sebaiknya tetap mengikuti 15 proses utama yang sudah dibuat. Untuk login, cukup digambarkan sebagai proses internal. Untuk ubah dan batal pesanan, aktivitas dimulai dari aksi pada halaman status pesanan.

### Sequence Diagram

Sequence diagram sebaiknya menggunakan peserta utama:

- aktor,
- halaman,
- sistem,
- database.

Untuk pembayaran, sistem boleh mengambil data pesanan dari database lalu menyimpan data pembayaran kembali pada data pesanan.

### Class Diagram dan ERD

Class diagram dan ERD tetap mengikuti model/tabel utama sistem. Pembayaran tidak perlu menjadi class atau tabel terpisah selama satu pesanan hanya memiliki satu pembayaran.

### Expanded Use Case

Expanded use case internal perlu mencantumkan pra-kondisi bahwa pengguna telah login dan memiliki hak akses. Use case pelanggan perlu mencantumkan pra-kondisi sesuai alur, misalnya pelanggan telah memiliki pesanan untuk melihat status pesanan.
