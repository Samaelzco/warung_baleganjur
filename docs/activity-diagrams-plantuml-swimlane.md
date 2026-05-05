# Activity Diagram UML Warung Baleganjur - Versi Swimlane

Dokumen ini berisi Activity Diagram inti dalam bentuk swimlane.

Catatan:
- Diagram dibuat sederhana agar rapi untuk laporan tugas akhir.
- Swimlane digunakan untuk membedakan aktor dan sistem.
- Cabang dibuat berakhir sendiri jika perlu agar PlantUML tidak membuat merge/shape kosong.
- Detail teknis seperti cache, pagination, dan query internal tidak ditampilkan.

Style umum yang digunakan pada setiap diagram:
- `skinparam defaultFontName Arial`
- `skinparam defaultFontSize 14`
- `skinparam activityFontSize 13`
- `skinparam swimlaneTitleFontSize 16`

## 1. Pemesanan Customer Melalui QR Code

Acuan sequence: **Pemesanan Customer Melalui QR Code**

```plantuml
@startuml
title Activity Pemesanan Customer Melalui QR Code

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|Customer|
start
:Memindai QR Code meja;

|Sistem|
:Memvalidasi token QR meja;

if (Meja valid dan aktif?) then (Tidak)
    :Menampilkan halaman tidak ditemukan;
    stop
else (Ya)
    :Mengambil data menu;
    :Mengambil data addon;
    :Menampilkan daftar menu;
endif

|Customer|
:Memilih menu;
:Memilih addon;
:Mengisi jumlah pesanan;
:Melakukan checkout;

|Sistem|
:Memvalidasi cart;
:Memvalidasi ketersediaan menu;

if (Cart valid?) then (Tidak)
    :Menampilkan pesan kesalahan;
    stop
else (Ya)
    :Memeriksa kapasitas meja;
endif

if (Kapasitas tersedia?) then (Tidak)
    :Mengarahkan ke waiting list;
    stop
else (Ya)
    :Menghitung total pesanan;
    :Menyimpan pesanan status menunggu;
    :Menyimpan detail pesanan;
    :Menyimpan addon pesanan;
    :Menyinkronkan status meja;
    :Mengarahkan ke status pesanan;
    stop
endif

@enduml
```

## 2. Pemantauan, Edit, dan Pembatalan Pesanan oleh Customer

Acuan sequence: **Pemantauan Status Pesanan oleh Customer**

```plantuml
@startuml
title Activity Pemantauan, Edit, dan Pembatalan Pesanan oleh Customer

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|Customer|
start
:Membuka halaman status pesanan;

|Sistem|
:Memvalidasi token;
:Mengambil data pesanan;

if (Token valid?) then (Ya)
    :Menampilkan status terbaru;
else (Tidak)
    :Menampilkan halaman tidak ditemukan;
    stop
endif

|Customer|
:Melihat status pesanan;
:Memilih aksi lanjutan;

|Sistem|
if (Pesanan masih dapat dikelola?) then (Ya)
    :Menampilkan opsi aksi customer;
else (Tidak)
    :Menampilkan status akhir pesanan;
    stop
endif

|Customer|
if (Aksi customer?) then (Edit)
    :Mengubah pesanan;
    :Mengirim perubahan pesanan;
    |Sistem|
    :Memvalidasi perubahan;
    :Menyimpan perubahan pesanan;
    :Mengubah status menjadi menunggu;
    :Menampilkan status terbaru;
    stop
else (Batal)
    |Sistem|
    :Mengubah status pesanan menjadi batal;
    :Menyinkronkan status meja;
    :Mengaktifkan waiting list berikutnya jika tersedia;
    :Menampilkan status pesanan batal;
    stop
else (Pantau)
    |Sistem|
    :Menampilkan status terbaru;
    stop
endif

@enduml
```

## 3. Proses Pesanan pada Kitchen Display

Acuan sequence: **Proses Kitchen Display oleh Koki**

```plantuml
@startuml
title Activity Proses Pesanan pada Kitchen Display

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|Koki|
start
:Membuka Kitchen Display;

|Sistem|
:Memeriksa permission kitchen.access;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Mengambil pesanan aktif;
endif

|Koki|
:Melihat daftar pesanan aktif;
:Memilih pesanan;
:Memilih aksi perubahan status;

|Sistem|
if (Transisi status valid?) then (Tidak)
    :Menolak perubahan status;
    |Koki|
    :Melihat pesan gagal;
    stop
else (Ya)
    :Mengubah status pesanan;
    :Menyimpan chef_id jika diperlukan;
    :Memperbarui daftar kitchen;
    |Koki|
    :Melihat status pesanan terbaru;
    stop
endif

@enduml
```

## 4. Pembayaran Pesanan oleh Kasir

Acuan sequence: **Pembayaran Pesanan oleh Kasir**

```plantuml
@startuml
title Activity Pembayaran Pesanan oleh Kasir

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|Kasir|
start
:Membuka halaman pembayaran;

|Sistem|
:Memeriksa permission pembayaran.access;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Mengambil pesanan siap bayar;
endif

|Kasir|
:Melihat daftar pesanan siap bayar;
:Memilih pesanan;

|Sistem|
:Menampilkan detail pembayaran;

|Kasir|
:Mengisi metode pembayaran;
:Mengisi data pembayaran;
:Mengonfirmasi pembayaran;

|Sistem|
:Memvalidasi pembayaran;

if (Pembayaran valid?) then (Tidak)
    :Menampilkan pesan gagal;
    |Kasir|
    :Memperbaiki data pembayaran;
    stop
else (Ya)
    :Menyimpan pembayaran;
    :Mengubah status pesanan menjadi selesai;
    :Mengaktifkan waiting list berikutnya jika tersedia;
endif

|Kasir|
if (Cetak struk?) then (Ya)
    :Mencetak struk;
    :Melihat pembayaran berhasil;
    stop
else (Tidak)
    :Melihat pembayaran berhasil;
    stop
endif

@enduml
```

## 5. Waiting List atau Booking Meja

Acuan sequence: **Waiting List / Booking Meja**

```plantuml
@startuml
title Activity Waiting List atau Booking Meja

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|Customer|
start
:Membuka halaman waiting list;

|Sistem|
:Mengambil daftar meja aktif;
:Menghitung kapasitas meja;
:Menghitung jumlah booking;
:Menampilkan kondisi meja;

|Customer|
:Memilih meja;
:Memilih menu;
:Memilih addon;
:Mengisi jumlah pesanan;
:Mengisi data customer;
:Melakukan checkout waiting list;

|Sistem|
:Memvalidasi data booking;

if (Data valid?) then (Ya)
    :Menghitung total booking;
    :Membuat status token;
    :Menyimpan pesanan status booking;
    :Menyimpan detail pesanan;
    :Menyimpan addon pesanan;
    |Customer|
    :Melihat status booking;
    stop
else (Tidak)
    :Menampilkan pesan kesalahan;
    |Customer|
    :Memperbaiki data booking;
    stop
endif

@enduml
```

## 6. Aktivasi Waiting List Setelah Meja Tersedia

Acuan sequence: **Aktivasi Waiting List Setelah Meja Tersedia**

```plantuml
@startuml
title Activity Aktivasi Waiting List Setelah Meja Tersedia

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|Kasir/Customer|
start
:Memicu aktivasi waiting list;

|Sistem|
:Mengambil data meja;
:Menghitung kapasitas tersisa;
:Mencari booking paling awal;

if (Booking dapat diaktifkan?) then (Tidak)
    :Tidak mengaktifkan waiting list;
    :Menyinkronkan status meja;
    stop
else (Ya)
    :Mengubah status booking menjadi menunggu;
    :Menyinkronkan status meja;
    :Memperbarui daftar Kitchen Display;
    |Kasir/Customer|
    :Melihat proses aktivasi selesai;
    stop
endif

@enduml
```

## 7. Admin atau Koki Mengelola Menu dan Addon

Acuan sequence: **Admin atau Koki Mengelola Menu dan Addon**

```plantuml
@startuml
title Activity Admin atau Koki Mengelola Menu dan Addon

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|Admin/Koki|
start
:Membuka halaman kelola data;

|Sistem|
:Memeriksa permission kelola data;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Menampilkan daftar data;
endif

|Admin/Koki|
:Memilih aksi kelola data;
:Mengisi form data;

|Sistem|
:Memvalidasi input;

if (Input valid?) then (Tidak)
    :Menampilkan error validasi;
    |Admin/Koki|
    :Memperbaiki input;
    stop
endif

if (Data menu memakai gambar?) then (Ya)
    :Menyimpan gambar menu;
    :Membuat thumbnail gambar;
    :Menyimpan data;
    :Memperbarui data menu customer;
    |Admin/Koki|
    :Kembali ke daftar data;
    stop
else (Tidak)
    :Menyimpan data;
    :Memperbarui data menu customer;
    |Admin/Koki|
    :Kembali ke daftar data;
    stop
endif

@enduml
```

## 8. Admin Mengelola Meja dan QR Code

Acuan sequence: **Admin Mengelola Meja dan QR Code**

```plantuml
@startuml
title Activity Admin Mengelola Meja dan QR Code

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|Admin|
start
:Membuka halaman meja;

|Sistem|
:Memeriksa permission meja;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Menampilkan daftar meja;
endif

|Admin|
:Memilih aksi kelola meja;

|Sistem|
:Menyiapkan form meja;
:Menyiapkan token QR;

|Admin|
:Mengisi data meja;

|Sistem|
:Memvalidasi data meja;

if (Input valid?) then (Tidak)
    :Menampilkan error validasi;
    |Admin|
    :Memperbaiki data meja;
    stop
else (Ya)
    :Menyimpan data meja;
endif

|Admin|
if (Cetak QR?) then (Ya)
    |Sistem|
    :Membuat QR Code;
    :Membuat PDF QR;
    |Admin|
    :Mencetak QR meja;
    :Melihat data meja berhasil disimpan;
    stop
else (Tidak)
    :Melihat data meja berhasil disimpan;
    stop
endif

@enduml
```

## 9. Login dan Pengalihan Berdasarkan Permission

Acuan sequence: **Login dan Redirect Berdasarkan Permission**

```plantuml
@startuml
title Activity Login dan Pengalihan Berdasarkan Permission

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16


|User|
start
:Membuka halaman login;
:Mengisi email;
:Mengisi password;
:Mengirim form login;

|Sistem|
:Memvalidasi kredensial;

if (Login berhasil?) then (Tidak)
    :Menampilkan error login;
    |User|
    :Mengisi ulang form login;
    stop
else (Ya)
    :Mengambil role;
    :Mengambil permission;
    :Menentukan halaman tujuan;
endif

|User|
:Mengakses halaman sistem;

|Sistem|
:Memeriksa permission route;

if (Permission sesuai?) then (Ya)
    :Menampilkan halaman;
    |User|
    :Menggunakan fitur sesuai hak akses;
    stop
else (Tidak)
    :Menampilkan 403 Forbidden;
    stop
endif

@enduml
```
