# MyStores — Aplikasi Pengurusan E-Dagang & Runcit Pelbagai Pasaran

[English](README.md) | **Bahasa Melayu**

> **Platform Pengurusan Kedai Pelbagai Pasaran (Self-Hosted)**  
> Urus produk, stok, harga, pesanan, pemenuhan pesanan (*fulfillment*), senarai pungutan (*pick list*), dan promosi merentasi kedai **Shopee** dan **Lazada** anda daripada satu antaramuka web berpusat.

---

## 🌟 Ciri-Ciri Utama

- **Integrasi Pelbagai Pasaran** — Sambungkan pelbagai akaun penjual Shopee dan Lazada melalui OAuth 2.0 dengan pembaharuan token (*token refresh*) automatik.
- **Penyegerakan Inventori & Stok Berpusat** — Kemas kini stok dan harga berasaskan pengelompokan SKU merentasi pelbagai kedai menggunakan pemprosesan baris gilir latar belakang (*background queue*).
- **Pengurusan & Pemenuhan Pesanan Berpusat** — Pengurusan pesanan seragam, aliran kerja pembatalan, tempahan penghantaran (*shipment booking*), dan penjanaan label penghantaran PDF secara pukal (`dompdf`).
- **Senarai Pungutan Gudang (*Pick Lists*)** — Senarai pungutan harian lengkap dengan pengesahan foto, ruangan ulasan pasukan, dan penjejakan status.
- **Tugasan Latar Belakang Automatik** — Penjadual terbina (*built-in scheduler*) untuk semakan pesanan berjadual (*order polling*), putaran token, dan pematuhan privasi data pelanggan.
- **Pemasang Web Bersendirian (*Self-Hosted Web Installer*)** — Wizard 4 langkah mudah untuk semakan keperluan pelayan PHP, konfigurasi pangkalan data, dan persediaan akaun pentadbir.

---

## 📸 Tangkap Layar

### Papan Pemuka (*Dashboard*)
Gambaran keseluruhan jualan, kedai yang disambungkan, pesanan tertangguh, dan amaran stok rendah secara sepintas lalu.

![Dashboard](Docs/1.jpg)

### Senarai Pungutan Hari Ini (*Today's Pick List*)
Senarai semak cetakan interaktif — kutip barang dengan foto, tambah ulasan, kemudian cetak.

![Pick List](Docs/2.jpg)

### Stok & Harga (*Stock & Price*)
Tarik maklumat pasaran atau tolak kemas kini stok tempatan ke semua kedai yang disambungkan.

![Stock & Price](Docs/3.jpg)

---

## 📦 Pakej Keluaran (*Release Package*)

Arkib keluaran sedia-guna untuk persekitaran pengeluaran (*production*) disediakan dalam direktori [`release_artifacts/`](release_artifacts/):

- **Muat Turun:** [`release_artifacts/mystores-v1.1.1.zip`](release_artifacts/mystores-v1.1.1.zip)

Kandungan arkib keluaran merangkumi:
1. `web/` — Bahagian belakang (*backend*) API Laravel 12 yang telah diprapasang + aplikasi satu halaman (*SPA*) React 19 terkompilasi, sedia dimuat naik ke mana-mana *root* cPanel atau VPS.
2. `documentation.html` — Manual panduan persediaan dan operasi interaktif langkah demi langkah.
3. `README.txt` — Rujukan ringkas orientasi sistem.

---

## 📋 Keperluan Awal (*Prerequisites*)

Sebelum memulakan, pastikan anda telah menyediakan keperluan berikut:

1. **Akaun Shopee Open Platform** (diperlukan untuk menyambungkan kedai Shopee) — daftar secara percuma di [https://open.shopee.com/](https://open.shopee.com/)
2. **Akaun Lazada Open Platform** (diperlukan untuk menyambungkan kedai Lazada) — daftar secara percuma di [https://open.lazada.com/](https://open.lazada.com/)
3. **Pengehosan Web + Domain** berserta pangkalan data MySQL — untuk persekitaran cPanel atau VPS seperti yang diterangkan di bawah

*(Hanya satu akaun platform pasaran diperlukan untuk bermula — anda boleh menyambungkan Shopee, Lazada, atau kedua-duanya).*

---

## 🚀 Panduan Mula Pantas (5 Minit)

1. **Muat Naik Fail:**  
   Ekstrak dan muat naik kandungan direktori `web/` ke direktori utama pengehosan web anda (contohnya `public_html` atau direktori *document root* domain).

2. **Cipta Pangkalan Data:**  
   Cipta pangkalan data MySQL kosong beserta pengguna pangkalan data dengan kebenaran penuh (*full privileges*) di panel kawalan pengehosan anda.

3. **Jalankan Wizard Pemasangan Web:**  
   Buka pelayar web anda dan layari:
   ```
   https://domain-anda.com/install
   ```
   Ikuti wizard pemasangan 4-langkah di skrin:
   - **Langkah 1: Semakan Pra-Pemasangan (*Preflight Check*)** (memeriksa versi PHP dan sambungan/modul yang diperlukan)
   - **Langkah 2: Sambungan Pangkalan Data (*Database Connection*)** (menguji dan menyimpan sambungan MySQL)
   - **Langkah 3: Persediaan Pentadbir & Kedai (*Admin & Store Setup*)** (mencipta kedai dan akaun pentadbir utama)
   - **Langkah 4: Pemulaan (*Initialization*)** (melaksanakan migrasi pangkalan data dan mengunci pemasang)

4. **Konfigurasi Tugas Latar Belakang Cron (*Cron Jobs*):**  
   Tambah dua entri cron berikut di panel kawalan pengehosan anda (cPanel Cron Jobs atau `crontab -e`):
   ```cron
   # Pekerja baris gilir (memproses tolakan stok pasaran secara tak segerak):
   * * * * * /usr/local/bin/php /path-to-mystore/artisan queue:work --stop-when-empty --timeout=600 >> /path-to-mystore/storage/logs/queue.log 2>&1

   # Tugasan berjadual (semakan pesanan Shopee & Lazada, putaran token):
   * * * * * /usr/local/bin/php /path-to-mystore/artisan schedule:run >> /path-to-mystore/storage/logs/scheduler.log 2>&1
   ```
   *(Nota: Sila sesuaikan laluan PHP dan direktori folder mengikut pelayan anda).*

---

## 💻 Keperluan Pelayan (*Server Requirements*)

- **PHP:** `^8.2`
- **Sambungan / Modul PHP:** `pdo`, `pdo_mysql`, `openssl`, `mbstring`, `curl`, `gd`, `zip`, `fileinfo`
- **Pangkalan Data:** MySQL 8.0+ atau MariaDB 10.4+
- **Pelayan Web:** Apache (dengan fungsi `mod_rewrite` diaktifkan) atau Nginx

---

## 📖 Dokumentasi

Untuk arahan pemasangan terperinci, pengoptimuman pelayan, dan konfigurasi API pasaran:
- Buka [`Docs/documentation.html`](Docs/documentation.html) dalam pelayar web anda.

---

## 🛠️ Servis Pemasangan (*Installation Service*)

Perlukan bantuan profesional untuk pemasangan?  
Jika anda memerlukan bantuan untuk memasang MyStores pada pengehosan cPanel atau VPS anda, persediaan pangkalan data, konfigurasi tugas cron (*cron jobs*), atau penyambungan API pasaran (*Shopee / Lazada*), servis pemasangan disediakan di Shopee:

👉 **[Tempah Servis Pemasangan di Shopee](https://shopee.com.my/product/61084342/47869625790/)**

---

## 📄 Lesen

Perisian ini disediakan di bawah terma yang diterangkan dalam dokumentasi dan pakej keluaran.

