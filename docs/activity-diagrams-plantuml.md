# Activity Diagram UML Warung Baleganjur

Dokumen ini berisi Activity Diagram inti untuk Sistem Informasi Pemesanan Warung Baleganjur berbasis QR Code dan Kitchen Display.

Acuan utama:
- Sequence Diagram pada `docs/sequence-diagrams-plantuml.md`.
- Struktur route, model, migration, middleware, Livewire/Volt component, dan service pada project.

Catatan:
- Diagram dibuat ringkas agar mudah dimasukkan ke laporan tugas akhir.
- Detail teknis seperti cache, pagination, dan rendering UI diringkas.
- Status pesanan mengikuti kode project: `booking`, `menunggu`, `sedang_diubah`, `diproses`, `siap`, `selesai`, dan `batal`.
- Status meja mengikuti kode project: `kosong`, `terisi`, `reservasi`, dan `nonaktif`.

## 1. Pemesanan Customer Melalui QR Code

Acuan sequence: **Pemesanan Customer Melalui QR Code**

```plantuml
@startuml
title Activity Pemesanan Customer Melalui QR Code

|Customer|
start
:Memindai QR Code meja;

|Sistem|
:Menerima token QR;
:Mencari meja berdasarkan token;

if (Meja valid dan aktif?) then (Tidak)
    :Menampilkan halaman tidak ditemukan;
    |Customer|
    :Menerima pesan gagal akses;
    stop
else (Ya)
    :Mengambil kategori, menu, dan addon;
    |Customer|
    :Melihat daftar menu;
    :Memilih menu, addon, jumlah, dan data pesanan;
    :Melakukan checkout;
endif

|Sistem|
:Memvalidasi cart;
:Memvalidasi menu dan addon tersedia;
:Menghitung subtotal, diskon, pajak, dan total;
:Memeriksa kapasitas meja;

if (Cart/menu valid?) then (Tidak)
    :Menolak pesanan;
    |Customer|
    :Melihat pesan kesalahan;
    stop
else (Ya)
endif

if (Kapasitas meja tersedia?) then (Tidak)
    :Mengembalikan arahan ke waiting list;
    |Customer|
    :Dialihkan ke waiting list;
    stop
else (Ya)
    :Membuat status token pesanan;
    :Menyimpan pesanan status menunggu;
    :Menyimpan detail pesanan dan addon;
    :Menyinkronkan status meja;
    :Mengarahkan ke halaman status pesanan;
    |Customer|
    :Melihat status pesanan;
    stop
endif

@enduml
```

## 2. Pemantauan, Edit, dan Pembatalan Pesanan oleh Customer

Acuan sequence: **Pemantauan Status Pesanan oleh Customer**

```plantuml
@startuml
title Activity Pemantauan, Edit, dan Pembatalan Pesanan oleh Customer

|Customer|
start
:Membuka halaman status pesanan;

|Sistem|
:Menerima pesanan_id dan status token;
:Memvalidasi status token;

if (Token valid?) then (Tidak)
    :Menampilkan halaman tidak ditemukan;
    |Customer|
    :Tidak dapat melihat pesanan;
    stop
else (Ya)
    :Mengambil data pesanan dan detail;
    :Memuat status terbaru;
endif

if (Pesanan sudah dibayar?) then (Ya)
    :Mengirim status paid dan redirect;
    |Customer|
    :Kembali ke halaman pemesanan atau waiting list;
    stop
else (Tidak)
endif

if (Pesanan masih aktif?) then (Tidak)
    |Customer|
    :Selesai memantau pesanan;
    stop
else (Ya)
    |Customer|
    :Melihat status pesanan;
endif

if (Aksi customer?) then (Edit pesanan)
    |Sistem|
    if (Status menunggu atau sedang_diubah?) then (Ya)
        |Customer|
        :Mengubah pesanan;
        :Mengirim perubahan pesanan;
        |Sistem|
        :Memvalidasi dan menyimpan perubahan;
        if (Perubahan valid?) then (Ya)
            :Mengubah status menjadi menunggu;
            |Customer|
            :Kembali melihat status pesanan;
            stop
        else (Tidak)
            :Menampilkan pesan kesalahan;
            |Customer|
            :Memperbaiki perubahan pesanan;
            stop
        endif
    else (Tidak)
        |Sistem|
        :Menolak perubahan pesanan;
        |Customer|
        :Melihat pesan pesanan tidak dapat diubah;
        stop
    endif

elseif (Batalkan pesanan)
    |Sistem|
    if (Status menunggu atau sedang_diubah?) then (Ya)
        :Mengubah status pesanan menjadi batal;
        :Menyinkronkan status meja;
        :Mengaktifkan waiting list berikutnya jika tersedia;
        |Customer|
        :Melihat status pesanan batal;
        stop
    else (Tidak)
        |Sistem|
        :Menolak pembatalan pesanan;
        |Customer|
        :Melihat pesan pesanan tidak dapat dibatalkan;
        stop
    endif

else (Pantau saja)
    |Customer|
    :Melihat status terbaru;
    :Selesai memantau pesanan;
    stop
endif

@enduml
```

## 3. Proses Pesanan pada Kitchen Display

Acuan sequence: **Proses Kitchen Display oleh Koki**

```plantuml
@startuml
title Activity Proses Pesanan pada Kitchen Display

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
    :Menampilkan pesanan status menunggu, sedang_diubah, diproses, dan siap;
endif

|Koki|
:Memilih pesanan;

if (Aksi yang dipilih?) then (Mulai proses)
    |Sistem|
    if (Status pesanan menunggu?) then (Ya)
        :Mengubah status menjadi diproses;
        :Menyimpan chef_id jika belum ada;
        :Memperbarui daftar kitchen;
    else (Tidak)
        :Menolak perubahan status;
    endif
elseif (Tandai siap)
    |Sistem|
    if (Status pesanan diproses?) then (Ya)
        :Mengubah status menjadi siap;
        :Memperbarui daftar kitchen;
    else (Tidak)
        :Menolak perubahan status;
    endif
else (Kembalikan ke proses)
    |Sistem|
    if (Status pesanan siap?) then (Ya)
        :Mengubah status menjadi diproses;
        :Memperbarui daftar kitchen;
    else (Tidak)
        :Menolak perubahan status;
    endif
endif

|Koki|
:Melihat status pesanan terbaru;
stop

@enduml
```

## 4. Pembayaran Pesanan oleh Kasir

Acuan sequence: **Pembayaran Pesanan oleh Kasir**

```plantuml
@startuml
title Activity Pembayaran Pesanan oleh Kasir

|Kasir|
start
:Membuka halaman pembayaran;

|Sistem|
:Memeriksa permission pembayaran.access;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Mengambil pesanan status siap dan belum dibayar;
    |Kasir|
    :Melihat daftar pesanan siap bayar;
endif

|Kasir|
:Memilih pesanan;

|Sistem|
:Mengambil detail pesanan;
:Menampilkan modal pembayaran;

|Kasir|
:Memilih metode pembayaran;
:Mengisi nominal atau referensi pembayaran;
:Mengonfirmasi pembayaran;

|Sistem|
:Memvalidasi metode pembayaran;
:Memeriksa ulang status pesanan;

if (Pesanan masih siap dan belum dibayar?) then (Tidak)
    :Menampilkan pesan gagal;
    stop
else (Ya)
endif

if (Metode tunai?) then (Ya)
    if (Nominal cukup?) then (Tidak)
        :Menampilkan pesan nominal kurang;
        stop
    else (Ya)
        :Menghitung kembalian;
    endif
else (Transfer/QRIS)
    :Mengisi nominal bayar sebesar total;
    :Mengatur kembalian 0;
endif

:Mengubah status pesanan menjadi selesai;
:Menyimpan metode pembayaran, kasir_id, dibayar, kembalian, dan waktu_selesai;
:Mengaktifkan waiting list berikutnya jika kapasitas tersedia;

if (Cetak struk dipilih?) then (Ya)
    :Membuka halaman struk;
    |Kasir|
    :Mencetak struk;
else (Tidak)
endif

|Kasir|
:Melihat pembayaran berhasil;
stop

@enduml
```

## 5. Waiting List atau Booking Meja

Acuan sequence: **Waiting List / Booking Meja**

```plantuml
@startuml
title Activity Waiting List atau Booking Meja

|Customer|
start
:Membuka halaman waiting list;

|Sistem|
:Mengambil daftar meja aktif;
:Menghitung kursi terpakai dan jumlah booking;
:Menampilkan kondisi meja;

|Customer|
:Memilih meja;
:Memilih menu, addon, jumlah, dan data customer;
:Melakukan checkout waiting list;

|Sistem|
:Memvalidasi data customer dan cart;
:Memvalidasi menu dan addon tersedia;

if (Data valid?) then (Tidak)
    :Menampilkan pesan kesalahan;
    |Customer|
    :Memperbaiki data booking;
    stop
else (Ya)
endif

:Menghitung subtotal, diskon, pajak, dan total;
:Membuat status token pesanan;
:Menyimpan pesanan status booking;
:Menyimpan detail pesanan dan addon;
:Mengarahkan ke halaman status pesanan;

|Customer|
:Melihat status booking;
stop

@enduml
```

## 6. Aktivasi Waiting List Setelah Meja Tersedia

Acuan sequence: **Aktivasi Waiting List Setelah Meja Tersedia**

```plantuml
@startuml
title Activity Aktivasi Waiting List Setelah Meja Tersedia

start

if (Pemicu aktivasi?) then (Pembayaran selesai)
    |Kasir|
    :Menyelesaikan pembayaran pesanan;
elseif (Pesanan dibatalkan)
    |Customer|
    :Membatalkan pesanan aktif;
else (Aktivasi manual)
    |Kasir|
    :Memilih booking untuk diaktifkan;
endif

|Sistem|
:Mengambil data meja;
:Menghitung kursi terpakai;
:Menghitung kapasitas tersisa;

if (Kapasitas tersedia?) then (Tidak)
    :Tidak mengaktifkan waiting list;
    stop
else (Ya)
endif

:Mencari pesanan booking paling awal;

if (Ada booking?) then (Tidak)
    :Menyinkronkan status meja;
    stop
else (Ya)
endif

if (Jumlah orang muat?) then (Tidak)
    :Menunda aktivasi booking;
    :Menyinkronkan status meja;
    stop
else (Ya)
    :Mengubah status booking menjadi menunggu;
    :Menyinkronkan status meja;
    :Memperbarui daftar Kitchen Display;
endif

|Kasir|
:Melihat waiting list berhasil diaktifkan;
stop

@enduml
```

## 7. Admin atau Koki Mengelola Menu dan Addon

Acuan sequence: **Admin atau Koki Mengelola Menu dan Addon**

```plantuml
@startuml
title Activity Admin atau Koki Mengelola Menu dan Addon

|Admin/Koki|
start
:Membuka halaman menu atau addon;

|Sistem|
:Memeriksa permission menu/addon;

if (Akses diizinkan?) then (Tidak)
    :Menampilkan 403 Forbidden;
    stop
else (Ya)
    :Menampilkan daftar data;
endif

|Admin/Koki|
:Memilih tambah atau edit data;

|Sistem|
if (Kelola menu?) then (Ya)
    :Mengambil kategori aktif;
    :Mengambil addon tersedia;
    |Admin/Koki|
    :Mengisi data menu, status, gambar, dan relasi addon;
    |Sistem|
    :Memvalidasi nama, kategori, harga, status, gambar, dan addon;

    if (Input valid?) then (Tidak)
        :Menampilkan error validasi;
        stop
    else (Ya)
    endif

    if (Ada gambar menu?) then (Ya)
        :Menyimpan gambar menu;
        :Membuat thumbnail gambar;
    else (Tidak)
    endif

    :Menyimpan data menu;
    :Menyimpan relasi addon_menu;
else (Kelola addon)
    |Admin/Koki|
    :Mengisi data addon dan status;
    |Sistem|
    :Memvalidasi nama addon, harga, dan status;

    if (Input valid?) then (Tidak)
        :Menampilkan error validasi;
        stop
    else (Ya)
        :Menyimpan data addon;
    endif
endif

:Memperbarui versi data menu customer;
:Menampilkan pesan berhasil;

|Admin/Koki|
:Kembali ke daftar data;
stop

@enduml
```

## 8. Admin Mengelola Meja dan QR Code

Acuan sequence: **Admin Mengelola Meja dan QR Code**

```plantuml
@startuml
title Activity Admin Mengelola Meja dan QR Code

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
if (Tambah meja?) then (Ya)
    :Membuat token QR unik;
else (Edit meja)
    :Mengambil data meja;
endif

|Admin|
:Mengisi nomor meja, kapasitas, status, dan token QR;

if (Admin meminta regenerate token?) then (Ya)
    |Sistem|
    :Membuat token QR baru yang unik;
    |Admin|
    :Melihat token QR baru;
else (Tidak)
endif

|Sistem|
:Memvalidasi nomor meja, token QR, status, dan kapasitas;

if (Input valid?) then (Tidak)
    :Menampilkan error validasi;
    stop
else (Ya)
    :Menyimpan data meja;
endif

|Admin|
if (Cetak QR?) then (Ya)
    |Sistem|
    :Mengambil data meja;
    :Membuat QR Code;
    :Membuat PDF QR meja;
    |Admin|
    :Mencetak QR meja;
else (Tidak)
endif

:Melihat data meja berhasil disimpan;
stop

@enduml
```

## 9. Login dan Pengalihan Berdasarkan Permission

Acuan sequence: **Login dan Redirect Berdasarkan Permission**

```plantuml
@startuml
title Activity Login dan Pengalihan Berdasarkan Permission

|User|
start
:Membuka halaman login;
:Mengisi email dan password;
:Mengirim form login;

|Sistem|
:Memvalidasi kredensial;
:Mengambil data user, role, dan permission;

if (Login berhasil?) then (Tidak)
    :Menampilkan error login;
    |User|
    :Mengisi ulang form login;
    stop
else (Ya)
endif

if (Ada intended URL dan user boleh akses?) then (Ya)
    :Redirect ke intended URL;
else (Tidak)
    if (Memiliki dashboard.access?) then (Ya)
        :Redirect ke dashboard;
    elseif (Memiliki pembayaran.access?) then (Ya)
        :Redirect ke halaman pembayaran;
    elseif (Memiliki pesanan.access?) then (Ya)
        :Redirect ke halaman pesanan;
    elseif (Memiliki waiting-list.access?) then (Ya)
        :Redirect ke kelola waiting list;
    elseif (Memiliki kitchen.access?) then (Ya)
        :Redirect ke Kitchen Display;
    elseif (Memiliki menu/access master data?) then (Ya)
        :Redirect ke halaman master data;
    else (Tidak)
        :Redirect ke halaman awal;
    endif
endif

|User|
:Mengakses halaman sistem;

|Sistem|
:Memeriksa middleware permission route;

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
