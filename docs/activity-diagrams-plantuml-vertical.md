# Activity Diagram UML Warung Baleganjur - Versi Vertikal

Dokumen ini berisi versi vertikal dari Activity Diagram inti Sistem Informasi Pemesanan Warung Baleganjur.

Catatan:
- Diagram tidak menggunakan swimlane agar alurnya lebih rapi dan garis tidak banyak bersilangan.
- Aktor tetap ditulis pada nama aktivitas, misalnya `Customer`, `Sistem`, `Koki`, `Kasir`, dan `Admin`.
- Status pesanan mengikuti kode project: `booking`, `menunggu`, `sedang_diubah`, `diproses`, `siap`, `selesai`, dan `batal`.

## 1. Pemesanan Customer Melalui QR Code

Acuan sequence: **Pemesanan Customer Melalui QR Code**

```plantuml
@startuml
title Activity Pemesanan Customer Melalui QR Code

start
:Customer memindai QR Code meja;
:Sistem menerima token QR;
:Sistem mencari meja berdasarkan token;

if (Meja valid dan aktif?) then (Tidak)
    :Sistem menampilkan halaman tidak ditemukan;
    :Customer menerima pesan gagal akses;
    stop
else (Ya)
    :Sistem mengambil kategori, menu, dan addon;
    :Customer melihat daftar menu;
    :Customer memilih menu, addon, jumlah, dan data pesanan;
    :Customer melakukan checkout;
endif

:Sistem memvalidasi cart;
:Sistem memvalidasi menu dan addon tersedia;
:Sistem menghitung subtotal, diskon, pajak, dan total;
:Sistem memeriksa kapasitas meja;

if (Cart dan menu valid?) then (Tidak)
    :Sistem menolak pesanan;
    :Customer melihat pesan kesalahan;
    stop
else (Ya)
endif

if (Kapasitas meja tersedia?) then (Tidak)
    :Sistem mengarahkan customer ke waiting list;
    :Customer membuka halaman waiting list;
    stop
else (Ya)
    :Sistem membuat status token pesanan;
    :Sistem menyimpan pesanan status menunggu;
    :Sistem menyimpan detail pesanan dan addon;
    :Sistem menyinkronkan status meja;
    :Customer diarahkan ke halaman status pesanan;
    stop
endif

@enduml
```

## 2. Pemantauan, Edit, dan Pembatalan Pesanan oleh Customer

Acuan sequence: **Pemantauan Status Pesanan oleh Customer**

```plantuml
@startuml
title Activity Pemantauan, Edit, dan Pembatalan Pesanan oleh Customer

start
:Customer membuka halaman status pesanan;
:Sistem menerima pesanan_id dan status token;
:Sistem memvalidasi status token;

if (Token valid?) then (Tidak)
    :Sistem menampilkan halaman tidak ditemukan;
    :Customer tidak dapat melihat pesanan;
    stop
else (Ya)
    :Sistem mengambil data pesanan dan detail;
    :Sistem memuat status terbaru;
endif

if (Pesanan sudah dibayar?) then (Ya)
    :Sistem mengirim status paid dan redirect;
    :Customer kembali ke halaman pemesanan atau waiting list;
    stop
else (Tidak)
endif

if (Pesanan masih aktif?) then (Tidak)
    :Customer selesai memantau pesanan;
    stop
else (Ya)
    :Customer melihat status pesanan;
endif

if (Aksi customer?) then (Edit pesanan)
    if (Status menunggu atau sedang_diubah?) then (Ya)
        :Customer mengubah pesanan;
        :Customer mengirim perubahan pesanan;
        :Sistem memvalidasi dan menyimpan perubahan;

        if (Perubahan valid?) then (Ya)
            :Sistem mengubah status menjadi menunggu;
            :Customer kembali melihat status pesanan;
            stop
        else (Tidak)
            :Sistem menampilkan pesan kesalahan;
            :Customer memperbaiki perubahan pesanan;
            stop
        endif
    else (Tidak)
        :Sistem menolak perubahan pesanan;
        :Customer melihat pesan pesanan tidak dapat diubah;
        stop
    endif

elseif (Batalkan pesanan)
    if (Status menunggu atau sedang_diubah?) then (Ya)
        :Sistem mengubah status pesanan menjadi batal;
        :Sistem menyinkronkan status meja;
        :Sistem mengaktifkan waiting list berikutnya jika tersedia;
        :Customer melihat status pesanan batal;
        stop
    else (Tidak)
        :Sistem menolak pembatalan pesanan;
        :Customer melihat pesan pesanan tidak dapat dibatalkan;
        stop
    endif

else (Pantau saja)
    :Customer melihat status terbaru;
    :Customer selesai memantau pesanan;
    stop
endif

@enduml
```

## 3. Proses Pesanan pada Kitchen Display

Acuan sequence: **Proses Kitchen Display oleh Koki**

```plantuml
@startuml
title Activity Proses Pesanan pada Kitchen Display

start
:Koki membuka Kitchen Display;
:Sistem memeriksa permission kitchen.access;

if (Akses diizinkan?) then (Tidak)
    :Sistem menampilkan 403 Forbidden;
    stop
else (Ya)
    :Sistem mengambil pesanan aktif;
    :Sistem menampilkan daftar pesanan aktif;
endif

:Koki memilih pesanan;
:Koki memilih aksi perubahan status;

if (Transisi status valid?) then (Tidak)
    :Sistem menolak perubahan status;
    :Koki melihat pesan gagal;
    stop
else (Ya)
endif

if (Aksi yang dipilih?) then (Mulai proses)
    :Sistem mengubah status menunggu menjadi diproses;
    :Sistem menyimpan chef_id jika belum ada;
elseif (Tandai siap)
    :Sistem mengubah status diproses menjadi siap;
else (Kembalikan ke proses)
    :Sistem mengubah status siap menjadi diproses;
endif

:Sistem memperbarui daftar kitchen;
:Koki melihat status pesanan terbaru;
stop

@enduml
```

## 4. Pembayaran Pesanan oleh Kasir

Acuan sequence: **Pembayaran Pesanan oleh Kasir**

```plantuml
@startuml
title Activity Pembayaran Pesanan oleh Kasir

start
:Kasir membuka halaman pembayaran;
:Sistem memeriksa permission pembayaran.access;

if (Akses diizinkan?) then (Tidak)
    :Sistem menampilkan 403 Forbidden;
    stop
else (Ya)
    :Sistem mengambil pesanan status siap dan belum dibayar;
    :Kasir melihat daftar pesanan siap bayar;
endif

:Kasir memilih pesanan;
:Sistem mengambil detail pesanan;
:Sistem menampilkan modal pembayaran;
:Kasir memilih metode pembayaran;
:Kasir mengisi nominal atau referensi pembayaran;
:Kasir mengonfirmasi pembayaran;
:Sistem memvalidasi metode pembayaran;
:Sistem memeriksa ulang status pesanan;

if (Pesanan masih siap dan belum dibayar?) then (Tidak)
    :Sistem menampilkan pesan gagal;
    stop
else (Ya)
endif

if (Metode tunai?) then (Ya)
    if (Nominal cukup?) then (Tidak)
        :Sistem menampilkan pesan nominal kurang;
        stop
    else (Ya)
        :Sistem menghitung kembalian;
        :Sistem mengubah status pesanan menjadi selesai;
        :Sistem menyimpan data pembayaran;
        :Sistem mengaktifkan waiting list berikutnya jika kapasitas tersedia;

        if (Cetak struk dipilih?) then (Ya)
            :Sistem membuka halaman struk;
            :Kasir mencetak struk;
            :Kasir melihat pembayaran berhasil;
            stop
        else (Tidak)
            :Kasir melihat pembayaran berhasil;
            stop
        endif
    endif
else (Transfer/QRIS)
    :Sistem mengisi nominal bayar sebesar total;
    :Sistem mengatur kembalian 0;
    :Sistem mengubah status pesanan menjadi selesai;
    :Sistem menyimpan data pembayaran;
    :Sistem mengaktifkan waiting list berikutnya jika kapasitas tersedia;

    if (Cetak struk dipilih?) then (Ya)
        :Sistem membuka halaman struk;
        :Kasir mencetak struk;
        :Kasir melihat pembayaran berhasil;
        stop
    else (Tidak)
        :Kasir melihat pembayaran berhasil;
        stop
    endif
endif

@enduml
```

## 5. Waiting List atau Booking Meja

Acuan sequence: **Waiting List / Booking Meja**

```plantuml
@startuml
title Activity Waiting List atau Booking Meja

start
:Customer membuka halaman waiting list;
:Sistem mengambil daftar meja aktif;
:Sistem menghitung kursi terpakai dan jumlah booking;
:Sistem menampilkan kondisi meja;
:Customer memilih meja;
:Customer memilih menu, addon, jumlah, dan data customer;
:Customer melakukan checkout waiting list;
:Sistem memvalidasi data customer dan cart;
:Sistem memvalidasi menu dan addon tersedia;

if (Data valid?) then (Ya)
    :Sistem menghitung subtotal, diskon, pajak, dan total;
    :Sistem membuat status token pesanan;
    :Sistem menyimpan pesanan status booking;
    :Sistem menyimpan detail pesanan dan addon;
    :Sistem mengarahkan customer ke halaman status pesanan;
    :Customer melihat status booking;
    stop
else (Tidak)
    :Sistem menampilkan pesan kesalahan;
    :Customer memperbaiki data booking;
    stop
endif

@enduml
```

## 6. Aktivasi Waiting List Setelah Meja Tersedia

Acuan sequence: **Aktivasi Waiting List Setelah Meja Tersedia**

```plantuml
@startuml
title Activity Aktivasi Waiting List Setelah Meja Tersedia

start

if (Pemicu aktivasi?) then (Pembayaran selesai)
    :Kasir menyelesaikan pembayaran pesanan;
elseif (Pesanan dibatalkan)
    :Customer membatalkan pesanan aktif;
else (Aktivasi manual)
    :Kasir memilih booking untuk diaktifkan;
endif

:Sistem mengambil data meja;
:Sistem menghitung kursi terpakai;
:Sistem menghitung kapasitas tersisa;

if (Kapasitas tersedia?) then (Tidak)
    :Sistem tidak mengaktifkan waiting list;
    stop
else (Ya)
endif

:Sistem mencari pesanan booking paling awal;

if (Ada booking?) then (Tidak)
    :Sistem menyinkronkan status meja;
    stop
else (Ya)
endif

if (Jumlah orang muat?) then (Tidak)
    :Sistem menunda aktivasi booking;
    :Sistem menyinkronkan status meja;
    stop
else (Ya)
    :Sistem mengubah status booking menjadi menunggu;
    :Sistem menyinkronkan status meja;
    :Sistem memperbarui daftar Kitchen Display;
    :Kasir melihat waiting list berhasil diaktifkan;
    stop
endif

@enduml
```

## 7. Admin atau Koki Mengelola Menu dan Addon

Acuan sequence: **Admin atau Koki Mengelola Menu dan Addon**

```plantuml
@startuml
title Activity Admin atau Koki Mengelola Menu dan Addon

start
:Admin atau Koki membuka halaman menu atau addon;
:Sistem memeriksa permission menu atau addon;

if (Akses diizinkan?) then (Tidak)
    :Sistem menampilkan 403 Forbidden;
    stop
else (Ya)
    :Sistem menampilkan daftar data;
endif

:Admin atau Koki memilih tambah atau edit data;

if (Kelola menu?) then (Ya)
    :Sistem mengambil kategori aktif;
    :Sistem mengambil addon tersedia;
    :Admin atau Koki mengisi data menu, status, gambar, dan relasi addon;
    :Sistem memvalidasi data menu;

    if (Input valid?) then (Ya)
        if (Ada gambar menu?) then (Ya)
            :Sistem menyimpan gambar menu;
            :Sistem membuat thumbnail gambar;
            :Sistem menyimpan data menu;
            :Sistem menyimpan relasi addon_menu;
            :Sistem memperbarui versi data menu customer;
            :Sistem menampilkan pesan berhasil;
            :Admin atau Koki kembali ke daftar data;
            stop
        else (Tidak)
            :Sistem menyimpan data menu;
            :Sistem menyimpan relasi addon_menu;
            :Sistem memperbarui versi data menu customer;
            :Sistem menampilkan pesan berhasil;
            :Admin atau Koki kembali ke daftar data;
            stop
        endif
    else (Tidak)
        :Sistem menampilkan error validasi;
        stop
    endif
else (Kelola addon)
    :Admin atau Koki mengisi data addon dan status;
    :Sistem memvalidasi data addon;

    if (Input valid?) then (Ya)
        :Sistem menyimpan data addon;
        :Sistem memperbarui versi data menu customer;
        :Sistem menampilkan pesan berhasil;
        :Admin atau Koki kembali ke daftar data;
        stop
    else (Tidak)
        :Sistem menampilkan error validasi;
        stop
    endif
endif

@enduml
```

## 8. Admin Mengelola Meja dan QR Code

Acuan sequence: **Admin Mengelola Meja dan QR Code**

```plantuml
@startuml
title Activity Admin Mengelola Meja dan QR Code

start
:Admin membuka halaman meja;
:Sistem memeriksa permission meja;

if (Akses diizinkan?) then (Tidak)
    :Sistem menampilkan 403 Forbidden;
    stop
else (Ya)
    :Sistem menampilkan daftar meja;
endif

:Admin memilih tambah atau edit meja;
:Sistem menyiapkan form meja dan token QR;
:Admin mengisi nomor meja, kapasitas, status, dan token QR;

if (Admin meminta regenerate token?) then (Ya)
    :Sistem membuat token QR baru yang unik;
    :Admin melihat token QR baru;
    :Sistem memvalidasi nomor meja, token QR, status, dan kapasitas;

    if (Input valid?) then (Tidak)
        :Sistem menampilkan error validasi;
        stop
    else (Ya)
        :Sistem menyimpan data meja;
    endif

    if (Cetak QR?) then (Ya)
        :Sistem mengambil data meja;
        :Sistem membuat QR Code;
        :Sistem membuat PDF QR meja;
        :Admin mencetak QR meja;
        :Admin melihat data meja berhasil disimpan;
        stop
    else (Tidak)
        :Admin melihat data meja berhasil disimpan;
        stop
    endif
else (Tidak)
    :Sistem memvalidasi nomor meja, token QR, status, dan kapasitas;

    if (Input valid?) then (Tidak)
        :Sistem menampilkan error validasi;
        stop
    else (Ya)
        :Sistem menyimpan data meja;
    endif

    if (Cetak QR?) then (Ya)
        :Sistem mengambil data meja;
        :Sistem membuat QR Code;
        :Sistem membuat PDF QR meja;
        :Admin mencetak QR meja;
        :Admin melihat data meja berhasil disimpan;
        stop
    else (Tidak)
        :Admin melihat data meja berhasil disimpan;
        stop
    endif
endif

@enduml
```

## 9. Login dan Pengalihan Berdasarkan Permission

Acuan sequence: **Login dan Redirect Berdasarkan Permission**

```plantuml
@startuml
title Activity Login dan Pengalihan Berdasarkan Permission

start
:User membuka halaman login;
:User mengisi email dan password;
:User mengirim form login;
:Sistem memvalidasi kredensial;
:Sistem mengambil data user, role, dan permission;

if (Login berhasil?) then (Tidak)
    :Sistem menampilkan error login;
    :User mengisi ulang form login;
    stop
else (Ya)
endif

:Sistem menentukan halaman tujuan berdasarkan permission;
:User mengakses halaman sistem;
:Sistem memeriksa middleware permission route;

if (Permission sesuai?) then (Ya)
    :Sistem menampilkan halaman;
    :User menggunakan fitur sesuai hak akses;
    stop
else (Tidak)
    :Sistem menampilkan 403 Forbidden;
    stop
endif

@enduml
```
