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

Keputusan final saat ini: **data pembayaran tidak dipisahkan ke tabel `pembayarans`**. Use case **Mengelola Pembayaran** tetap valid karena use case menggambarkan proses bisnis kasir, bukan harus sama persis dengan nama tabel database.

Data pembayaran disimpan pada tabel `pesanans` karena ruang lingkup sistem masih menggunakan pola satu pesanan memiliki satu pembayaran akhir. Dengan begitu, data order dan status penyelesaian transaksi dapat dikelola secara sederhana tanpa membuat entitas pembayaran terpisah.

Kolom pembayaran yang berada di tabel `pesanans` antara lain:

- `metode_pembayaran`
- `dibayar`
- `kembalian`
- `referensi_pembayaran`
- `waktu_selesai`

Catatan untuk laporan: jika ditanya mengapa tidak dipisah, alasannya adalah proses pembayaran pada sistem ini bersifat sederhana, tidak mendukung cicilan, multi-payment, refund terpisah, atau riwayat transaksi berganda. Karena itu, penyimpanan atribut pembayaran langsung pada `pesanans` masih cukup proporsional untuk kebutuhan tugas akhir ini.

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
- Pembayaran dijelaskan sebagai proses bisnis kasir, walaupun datanya tersimpan pada tabel `pesanans`.
- Data pembayaran tidak dipisahkan menjadi tabel tersendiri untuk ruang lingkup sistem saat ini.

## 6. Implikasi ke Diagram Lain

### Activity Diagram

Activity diagram sebaiknya tetap mengikuti 15 proses utama yang sudah dibuat. Untuk login, cukup digambarkan sebagai proses internal. Untuk ubah dan batal pesanan, aktivitas dimulai dari aksi pada halaman status pesanan.

#### Catatan Activity 1 - Melakukan Pemesanan Melalui QR Meja

Versi manual terbaru untuk activity 1 menggunakan dua swimlane, yaitu **Pelanggan** dan **Sistem**. Alur yang perlu dipertahankan:

- Pelanggan memulai proses dengan memindai QR Code meja.
- Sistem memvalidasi QR Code meja.
- Decision **Status QR Code meja?** memiliki cabang:
  - **Aktif**: sistem menampilkan halaman pemesanan, lalu pelanggan memilih menu dan addon.
  - **Nonaktif**: sistem menampilkan halaman tidak ditemukan, lalu proses berakhir.
- Pelanggan memilih menu dan addon, mengisi data pesanan, lalu mengirim pesanan.
- Sistem memvalidasi data pesanan.
- Decision **Status data pesanan?** memiliki cabang:
  - **Sesuai**: sistem menghitung total pesanan.
  - **Tidak sesuai**: sistem menampilkan pesan kesalahan, lalu alur kembali ke aktivitas pelanggan untuk memperbaiki data pesanan.
- Setelah total dihitung, sistem memeriksa kapasitas meja.
- Decision **Status kapasitas meja?** memiliki cabang:
  - **Cukup**: sistem menyimpan pesanan dengan status menunggu, menyinkronkan status meja, dan menampilkan halaman status pesanan.
  - **Tidak cukup**: sistem menampilkan pesan tamu melebihi kapasitas, lalu alur kembali ke aktivitas pelanggan untuk memperbaiki jumlah tamu/data pesanan.
- Pelanggan melihat halaman status pesanan, lalu proses berakhir.

Catatan notasi:

- Gunakan initial node pada swimlane Pelanggan sebelum aktivitas memindai QR Code meja.
- Gunakan final node pada cabang QR Code nonaktif dan pada akhir alur sukses setelah pelanggan melihat halaman status pesanan.
- Label decision seperti **Status QR Code meja?**, **Status data pesanan?**, dan **Status kapasitas meja?** sebaiknya ditempatkan dekat diamond decision, bukan sebagai activity/action terpisah.
- Cabang kesalahan data dan kapasitas tidak perlu langsung final karena pelanggan masih dapat memperbaiki input.

### Sequence Diagram

Sequence diagram sebaiknya menggunakan peserta utama:

- aktor,
- halaman,
- sistem,
- database.

Untuk pembayaran, sistem mengambil data pesanan dari database, memvalidasi nominal dan metode pembayaran, lalu memperbarui atribut pembayaran serta status pesanan pada tabel `pesanans`.

### Class Diagram dan ERD

Class diagram dan ERD tetap mengikuti model/tabel utama sistem. **Pembayaran tidak perlu menjadi class atau tabel terpisah** selama satu pesanan hanya memiliki satu pembayaran akhir. Pada ERD dan basis data konseptual, atribut pembayaran cukup ditampilkan sebagai bagian dari entitas `pesanans`.

### Expanded Use Case

Expanded use case internal perlu mencantumkan pra-kondisi bahwa pengguna telah login dan memiliki hak akses. Use case pelanggan perlu mencantumkan pra-kondisi sesuai alur, misalnya pelanggan telah memiliki pesanan untuk melihat status pesanan.
