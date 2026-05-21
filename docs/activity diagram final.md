# Activity Diagram UML Berdasarkan Use Case - Versi Swimlane

Dokumen ini berisi 15 activity diagram PlantUML berdasarkan 15 use case utama sistem.

Catatan:
- Diagram menggunakan swimlane untuk memisahkan aktor dan sistem.
- Alur dibuat ringkas agar mudah dimasukkan ke laporan tugas akhir.
- Detail teknis seperti query, cache, token internal, dan struktur tabel tidak ditampilkan.

## 1. Melakukan Pemesanan Melalui QR Meja

```plantuml
@startuml
title Activity Melakukan Pemesanan Melalui QR Meja

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Pelanggan|
start
:Memindai QR Code meja;

|Sistem|
:Memvalidasi QR Code meja;

if (Status QR Code meja?) then (Valid)
    :Menampilkan halaman pemesanan;
else (Tidak valid)
    :Menampilkan halaman tidak ditemukan;
    stop
endif

|Pelanggan|
:Memilih menu dan addon;
:Mengisi data pesanan;
:Mengirim pesanan;

|Sistem|
:Memvalidasi data pesanan;

if (Status data pesanan?) then (Sesuai)
else (Tidak sesuai)
    :Menampilkan pesan kesalahan;
    stop
endif

:Menghitung total pesanan;
:Memeriksa kapasitas meja;

if (Status kapasitas meja?) then (Cukup)
    :Menyimpan pesanan status menunggu;
    :Menyinkronkan status meja;
    :Menampilkan halaman status pesanan;

    |Pelanggan|
    :Melihat halaman status pesanan;
    stop
else (Tidak cukup)
    :Menampilkan pesan jumlah tamu melebihi kapasitas;
    stop
endif

@enduml
```

## 2. Melihat Status Pesanan

```plantuml
@startuml
title Activity Melihat Status Pesanan

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Pelanggan|
start
:Membuka halaman status pesanan;

|Sistem|
:Memvalidasi status token pesanan;

if (Status token pesanan?) then (Valid)
    :Mengambil data pesanan;
    :Menampilkan detail pesanan;
    :Menampilkan status awal pesanan;
else (Tidak valid)
    :Menampilkan halaman tidak ditemukan;
    stop
endif

|Pelanggan|
:Melihat status pesanan;

|Sistem|
:Memuat pembaruan status pesanan;

if (Status pesanan?) then (Masih aktif)
    :Menampilkan status terbaru pesanan;
    |Pelanggan|
    :Memantau perubahan status pesanan;
else (Tidak aktif)
    if (Status akhir pesanan?) then (Selesai)
        :Menampilkan status pembayaran selesai;
        :Redirect ke halaman pemesanan;
    else (Batal)
        :Menampilkan status pesanan batal;
        :Redirect ke halaman pemesanan;
    endif
endif

stop

@enduml
```

## 3. Mengubah Pesanan

```plantuml
@startuml
title Activity Mengubah Pesanan

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Pelanggan|
start
:Memilih aksi ubah pesanan;

|Sistem|
:Memvalidasi akses dan status pesanan;

if (Pesanan dapat diubah?) then (Ya)
    :Menampilkan halaman ubah pesanan;
else (Tidak)
    :Menampilkan pesan pesanan tidak dapat diubah;
    stop
endif

|Pelanggan|
:Mengubah item pesanan;
:Mengirim perubahan;

|Sistem|
:Memvalidasi perubahan;

if (Perubahan valid?) then (Ya)
    :Menghitung ulang total;
    :Menyimpan perubahan pesanan;
    :Menampilkan status terbaru;
    stop
else (Tidak)
    :Menampilkan error validasi;
    stop
endif

@enduml
```

## 4. Membatalkan Pesanan

```plantuml
@startuml
title Activity Membatalkan Pesanan

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Pelanggan|
start
:Memilih aksi batalkan pesanan;

|Sistem|
:Memvalidasi akses dan status pesanan;

if (Pesanan dapat dibatalkan?) then (Tidak)
    :Menampilkan pesan pesanan tidak dapat dibatalkan;
    stop
else (Ya)
    :Mengubah status pesanan menjadi batal;
    :Menyinkronkan status meja;
    :Mengaktifkan waiting list jika tersedia;
    |Pelanggan|
    :Melihat status pesanan batal;
    stop
endif

@enduml
```

## 5. Membuat Waiting List

```plantuml
@startuml
title Activity Membuat Waiting List

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Pelanggan|
start
:Membuka halaman waiting list;

|Sistem|
:Mengambil daftar meja aktif;
:Menghitung kapasitas dan jumlah waiting list;
:Menampilkan kondisi meja;

|Pelanggan|
:Memilih meja;
:Memilih menu dan addon;
:Mengisi data waiting list;
:Mengirim waiting list;

|Sistem|
:Memvalidasi waiting list;

if (Waiting list valid?) then (Tidak)
    :Menampilkan pesan kesalahan;
    stop
else (Ya)
    :Menyimpan pesanan status waiting list;
    |Pelanggan|
    :Melihat status waiting list;
    stop
endif

@enduml
```

## 6. Melakukan Login

```plantuml
@startuml
title Activity Melakukan Login

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|User Internal|
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
    |User Internal|
    :Masuk ke halaman sesuai akses;
    stop
endif

@enduml
```

## 7. Melihat Dashboard

```plantuml
@startuml
title Activity Melihat Dashboard

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Admin|
start
:Membuka dashboard;

|Sistem|
:Memeriksa permission dashboard;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Mengambil ringkasan penjualan;
    :Mengambil ringkasan pesanan;
    :Menampilkan grafik dan laporan;
    |Admin|
    :Melihat informasi dashboard;
    stop
endif

@enduml
```

## 8. Mengelola Pesanan

```plantuml
@startuml
title Activity Mengelola Pesanan

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Admin/Kasir|
start
:Membuka halaman pesanan;

|Sistem|
:Memeriksa permission pesanan;

if (Akses diizinkan?) then (Ya)
    :Menampilkan daftar pesanan;
else (Tidak)
    :Menampilkan 403 Forbidden;
    stop
endif

|Admin/Kasir|
:Memilih aksi kelola pesanan;
:Mengisi atau mengubah data pesanan;

|Sistem|
:Memvalidasi data pesanan;

if (Data valid?) then (Ya)
    :Menyimpan perubahan pesanan;
    :Menampilkan pesan berhasil;
    stop
else (Tidak)
    :Menampilkan error validasi;
    stop
endif

@enduml
```

## 9. Mengelola Kitchen Display

```plantuml
@startuml
title Activity Mengelola Kitchen Display

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Koki|
start
:Membuka Kitchen Display;

|Sistem|
:Memeriksa permission kitchen;

if (Akses diizinkan?) then (Ya)
    :Menampilkan pesanan aktif;
else (Tidak)
    :Menampilkan 403 Forbidden;
    stop
endif

|Koki|
:Memilih pesanan;
:Memilih aksi status;

|Sistem|
:Memvalidasi transisi status;

if (Transisi valid?) then (Tidak)
    :Menampilkan pesan gagal;
    stop
else (Ya)
    :Mengubah status pesanan;
    :Memperbarui Kitchen Display;
    |Koki|
    :Melihat status terbaru;
    stop
endif

@enduml
```

## 10. Mengelola Pembayaran

```plantuml
@startuml
title Activity Mengelola Pembayaran

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Kasir|
start
:Membuka halaman pembayaran;

|Sistem|
:Memeriksa permission pembayaran;

if (Akses diizinkan?) then (Ya)
    :Menampilkan pesanan siap bayar;
else (Tidak)
    :Menampilkan 403 Forbidden;
    stop
endif

|Kasir|
:Memilih pesanan;
:Mengisi metode dan data pembayaran;
:Mengonfirmasi pembayaran;

|Sistem|
:Memvalidasi pembayaran;

if (Pembayaran valid?) then (Tidak)
    :Menampilkan pesan gagal;
    stop
else (Ya)
    :Menyimpan pembayaran;
    :Mengubah status pesanan menjadi selesai;
    :Menyinkronkan status meja;
    :Mengaktifkan waiting list jika tersedia;
    |Kasir|
    :Mencetak struk jika diperlukan;
    stop
endif

@enduml
```

## 11. Mengelola Waiting List

```plantuml
@startuml
title Activity Mengelola Waiting List

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Admin/Kasir|
start
:Membuka halaman kelola waiting list;

|Sistem|
:Memeriksa permission waiting list;

if (Akses diizinkan?) then (Ya)
    :Menampilkan daftar waiting list;
else (Tidak)
    :Menampilkan 403 Forbidden;
    stop
endif

|Admin/Kasir|
:Memilih data waiting list;

if (Aksi pengelola?) then (Batalkan)
    |Sistem|
    :Mengubah status waiting list menjadi batal;
    :Menyinkronkan status meja;
    stop

else (Aktifkan)
    |Sistem|
    :Memeriksa kapasitas meja;

    if (Waiting list dapat diaktifkan?) then (Ya)
        :Mengubah status waiting list menjadi menunggu;
        :Menyinkronkan status meja;
        :Memperbarui Kitchen Display;
        stop
    else (Tidak)
        :Menampilkan pesan tidak dapat diaktifkan;
        stop
    endif

endif

@enduml
```

## 12. Mengelola Menu dan Addon

```plantuml
@startuml
title Activity Mengelola Menu dan Addon

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Admin/Koki|
start
:Membuka halaman menu atau addon;

|Sistem|
:Memeriksa permission menu atau addon;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Menampilkan daftar data;
endif

|Admin/Koki|
:Memilih tambah, edit, hapus, atau ubah status;
:Mengisi form data;

|Sistem|
:Memvalidasi input;

if (Input valid?) then (Tidak)
    :Menampilkan error validasi;
    stop
else (Ya)
    :Menyimpan gambar jika ada;
    :Menyimpan data menu atau addon;
    :Memperbarui data menu pelanggan;
    stop
endif

@enduml

```

## 13. Mengelola Data Referensi

```plantuml
@startuml
title Activity Mengelola Data Referensi

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Admin|
start
:Membuka halaman kategori, pajak, atau diskon;

|Sistem|
:Memeriksa permission data referensi;

if (Akses diizinkan?) then (Ya)
    :Menampilkan daftar data referensi;
else (Tidak)
    :Menampilkan 403 Forbidden;
    stop
endif

|Admin|
:Memilih tambah, edit, hapus, atau ubah status;
:Mengisi form data;

|Sistem|
:Memvalidasi data referensi;

if (Data valid?) then (Ya)
    :Menyimpan data referensi;
    :Menampilkan pesan berhasil;
    stop
else (Tidak)
    :Menampilkan error validasi;
    stop
endif

@enduml
```

## 14. Mengelola Meja dan QR Code

```plantuml
@startuml
title Activity Mengelola Meja dan QR Code

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
:Memilih tambah, edit, ubah status, atau cetak QR;
:Mengisi data meja;

|Sistem|
:Menyiapkan token QR;
:Memvalidasi data meja;

if (Data valid?) then (Tidak)
    :Menampilkan error validasi;
    stop
else (Ya)
    :Menyimpan data meja;
    :Menyiapkan QR Code meja;
    :Mencetak PDF QR jika diminta;
    stop
endif

@enduml

```

## 15. Mengelola User dan Hak Akses

```plantuml
@startuml
title Activity Mengelola User dan Hak Akses

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam activityFontSize 13
skinparam swimlaneTitleFontSize 16

|Admin|
start
:Membuka halaman user atau role;

|Sistem|
:Memeriksa permission user dan role;

if (Akses diizinkan?) then (Ya)
    :Menampilkan daftar user, role, dan permission;
else (Tidak)
    :Menampilkan 403 Forbidden;
    stop
endif

|Admin|
:Memilih tambah, edit, nonaktifkan user, atau ubah role;
:Mengisi data user atau hak akses;

|Sistem|
:Memvalidasi data dan permission;

if (Data valid?) then (Ya)
    :Menyimpan user, role, dan hak akses;
    :Menampilkan pesan berhasil;
    stop
else (Tidak)
    :Menampilkan error validasi;
    stop
endif

@enduml
```
