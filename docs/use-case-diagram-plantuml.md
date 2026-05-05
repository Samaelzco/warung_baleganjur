# Use Case Diagram UML Warung Baleganjur

Dokumen ini berisi use case diagram versi ringkas untuk Sistem Informasi Pemesanan Warung Baleganjur Berbasis QR Code dan Kitchen Display.

Diagram dibuat dalam satu sistem boundary dengan 15 use case utama:
- 5 use case Customer
- 10 use case Internal

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

actor Customer
actor Admin
actor Koki
actor Kasir

rectangle "Sistem Informasi Pemesanan Warung Baleganjur\nBerbasis QR Code dan Kitchen Display" {
    package "Fitur Customer" {
        usecase UC01 as "Melakukan Pemesanan\nMelalui QR Meja"
        usecase UC02 as "Melihat Status\nPesanan"
        usecase UC03 as "Mengubah\nPesanan"
        usecase UC04 as "Membatalkan\nPesanan"
        usecase UC05 as "Membuat Waiting List /\nBooking Meja"
    }

    package "Fitur Internal" {
        usecase UC06 as "Melakukan\nLogin"
        usecase UC07 as "Melakukan\nLogout"
        usecase UC08 as "Melihat\nDashboard"
        usecase UC09 as "Mengelola\nPesanan"
        usecase UC10 as "Mengelola\nKitchen Display"
        usecase UC11 as "Mengelola\nPembayaran"
        usecase UC12 as "Mengelola\nWaiting List"
        usecase UC13 as "Mengelola\nMaster Data"
        usecase UC14 as "Mengelola Meja\ndan QR Code"
        usecase UC15 as "Mengelola User\ndan Hak Akses"
    }
}

Customer --> UC01
Customer --> UC02
Customer --> UC03
Customer --> UC04
Customer --> UC05

Admin --> UC06
Admin --> UC07
Admin --> UC08
Admin --> UC09
Admin --> UC12
Admin --> UC13
Admin --> UC14
Admin --> UC15

Koki --> UC06
Koki --> UC07
Koki --> UC10
Koki --> UC13

Kasir --> UC06
Kasir --> UC07
Kasir --> UC09
Kasir --> UC11
Kasir --> UC12

@enduml
```

