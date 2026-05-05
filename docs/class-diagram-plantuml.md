# Class Diagram UML Warung Baleganjur

Dokumen ini berisi class diagram PlantUML berdasarkan ERD, model Laravel, migration, dan alur sistem Warung Baleganjur.

Catatan:
- Diagram difokuskan pada class domain utama.
- Field teknis berulang seperti `created_at` dan `updated_at` tidak ditampilkan agar diagram tetap mudah dibaca.
- Relasi dibuat sederhana agar diagram tetap rapi dan mudah dibaca pada laporan.

## Class Diagram

```plantuml
@startuml
title Class Diagram Warung Baleganjur

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam classAttributeIconSize 0
skinparam linetype ortho
hide empty members

class User {
    +id: bigint
    +name: string
    +email: string
    +password: string
    +is_active: boolean
    --
    +initials(): string
    +roles()
}

class Role {
    +id: bigint
    +name: string
    +guard_name: string
    --
    +permissions()
}

class Permission {
    +id: bigint
    +name: string
    +guard_name: string
}

class Meja {
    +id: bigint
    +nomor_meja: string
    +qr_token: string
    +status: string
    +kapasitas: integer
}

class KategoriMenu {
    +id: bigint
    +nama_kategori: string
    +nama_kategori_en: string
    +deskripsi: text
    +is_active: boolean
    --
    +nama_kategori_localized(): string
}

class Menu {
    +id: bigint
    +kategori_id: bigint
    +nama_menu: string
    +nama_menu_en: string
    +deskripsi: text
    +deskripsi_en: text
    +harga: decimal
    +gambar: string
    +status: string
    --
    +kategori()
    +addons()
    +pesananDetails()
    +nama_menu_localized(): string
    +deskripsi_localized(): string
}

class Addon {
    +id: bigint
    +nama_addon: string
    +nama_addon_en: string
    +harga: decimal
    +status: string
    --
    +menus()
    +nama_addon_localized(): string
}

class AddonMenu {
    +menu_id: bigint
    +addon_id: bigint
}

class Pesanan {
    +id: bigint
    +meja_id: bigint
    +kode_pesanan: string
    +status_token: string
    +customer_name: string
    +customer_note: string
    +jumlah_orang: integer
    +subtotal: decimal
    +discount_total: decimal
    +tax_total: decimal
    +total_harga: decimal
    +status: string
    +metode_pembayaran: string
    +dibayar: decimal
    +kembalian: decimal
    +referensi_pembayaran: string
    +user_id: bigint
    +diskon_id: bigint
    +pajak_id: bigint
    +waktu_pesan: datetime
    +waktu_selesai: datetime
    --
    +meja()
    +details()
    +diskon()
    +pajak()
    +user()
}

class PesananDetail {
    +id: bigint
    +pesanan_id: bigint
    +menu_id: bigint
    +qty: integer
    +harga: decimal
    +subtotal: decimal
    --
    +pesanan()
    +menu()
    +addons()
}

class PesananDetailAddon {
    +pesanan_detail_id: bigint
    +addon_id: bigint
    +harga: decimal
}

class Pajak {
    +id: bigint
    +nama: string
    +persentase: decimal
    +is_active: boolean
}

class Diskon {
    +id: bigint
    +kode: string
    +tipe: string
    +nilai: decimal
    +min_subtotal: decimal
    +is_active: boolean
    +tanggal_mulai: date
    +tanggal_selesai: date
}

User "1" -- "0..*" Pesanan

User "0..*" -- "0..*" Role
Role "0..*" -- "0..*" Permission

Meja "1" -- "0..*" Pesanan
KategoriMenu "1" -- "0..*" Menu

Menu "1" -- "0..*" PesananDetail
Pesanan "1" *-- "1..*" PesananDetail

Menu "1" -- "0..*" AddonMenu
Addon "1" -- "0..*" AddonMenu

PesananDetail "1" -- "0..*" PesananDetailAddon
Addon "1" -- "0..*" PesananDetailAddon

Pajak "0..1" -- "0..*" Pesanan
Diskon "0..1" -- "0..*" Pesanan

@enduml
```

## Ringkasan Relasi

- `User` berelasi dengan `Pesanan` sebagai user internal yang menangani pesanan.
- `Meja` memiliki banyak `Pesanan`.
- `Pesanan` memiliki banyak `PesananDetail`.
- `Menu` memiliki banyak `PesananDetail`.
- `Menu` dan `Addon` berelasi many-to-many melalui `AddonMenu`.
- `PesananDetail` dan `Addon` berelasi many-to-many melalui `PesananDetailAddon`.
- `Pesanan` dapat menggunakan satu `Pajak` dan satu `Diskon`.
- `User`, `Role`, dan `Permission` mengikuti struktur hak akses dari Spatie Permission.
