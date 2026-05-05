# Activity Diagram UML Warung Baleganjur - Versi Swimlane Ringkas

Dokumen ini berisi 11 Activity Diagram inti dalam bentuk PlantUML swimlane untuk laporan tugas akhir.

Tujuan versi ini:
- Memisahkan aktivitas berdasarkan aktor agar alur mudah dipahami.
- Hanya menampilkan proses penting yang mewakili project.
- Detail teknis seperti cache, query database, token internal, dan relasi pivot diringkas.
- Flow yang terlalu padat dipecah menjadi diagram terpisah.

Style umum yang digunakan:
- `skinparam defaultFontName Arial`
- `skinparam defaultFontSize 14`
- `skinparam activityFontSize 13`
- `skinparam swimlaneTitleFontSize 16`

## 1. Login dan Pengalihan Berdasarkan Permission

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
:Mengisi email dan password;
:Mengirim form login;

|Sistem|
:Memvalidasi kredensial;

if (Login berhasil?) then (Tidak)
    :Menampilkan pesan login gagal;
    stop
else (Ya)
    :Mengambil role dan permission;
    :Menentukan halaman awal;
endif

|User|
:Mengakses halaman sistem;

|Sistem|
:Memeriksa permission route;

if (Permission sesuai?) then (Ya)
    :Menampilkan halaman sesuai akses;
    |User|
    :Menggunakan fitur sistem;
    stop
else (Tidak)
    :Menampilkan 403 Forbidden;
    stop
endif

@enduml
```

## 2. Customer Scan QR dan Melihat Menu

```plantuml
@startuml
title Activity Customer Scan QR dan Melihat Menu

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Customer|
start
:Memindai QR Code meja;

|Sistem|
:Menerima token QR;
:Memvalidasi meja;

if (Meja valid dan aktif?) then (Tidak)
    :Menampilkan halaman tidak ditemukan;
    stop
else (Ya)
    :Mengambil kategori menu;
    :Mengambil menu dan addon;
    :Menampilkan halaman menu;
endif

|Customer|
:Melihat daftar menu;
:Memilih menu dan addon;
:Mengatur jumlah pesanan;
:Membuka halaman checkout;
stop

@enduml
```

## 3. Checkout dan Penyimpanan Pesanan Customer

```plantuml
@startuml
title Activity Checkout dan Penyimpanan Pesanan Customer

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Customer|
start
:Melihat ringkasan checkout;
:Mengisi nama dan jumlah orang;
:Mengirim pesanan;

|Sistem|
:Memvalidasi cart;
:Memvalidasi ketersediaan menu;

if (Pesanan valid?) then (Tidak)
    :Menampilkan pesan kesalahan;
    stop
else (Ya)
    :Memeriksa kapasitas meja;
endif

if (Kapasitas tersedia?) then (Tidak)
    :Mengarahkan ke waiting list;
    stop
else (Ya)
    :Menghitung subtotal, diskon, pajak, dan total;
    :Menyimpan pesanan status menunggu;
    :Menyimpan detail pesanan;
    :Menyinkronkan status meja;
endif

|Customer|
:Diarahkan ke halaman status pesanan;
stop

@enduml
```

## 4. Pemantauan Status Pesanan oleh Customer

```plantuml
@startuml
title Activity Pemantauan Status Pesanan oleh Customer

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Customer|
start
:Membuka halaman status pesanan;

|Sistem|
:Memvalidasi akses status;
:Mengambil data pesanan;

if (Pesanan ditemukan?) then (Tidak)
    :Menampilkan halaman tidak ditemukan;
    stop
else (Ya)
    :Menampilkan status pesanan;
endif

|Customer|
:Melihat status pesanan;

|Sistem|
:Memuat pembaruan status;

if (Pesanan masih aktif?) then (Ya)
    :Menampilkan status terbaru;
    |Customer|
    :Memantau proses pesanan;
    stop
elseif (Sudah dibayar)
    :Mengarahkan customer kembali ke halaman pemesanan;
    stop
else (Batal / tidak aktif)
    :Menampilkan status akhir pesanan;
    stop
endif

@enduml
```

## 5. Edit atau Pembatalan Pesanan oleh Customer

```plantuml
@startuml
title Activity Edit atau Pembatalan Pesanan oleh Customer

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Customer|
start
:Membuka halaman status;
:Memilih aksi pesanan;

|Sistem|
:Memvalidasi akses dan status pesanan;

if (Pesanan dapat dikelola?) then (Tidak)
    :Menampilkan pesan pesanan tidak dapat diubah;
    stop
else (Ya)
endif

|Customer|
if (Aksi customer?) then (Edit)
    :Membuka halaman edit pesanan;
    :Mengubah item pesanan;
    :Mengirim perubahan;

    |Sistem|
    :Memvalidasi perubahan;

    if (Perubahan valid?) then (Ya)
        :Menyimpan perubahan pesanan;
        :Mengubah status menjadi menunggu;
        :Menampilkan status terbaru;
        stop
    else (Tidak)
        :Menampilkan pesan kesalahan;
        stop
    endif

else (Batal)
    |Sistem|
    :Mengubah status pesanan menjadi batal;
    :Menyinkronkan status meja;
    :Mengaktifkan waiting list jika tersedia;
    :Menampilkan status pesanan batal;
    stop
endif

@enduml
```

## 6. Proses Pesanan pada Kitchen Display

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
:Memeriksa permission kitchen;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Mengambil pesanan aktif;
    :Menampilkan daftar pesanan;
endif

|Koki|
:Memilih pesanan;
:Memilih perubahan status;

|Sistem|
:Memvalidasi transisi status;

if (Transisi valid?) then (Tidak)
    :Menampilkan pesan gagal;
    stop
else (Ya)
endif

if (Aksi koki?) then (Mulai proses)
    :Mengubah status menjadi diproses;
elseif (Tandai siap)
    :Mengubah status menjadi siap;
else (Kembalikan)
    :Mengubah status menjadi diproses;
endif

:Memperbarui daftar kitchen;

|Koki|
:Melihat status pesanan terbaru;
stop

@enduml
```

## 7. Pembayaran Pesanan oleh Kasir

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
:Memeriksa permission pembayaran;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Mengambil pesanan siap bayar;
    :Menampilkan daftar pesanan;
endif

|Kasir|
:Memilih pesanan;

|Sistem|
:Menampilkan detail pembayaran;

|Kasir|
:Memilih metode pembayaran;
:Mengisi data pembayaran;
:Mengonfirmasi pembayaran;

|Sistem|
:Memvalidasi pembayaran;

if (Pembayaran valid?) then (Tidak)
    :Menampilkan pesan gagal;
    stop
else (Ya)
    :Menyimpan data pembayaran;
    :Mengubah status pesanan menjadi selesai;
    :Menyinkronkan status meja;
    :Mengaktifkan waiting list jika tersedia;
endif

|Kasir|
if (Cetak struk?) then (Ya)
    :Mencetak struk pembayaran;
    :Melihat pembayaran berhasil;
    stop
else (Tidak)
    :Melihat pembayaran berhasil;
    stop
endif

@enduml
```

## 8. Customer Membuat Waiting List atau Booking Meja

```plantuml
@startuml
title Activity Customer Membuat Waiting List atau Booking Meja

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Customer|
start
:Membuka halaman waiting list;

|Sistem|
:Mengambil daftar meja aktif;
:Menghitung kapasitas dan jumlah booking;
:Menampilkan kondisi meja;

|Customer|
:Memilih meja;
:Memilih menu dan addon;
:Mengisi data booking;
:Mengirim booking;

|Sistem|
:Memvalidasi data booking;

if (Booking valid?) then (Tidak)
    :Menampilkan pesan kesalahan;
    stop
else (Ya)
    :Menghitung total booking;
    :Menyimpan pesanan status booking;
    :Menyimpan detail pesanan;
endif

|Customer|
:Melihat status booking;
stop

@enduml
```

## 9. Admin atau Kasir Mengelola dan Mengaktifkan Waiting List

```plantuml
@startuml
title Activity Admin atau Kasir Mengelola dan Mengaktifkan Waiting List

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Admin/Kasir|
start
:Membuka halaman kelola waiting list;

|Sistem|
:Memeriksa permission waiting list;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Menampilkan daftar booking;
endif

|Admin/Kasir|
:Memilih booking;

if (Aksi pengelola?) then (Aktifkan)
    |Sistem|
    :Memeriksa kapasitas meja;
    :Memeriksa urutan waiting list;

    if (Booking dapat diaktifkan?) then (Ya)
        :Mengubah status booking menjadi menunggu;
        :Menyinkronkan status meja;
        :Memperbarui Kitchen Display;
        |Admin/Kasir|
        :Melihat booking berhasil diaktifkan;
        stop
    else (Tidak)
        :Menampilkan pesan tidak dapat diaktifkan;
        stop
    endif

else (Batalkan)
    |Sistem|
    :Mengubah status booking menjadi batal;
    :Menyinkronkan status meja;
    |Admin/Kasir|
    :Melihat booking berhasil dibatalkan;
    stop
endif

@enduml
```

## 10. Admin atau Koki Mengelola Menu dan Addon

```plantuml
@startuml
title Activity Admin atau Koki Mengelola Menu dan Addon

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Admin/Koki|
start
:Membuka halaman menu atau addon;

|Sistem|
:Memeriksa permission kelola menu;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Menampilkan daftar data;
endif

|Admin/Koki|
:Memilih tambah atau edit data;
:Mengisi form data;

|Sistem|
:Memvalidasi input;

if (Input valid?) then (Tidak)
    :Menampilkan error validasi;
    stop
else (Ya)
endif

if (Data menu memakai gambar?) then (Ya)
    :Menyimpan gambar menu;
    :Membuat thumbnail gambar;
endif

:Menyimpan data menu atau addon;
:Memperbarui data menu customer;

|Admin/Koki|
:Melihat pesan berhasil;
:Kembali ke daftar data;
stop

@enduml
```

## 11. Admin Mengelola Meja dan QR Code

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
:Memilih tambah atau edit meja;

|Sistem|
:Menyiapkan form meja;
:Menyiapkan token QR;

|Admin|
:Mengisi data meja;

if (Regenerate token QR?) then (Ya)
    |Sistem|
    :Membuat token QR baru;
    |Admin|
    :Melihat token QR baru;
else (Tidak)
endif

|Sistem|
:Memvalidasi data meja;

if (Data valid?) then (Tidak)
    :Menampilkan error validasi;
    stop
else (Ya)
    :Menyimpan data meja;
endif

|Admin|
if (Cetak QR?) then (Ya)
    |Sistem|
    :Membuat QR Code;
    :Membuat PDF QR meja;
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
