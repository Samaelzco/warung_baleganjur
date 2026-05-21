# Panduan Prompt ChatGPT untuk Generate Activity Diagram

Dokumen ini dibuat sebagai bahan untuk ChatGPT atau AI pembuat gambar agar dapat menghasilkan activity diagram yang rapi, konsisten, dan sesuai logic project **Sistem Informasi Pemesanan Warung Baleganjur Berbasis QR Code dan Kitchen Display**.

Tujuan dokumen ini bukan untuk langsung menjadi isi laporan, tetapi menjadi referensi teknis bagi AI agar dapat menerjemahkan alur sistem menjadi activity diagram UML.

## 1. Instruksi Umum untuk ChatGPT

Gunakan instruksi berikut sebelum membuat activity diagram:

```text
Analisis terlebih dahulu alur proses yang diberikan. Ubah alur tersebut menjadi UML Activity Diagram yang rapi, formal, dan sesuai standar. Jangan langsung menggambar sebelum memahami aktor, aktivitas utama, decision node, cabang alternatif, alur balik, dan final node.

Gunakan swimlane/partition sesuai aktor yang terlibat. Gunakan activity/action berbentuk rounded rectangle, initial node berbentuk lingkaran hitam, final node berbentuk lingkaran hitam dengan cincin luar, dan decision/merge node berbentuk diamond kosong.

Jangan menulis label di dalam diamond decision/merge. Label pertanyaan decision diletakkan di dekat diamond, sedangkan label kondisi diletakkan pada garis panah keluar dari diamond. Jangan menggabungkan dua aksi berbeda dalam satu kotak aktivitas. Hindari label cabang yang memakai kata "atau"; jika diperlukan, pecah menjadi decision bertingkat.

Prioritaskan layout yang rapi dan mudah digambar ulang secara manual di StarUML. Buat alur utama dominan dari atas ke bawah. Gunakan beberapa final node jika lebih rapi daripada membuat panah terlalu panjang atau saling silang. Gunakan fork/join hanya jika benar-benar ada aktivitas paralel.
```

## 2. Aturan Notasi

- **Initial node** wajib ada pada awal proses.
- **Activity/action** harus berisi satu aktivitas utama, contoh: `Memvalidasi data pesanan`, bukan `Memvalidasi dan menyimpan pesanan`.
- **Decision node** berupa diamond kosong.
- **Label decision** seperti `Status data pesanan?` diletakkan di dekat diamond.
- **Label cabang** seperti `Data sesuai`, `Data tidak sesuai`, `Kapasitas cukup`, `Kapasitas tidak cukup` diletakkan pada panah keluar.
- **Merge node** digunakan bila beberapa alur alternatif kembali ke aktivitas yang sama.
- **Fork/join** hanya digunakan jika aktivitas benar-benar berjalan bersamaan.
- Hindari terlalu banyak decision dalam satu titik. Jika lebih dari dua cabang, gunakan decision bertingkat.
- Untuk cabang error yang masih bisa diperbaiki pelanggan, alur boleh kembali ke aktivitas input pelanggan.
- Untuk cabang fatal seperti token tidak valid atau halaman tidak ditemukan, alur boleh langsung menuju final node.

## 3. Status Penting dalam Project

Status pesanan utama:

- `booking`: pesanan waiting list.
- `menunggu`: pesanan sudah masuk dan menunggu diproses.
- `sedang_diubah`: pelanggan sedang mengubah pesanan.
- `diproses`: pesanan sedang dikerjakan di dapur.
- `siap`: pesanan sudah siap dan bisa dibayar.
- `selesai`: pesanan selesai setelah pembayaran dicatat.
- `batal`: pesanan dibatalkan.

Status meja:

- `kosong`: tidak ada pesanan aktif.
- `terisi`: ada pesanan aktif.
- `reservasi`: meja dalam kondisi reservasi.
- `nonaktif`: meja tidak dapat digunakan.

Status aktif untuk kapasitas meja dihitung dari pesanan dengan status:

- `menunggu`
- `sedang_diubah`
- `diproses`
- `siap`

Pesanan yang sudah memiliki `metode_pembayaran` tidak dihitung sebagai pesanan aktif untuk kapasitas.

## 4. Proses 1 - Melakukan Pemesanan Melalui QR Meja

### Aktor

- Pelanggan
- Sistem

### Logic Project

Pelanggan memindai QR Code meja. Sistem membaca token QR dan memeriksa apakah meja ditemukan serta status meja bukan `nonaktif`. Jika QR tidak valid atau meja nonaktif, sistem menampilkan halaman tidak ditemukan. Jika QR valid, sistem menampilkan halaman pemesanan yang berisi kategori, menu, dan addon.

Pelanggan memilih menu, memilih addon, mengisi data pesanan, lalu mengirim pesanan melalui checkout. Sistem memvalidasi data pesanan, meliputi cart, menu, addon, jumlah item, nama pelanggan, catatan, voucher, dan jumlah tamu. Sistem hanya memproses menu yang statusnya `tersedia` dan addon yang tersedia serta memang terhubung dengan menu yang dipilih.

Jika data pesanan tidak sesuai, sistem menampilkan pesan kesalahan. Pelanggan dapat memperbaiki item atau data pesanan, kemudian mengirim ulang. Jika data sesuai, sistem menghitung subtotal, diskon, pajak, dan total harga. Setelah itu sistem memeriksa kapasitas meja berdasarkan jumlah tamu. Jika kapasitas tidak cukup, sistem menampilkan pesan jumlah tamu melebihi kapasitas. Pelanggan dapat memperbaiki jumlah tamu. Jika kapasitas cukup, sistem menyimpan pesanan dengan status `menunggu`, menyimpan detail pesanan dan addon, menyinkronkan status meja, lalu menampilkan halaman status pesanan.

### Alur untuk Diagram

1. Pelanggan memindai QR Code meja.
2. Sistem memvalidasi QR Code meja.
3. Decision: `Status QR Code meja?`
   - `QR valid`: sistem menampilkan halaman pemesanan.
   - `QR tidak valid`: sistem menampilkan halaman tidak ditemukan, lalu final node.
4. Pelanggan memilih menu dan addon.
5. Pelanggan mengisi data pesanan.
6. Pelanggan mengirim pesanan.
7. Sistem memvalidasi data pesanan.
8. Decision: `Status data pesanan?`
   - `Data sesuai`: sistem menghitung total pesanan.
   - `Data tidak sesuai`: sistem menampilkan pesan kesalahan, lalu alur kembali ke aktivitas pelanggan untuk memperbaiki data pesanan atau memilih menu dan addon.
9. Sistem memeriksa kapasitas meja.
10. Decision: `Status kapasitas meja?`
    - `Kapasitas cukup`: sistem menyimpan pesanan status menunggu.
    - `Kapasitas tidak cukup`: sistem menampilkan pesan tamu melebihi kapasitas, lalu alur kembali ke aktivitas pelanggan untuk memperbaiki jumlah tamu.
11. Sistem memperbarui status meja.
12. Sistem menampilkan halaman status pesanan.
13. Pelanggan melihat halaman status pesanan.
14. Final node.

### Catatan Diagram

- `Memvalidasi data pesanan` tidak perlu dipecah terlalu banyak.
- Jika ingin lebih spesifik, gunakan `Memvalidasi item dan data pesanan`.
- Jangan menghubungkan kapasitas tidak cukup ke waiting list otomatis, karena pada project QR meja sistem menampilkan pesan agar jumlah tamu diubah.

## 5. Proses 2 - Melihat Status Pesanan

### Aktor

- Pelanggan
- Sistem

### Logic Project

Pelanggan membuka halaman status pesanan melalui URL yang berisi `pesanan` dan `status_token`. Sistem memvalidasi token status pesanan. Jika token tidak cocok, sistem menampilkan halaman tidak ditemukan. Jika token valid, sistem mengambil data pesanan, detail pesanan, addon, dan meja.

Sistem menampilkan detail pesanan dan status awal. Pelanggan melihat halaman status pesanan. Sistem kemudian memuat pembaruan status pesanan melalui endpoint JSON. Jika pesanan masih aktif, sistem menampilkan status terbaru. Status aktif yang muncul di halaman pelanggan meliputi `booking`, `menunggu`, `sedang_diubah`, `diproses`, dan `siap`. Jika pesanan selesai dan sudah memiliki metode pembayaran, sistem mengarahkan pelanggan kembali ke halaman pemesanan berdasarkan QR Code meja. Jika pesanan batal, sistem juga mengarahkan pelanggan kembali ke halaman pemesanan.

### Alur untuk Diagram

1. Pelanggan membuka halaman status pesanan.
2. Sistem memvalidasi status token pesanan.
3. Decision: `Status token pesanan?`
   - `Valid`: sistem mengambil data pesanan.
   - `Tidak valid`: sistem menampilkan halaman tidak ditemukan, lalu final node.
4. Sistem menampilkan detail pesanan.
5. Sistem menampilkan status awal pesanan.
6. Pelanggan melihat status pesanan.
7. Sistem memuat pembaruan status pesanan.
8. Decision: `Status pesanan?`
   - `Masih aktif`: sistem menampilkan status terbaru pesanan, lalu pelanggan memantau perubahan status pesanan.
   - `Tidak aktif`: lanjut decision kedua.
9. Decision: `Status akhir pesanan?`
   - `Pembayaran selesai`: sistem menampilkan status pembayaran selesai, lalu mengarahkan ke halaman pemesanan.
   - `Pesanan batal`: sistem mengarahkan ke halaman pemesanan.
10. Final node.

### Catatan Diagram

- Hindari tiga cabang langsung dari decision `Status pesanan?`. Gunakan decision bertingkat agar rapi.
- Jangan memakai label `Selesai atau batal`; pecah menjadi `Pembayaran selesai` dan `Pesanan batal`.
- Tidak perlu fork karena proses ini tidak perlu digambarkan paralel.

## 6. Proses 3 - Mengubah Pesanan

### Aktor

- Pelanggan
- Sistem

### Logic Project

Pelanggan dapat mengubah pesanan dari halaman status pesanan selama status pesanan masih `menunggu` atau `sedang_diubah`. Sistem memvalidasi `status_token` dan memastikan pesanan terkait meja yang benar. Jika status masih `menunggu`, sistem mengubah status menjadi `sedang_diubah` dan mengarahkan pelanggan ke halaman pemesanan dalam mode edit. Jika status tidak memenuhi syarat, sistem menolak perubahan.

Pada mode edit, pelanggan mengubah menu, addon, jumlah item, catatan, atau jumlah tamu. Saat perubahan dikirim, sistem memvalidasi token, status pesanan, dan data perubahan. Sistem menghitung ulang item, subtotal, diskon, pajak, dan total harga. Untuk kapasitas meja, sistem menghitung kapasitas dengan mengecualikan pesanan yang sedang diedit agar jumlah tamu pesanan sendiri tidak dihitung dua kali.

Jika data perubahan tidak sesuai, sistem menampilkan pesan kesalahan. Jika kapasitas tidak mencukupi, sistem menampilkan pesan jumlah tamu melebihi kapasitas. Jika valid, sistem menghapus detail pesanan lama, menyimpan detail baru, memperbarui total pesanan, mengubah status kembali menjadi `menunggu`, menyinkronkan status meja, membersihkan cache kitchen display, lalu mengarahkan pelanggan kembali ke halaman status pesanan.

### Alur untuk Diagram

1. Pelanggan memilih aksi ubah pesanan pada halaman status.
2. Sistem memvalidasi token dan status pesanan.
3. Decision: `Status pesanan?`
   - `Dapat diubah`: sistem mengubah status menjadi `sedang_diubah`.
   - `Tidak dapat diubah`: sistem menampilkan pesan pesanan tidak dapat diubah, lalu final node.
4. Sistem menampilkan halaman ubah pesanan.
5. Pelanggan mengubah item pesanan.
6. Pelanggan mengubah data pesanan jika diperlukan.
7. Pelanggan mengirim perubahan.
8. Sistem memvalidasi data perubahan.
9. Decision: `Status data perubahan?`
   - `Sesuai`: sistem menghitung ulang total pesanan.
   - `Tidak sesuai`: sistem menampilkan pesan kesalahan, lalu alur kembali ke aktivitas pelanggan mengubah pesanan.
10. Sistem memeriksa kapasitas meja.
11. Decision: `Status kapasitas meja?`
    - `Cukup`: sistem menyimpan perubahan pesanan.
    - `Tidak cukup`: sistem menampilkan pesan tamu melebihi kapasitas, lalu alur kembali ke aktivitas pelanggan mengubah data pesanan.
12. Sistem mengubah status pesanan menjadi `menunggu`.
13. Sistem menyinkronkan status meja.
14. Sistem menampilkan halaman status pesanan.
15. Pelanggan melihat status pesanan.
16. Final node.

### Catatan Diagram

- Jangan mulai alur dari halaman pemesanan langsung. Mulai dari aksi ubah pesanan pada halaman status.
- Cabang data salah dan kapasitas tidak cukup sebaiknya kembali ke aktivitas pelanggan.
- Tidak perlu menggambarkan detail hapus detail lama dan insert detail baru kecuali diminta sangat teknis.

## 7. Proses 4 - Membatalkan Pesanan

### Aktor

- Pelanggan
- Sistem

### Logic Project

Pelanggan membatalkan pesanan dari halaman status pesanan. Sistem memvalidasi `status_token` dan memastikan status pesanan masih dapat dibatalkan. Pesanan hanya dapat dibatalkan jika statusnya `menunggu` atau `sedang_diubah`. Jika status tidak sesuai, sistem menolak pembatalan.

Jika pembatalan valid, sistem mengubah status pesanan menjadi `batal`. Setelah itu sistem menyinkronkan status meja. Jika ada waiting list untuk meja yang sama dan kapasitas tersedia, sistem mencoba mengaktifkan waiting list berikutnya. Sistem juga membersihkan cache kitchen display dan mengarahkan pelanggan kembali ke halaman status atau halaman pemesanan.

### Alur untuk Diagram

1. Pelanggan memilih aksi batalkan pesanan.
2. Sistem memvalidasi token pesanan.
3. Sistem memvalidasi status pesanan.
4. Decision: `Status pembatalan pesanan?`
   - `Dapat dibatalkan`: sistem mengubah status pesanan menjadi batal.
   - `Tidak dapat dibatalkan`: sistem menampilkan pesan pesanan tidak dapat dibatalkan, lalu final node.
5. Sistem menyinkronkan status meja.
6. Sistem memeriksa waiting list pada meja terkait.
7. Decision: `Waiting list tersedia?`
   - `Tersedia`: sistem mengaktifkan waiting list berikutnya jika kapasitas mencukupi.
   - `Tidak tersedia`: sistem melanjutkan proses.
8. Sistem membersihkan cache kitchen display.
9. Sistem mengarahkan pelanggan ke halaman pemesanan atau status.
10. Final node.

### Catatan Diagram

- Status yang dapat dibatalkan adalah `menunggu` dan `sedang_diubah`.
- Waiting list tidak selalu aktif. Gunakan decision agar tidak terlihat wajib.

## 8. Proses 5 - Membuat Waiting List

### Aktor

- Pelanggan
- Sistem

### Logic Project

Pelanggan membuka halaman waiting list. Sistem mengambil daftar meja yang tidak nonaktif, menghitung kursi terpakai berdasarkan pesanan aktif, menghitung sisa kursi, dan menghitung jumlah waiting list per meja. Pelanggan memilih meja, lalu sistem menampilkan halaman pemesanan waiting list. Pelanggan memilih menu dan addon, masuk checkout, mengisi nama pelanggan, catatan, jumlah tamu, dan mengirim waiting list.

Sistem memvalidasi data waiting list. Berbeda dengan pemesanan QR meja, waiting list tidak membutuhkan kapasitas meja tersedia saat submit, karena pesanan waiting list disimpan dengan status `booking`. Sistem tetap memvalidasi cart, menu, addon, jumlah item, voucher, dan data pelanggan. Sistem menghitung subtotal, diskon, pajak, dan total. Jika data tidak valid, sistem menampilkan pesan kesalahan. Jika valid, sistem menyimpan pesanan dengan kode `WTL-...`, status `booking`, status token, detail pesanan, dan addon. Setelah itu sistem mengarahkan pelanggan ke halaman status pesanan.

### Alur untuk Diagram

1. Pelanggan membuka halaman waiting list.
2. Sistem mengambil daftar meja aktif.
3. Sistem menghitung kapasitas, kursi terpakai, sisa kursi, dan jumlah waiting list.
4. Sistem menampilkan kondisi meja.
5. Pelanggan memilih meja.
6. Sistem menampilkan halaman pemesanan waiting list.
7. Pelanggan memilih menu dan addon.
8. Pelanggan mengisi data waiting list.
9. Pelanggan mengirim waiting list.
10. Sistem memvalidasi data waiting list.
11. Decision: `Status data waiting list?`
    - `Sesuai`: sistem menghitung total waiting list.
    - `Tidak sesuai`: sistem menampilkan pesan kesalahan, lalu alur kembali ke pengisian data waiting list.
12. Sistem menyimpan pesanan dengan status `booking`.
13. Sistem menyimpan detail pesanan dan addon.
14. Sistem menampilkan halaman status waiting list.
15. Pelanggan melihat status waiting list.
16. Final node.

### Catatan Diagram

- Jangan gunakan decision kapasitas cukup sebagai syarat submit waiting list, karena waiting list memang dibuat untuk kondisi menunggu.
- Jika ingin menampilkan kapasitas, gunakan sebagai informasi kondisi meja, bukan syarat penyimpanan.

## 9. Proses 6 - Memproses Kitchen Display

### Aktor

- Koki
- Sistem

### Logic Project

Koki membuka halaman kitchen display. Sistem menampilkan pesanan aktif dengan status `menunggu`, `sedang_diubah`, `diproses`, dan `siap`. Sistem juga melakukan polling untuk mendeteksi pesanan baru atau perubahan data pesanan.

Koki memilih pesanan dan memperbarui status pengerjaan. Sistem hanya mengizinkan perubahan status tertentu. Jika status saat ini `menunggu`, target yang diizinkan adalah `diproses`. Jika status saat ini `diproses`, target yang diizinkan adalah `siap`. Jika status saat ini `siap`, target yang diizinkan adalah `diproses` untuk koreksi. Jika target status tidak sesuai aturan, sistem tidak memperbarui pesanan.

Saat status diubah menjadi `diproses`, sistem mengisi `chef_id` jika sebelumnya belum ada. Setelah update berhasil, sistem membersihkan cache hitungan status kitchen.

### Alur untuk Diagram

1. Koki membuka halaman kitchen display.
2. Sistem memuat daftar pesanan aktif.
3. Sistem menampilkan pesanan pada kitchen display.
4. Koki memilih pesanan.
5. Koki memilih perubahan status.
6. Sistem memvalidasi target status.
7. Decision: `Status perubahan pesanan?`
   - `Valid`: sistem memperbarui status pesanan.
   - `Tidak valid`: sistem menolak perubahan status, lalu final node atau kembali ke daftar pesanan.
8. Decision: `Status target?`
   - `Diproses`: sistem mengisi chef pada pesanan jika belum ada.
   - `Siap`: sistem melanjutkan update status.
9. Sistem membersihkan cache kitchen display.
10. Sistem menampilkan daftar pesanan terbaru.
11. Koki melihat status pesanan terbaru.
12. Final node.

### Catatan Diagram

- Jika ingin menggambarkan polling pesanan baru, dapat ditambahkan aktivitas `Memuat pembaruan pesanan` sebelum daftar ditampilkan.
- Tidak perlu fork kecuali ingin menggambarkan audio notifikasi sebagai proses paralel. Untuk laporan, tidak perlu fork.
- Perubahan status yang paling utama adalah `menunggu -> diproses -> siap`.

## 10. Proses 7 - Memproses Pembayaran

### Aktor

- Kasir
- Sistem

### Logic Project

Kasir membuka halaman pembayaran. Sistem menampilkan daftar pesanan yang berstatus `siap` dan belum memiliki metode pembayaran. Kasir memilih pesanan untuk dibayar. Sistem mengambil detail pesanan, item, total, dan data meja, lalu menampilkan modal pembayaran.

Kasir memilih metode pembayaran. Metode pembayaran yang valid adalah `tunai`, `transfer`, dan `qris`. Jika metode `transfer` atau `qris`, sistem mengatur nilai dibayar sama dengan total dan kembalian nol. Jika metode `tunai`, sistem menghitung kembalian dari nominal dibayar dikurangi total. Jika nominal tunai kurang dari total, pembayaran ditolak.

Saat pembayaran dikonfirmasi, sistem menjalankan database transaction dan melakukan lock pada pesanan. Sistem memastikan pesanan masih berstatus `siap` dan belum dibayar. Jika status berubah atau pesanan sudah dibayar, sistem menampilkan pesan kesalahan. Jika valid, sistem menyimpan metode pembayaran, nominal dibayar, kembalian, referensi pembayaran, kasir, status `selesai`, dan waktu selesai. Setelah pembayaran tersimpan, sistem menutup modal, menampilkan notifikasi sukses, mengaktifkan waiting list berikutnya jika ada kapasitas, membersihkan cache kitchen, dan mencetak struk jika opsi cetak aktif.

### Alur untuk Diagram

1. Kasir membuka halaman pembayaran.
2. Sistem memuat daftar pesanan siap dan belum dibayar.
3. Kasir memilih pesanan.
4. Sistem mengambil detail pesanan.
5. Sistem menampilkan form pembayaran.
6. Kasir memilih metode pembayaran.
7. Kasir memasukkan nominal pembayaran atau referensi pembayaran.
8. Sistem menghitung kembalian.
9. Kasir mengonfirmasi pembayaran.
10. Sistem memvalidasi data pembayaran.
11. Decision: `Status data pembayaran?`
    - `Sesuai`: sistem memvalidasi status pesanan.
    - `Tidak sesuai`: sistem menampilkan pesan kesalahan, lalu kembali ke form pembayaran.
12. Decision: `Status pesanan?`
    - `Siap dan belum dibayar`: sistem menyimpan pembayaran.
    - `Tidak valid`: sistem menampilkan pesan pesanan tidak dapat dibayar, lalu final node atau kembali ke daftar.
13. Sistem mengubah status pesanan menjadi `selesai`.
14. Sistem mencatat kasir dan waktu selesai.
15. Sistem mengaktifkan waiting list berikutnya jika tersedia dan kapasitas mencukupi.
16. Sistem membersihkan cache kitchen display.
17. Decision: `Cetak struk?`
    - `Dicetak`: sistem membuka struk pembayaran.
    - `Tidak dicetak`: sistem melewati cetak struk.
18. Sistem menampilkan notifikasi pembayaran berhasil.
19. Final node.

### Catatan Diagram

- Data pembayaran tetap berada di tabel `pesanans`, tidak ada tabel `pembayarans`.
- Untuk metode non-tunai, `dibayar = total` dan `kembalian = 0`.
- Untuk metode tunai, nominal dibayar harus lebih besar atau sama dengan total.
- Pembayaran hanya dapat diproses jika pesanan berstatus `siap`.

## 11. Template Prompt Per Proses

Gunakan format prompt berikut ketika meminta AI menggambar salah satu proses:

```text
Buatkan UML Activity Diagram untuk proses: [NAMA PROSES].

Gunakan swimlane: [AKTOR 1], [AKTOR 2], dan [AKTOR LAIN JIKA ADA].

Ikuti aturan berikut:
- Gunakan notasi UML Activity Diagram, bukan flowchart umum.
- Gunakan initial node, activity/action, decision node kosong, merge node bila perlu, dan activity final node.
- Label decision diletakkan di luar diamond.
- Label cabang diletakkan pada panah keluar decision.
- Jangan isi teks di dalam diamond.
- Jangan gunakan action ganda dalam satu kotak.
- Hindari cabang dengan kata "atau"; pecah menjadi decision bertingkat.
- Buat alur utama dari atas ke bawah.
- Hindari panah saling silang.
- Gunakan beberapa final node jika lebih rapi.
- Gunakan fork/join hanya jika ada proses paralel yang benar-benar perlu digambarkan.

Sebelum menggambar, analisis alur berikut dan susun ulang menjadi activity diagram yang paling rapi:
[TEMPELKAN ALUR PROSES DARI DOKUMEN INI]
```

## 12. Checklist Validasi Hasil Gambar

Setelah AI menghasilkan diagram, periksa hal berikut:

- Apakah setiap aktivitas berada pada swimlane aktor yang tepat?
- Apakah decision node berbentuk diamond kosong?
- Apakah label kondisi ada pada panah, bukan di dalam diamond?
- Apakah action hanya berisi satu aktivitas?
- Apakah alur gagal yang masih bisa diperbaiki kembali ke aktivitas input?
- Apakah alur fatal menuju final node?
- Apakah alur sukses berakhir pada final node?
- Apakah diagram terlalu mirip flowchart teknis?
- Apakah ada panah yang saling silang?
- Apakah nama aktivitas sesuai istilah project?

