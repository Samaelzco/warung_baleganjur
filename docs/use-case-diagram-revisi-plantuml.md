# Use Case Diagram UML Revisi Warung Baleganjur

Dokumen ini berisi use case diagram revisi untuk Sistem Informasi Pemesanan Warung Baleganjur Berbasis QR Code dan Kitchen Display.

Revisi utama:
- Aktor `Customer` diganti menjadi `Pelanggan`.
- Use case `Melakukan Logout` dihapus agar selaras dengan analisis proses dan activity diagram.
- Use case `Mengelola Master Data` dibuat lebih spesifik menjadi `Mengelola Menu dan Addon` serta `Mengelola Data Referensi`.
- Relasi `include` dan `extend` ditambahkan pada use case yang sesuai.

```plantuml
@startuml
title Use Case Diagram Sistem Informasi Pemesanan Warung Baleganjur

left to right direction

skinparam defaultFontName Arial
skinparam defaultFontSize 14
skinparam actorFontSize 14
skinparam usecaseFontSize 13
skinparam packageStyle rectangle
skinparam shadowing false
skinparam linetype ortho

actor Pelanggan as Pelanggan
actor Admin as Admin
actor Kasir as Kasir
actor Koki as Koki

rectangle "Sistem Informasi Pemesanan Warung Baleganjur\nBerbasis QR Code dan Kitchen Display" as Sistem {
    package "Fitur Pelanggan" as FP {
        usecase "Melakukan Pemesanan\nMelalui QR Meja" as UC_PesanQR
        usecase "Melihat Status\nPesanan" as UC_Status
        usecase "Mengubah\nPesanan" as UC_Ubah
        usecase "Membatalkan\nPesanan" as UC_Batal
        usecase "Membuat Waiting List /\nBooking Meja" as UC_Booking
    }

    package "Fitur Internal" as FI {
        usecase "Melakukan\nLogin" as UC_Login
        usecase "Melihat\nDashboard" as UC_Dashboard
        usecase "Mengelola\nPesanan" as UC_KelolaPesanan
        usecase "Melihat Detail\nPesanan" as UC_DetailPesanan
        usecase "Mengelola\nKitchen Display" as UC_Kitchen
        usecase "Mengelola\nPembayaran" as UC_Pembayaran
        usecase "Mengelola\nWaiting List" as UC_KelolaWL
        usecase "Mengelola Menu\ndan Addon" as UC_MenuAddon
        usecase "Mengelola Meja\ndan QR Code" as UC_MejaQR
        usecase "Mengelola User\ndan Hak Akses" as UC_UserAkses
        usecase "Mengelola\nData Referensi" as UC_Referensi
    }
}

' Relasi aktor pelanggan
Pelanggan --> UC_PesanQR
Pelanggan --> UC_Status
Pelanggan --> UC_Ubah
Pelanggan --> UC_Batal
Pelanggan --> UC_Booking

' Relasi aktor admin
Admin --> UC_Login
Admin --> UC_Dashboard
Admin --> UC_KelolaPesanan
Admin --> UC_KelolaWL
Admin --> UC_MenuAddon
Admin --> UC_MejaQR
Admin --> UC_UserAkses
Admin --> UC_Referensi

' Relasi aktor kasir
Kasir --> UC_Login
Kasir --> UC_KelolaPesanan
Kasir --> UC_Pembayaran
Kasir --> UC_KelolaWL

' Relasi aktor koki
Koki --> UC_Login
Koki --> UC_Kitchen
Koki --> UC_MenuAddon

' Relasi include
UC_PesanQR ..> UC_Status : <<include>>
UC_Pembayaran ..> UC_DetailPesanan : <<include>>

' Relasi extend
UC_Ubah ..> UC_Status : <<extend>>
UC_Batal ..> UC_Status : <<extend>>
UC_Booking ..> UC_PesanQR : <<extend>>

@enduml
```
