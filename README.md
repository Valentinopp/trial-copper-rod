<div align="center">
  <img src="backend/public/irrisnext.png" alt="Logo irris.next" width="72">
  <h1>irris.next</h1>
  <p><strong>Satu Sistem, Seluruh Operasi.</strong></p>
  <p>Sistem operasional terintegrasi untuk PT Indonesia Royal Resources.</p>
</div>

---

## Visi

**irris.next** dikembangkan untuk menghubungkan aktivitas **Produksi, PPIC, Marketing, dan Purchasing** dalam satu sistem yang sederhana, konsisten, dan mudah digunakan.

Tujuannya adalah menyediakan alur informasi yang saling terhubung antardepartemen, mengurangi pencatatan berulang, dan membantu tim menelusuri data operasional dari sumber yang sama. Pengembangan dimulai dari pencatatan produksi Copper Rod, kemudian diperluas secara bertahap sesuai kebutuhan setiap modul.

## Modul dan status pengembangan

| Modul | Cakupan | Status saat ini |
| --- | --- | --- |
| **Produksi** | Pencatatan dan monitoring operasional produksi | Fitur Copper Rod tersedia: Papan Digital, Crystallizer, Copper Putus, dan Rekap Output |
| **PPIC** | Perencanaan dan pengendalian produksi serta persediaan | IC memeriksa kebutuhan bahan penjualan, mengajukan kekurangan, dan menyerahkan penjualan yang siap ke antrean Production Plan |
| **Marketing** | Katalog produk, bahan, dan penjualan | Katalog Copper Rod dengan BoM; pencatatan penjualan final dari produk katalog dan tanggal harus dikirim |
| **Purchasing** | Pemrosesan pengadaan | Tabel pengajuan IC, persetujuan menjadi pesanan pembelian, dan jadwal kedatangan |

Integrasi yang tersedia saat ini mencakup pengajuan pembelian dari IC, pemrosesan oleh Purchasing, dan penerimaan kembali oleh IC.

### Alur pengadaan dan stok

1. Buka **PPIC → Inventory Control → Pengajuan Pembelian**. Pilih produk/material dari master barang; UoM otomatis ditampilkan di sisi kanan kolom Qty. Isi qty dan tanggal barang harus datang. Barang baru didaftarkan melalui **Marketing → Katalog Material** terlebih dahulu.
2. Di **Purchasing**, pilih **Proses pesanan** pada pengajuan. Isi tanggal dibeli dan estimasi datang, lalu pilih **Setujui & buat pesanan**. Tindakan ini merupakan persetujuan Purchasing.
3. Di **Inventory Control → Barang Masuk**, pilih **Terima barang** pada pesanan. Isi qty aktual dan tanggal penerimaan. Penerimaan sebagian diperbolehkan sampai seluruh jumlah pesanan diterima.
4. Buka **Stok** untuk melihat saldo dari penerimaan tercatat. Produk dengan nama dan satuan yang sama digabung tanpa membedakan huruf besar/kecil. Satuan berbeda tetap terpisah.

Pengajuan dan persetujuan belum menambah stok. Penerimaan yang melebihi sisa pesanan ditolak, dan pengiriman ulang request penerimaan yang sama tidak menggandakan stok. Barang Keluar masih berupa halaman awal; saldo saat ini hanya mencakup barang masuk.

Untuk checkout yang sudah terpasang, jalankan `php artisan migrate` dari folder `backend` sebelum membuka fitur ini, kemudian `npm.cmd run build` dari folder `frontend`. Migration menambah tabel produk, pengajuan, pesanan, dan penerimaan.

### Fitur Copper Rod

Produk dikelola melalui **Master Data → Barang**, kategori **Produk**. Komposisi material dikelola melalui **Master BoM**. Modul Marketing menggunakan data tersebut pada **Penjualan**.

BoM mencatat resep yang dimasukkan pengguna; komposisi belum menghitung kemurnian secara otomatis. Menyimpan katalog tidak mengubah stok. Produk dan BoM dapat dibuka kembali melalui **Lihat / ubah BoM**. Jalankan `php artisan migrate` dari `backend` setelah mengambil perubahan katalog ini.

### Penjualan Marketing

Buka **Marketing → Penjualan** (`/marketing/sales`), lalu pilih **Catat penjualan**. Modal memuat nama pelanggan, produk dari katalog, qty dalam KG, tanggal penjualan, tanggal barang harus dikirim, dan catatan opsional. Setiap catatan memuat satu produk dan langsung tersimpan sebagai penjualan final dengan nomor `SO-...`.

Nama, diameter, dan kemurnian produk disimpan sesuai katalog saat transaksi dicatat agar perubahan katalog tidak mengubah data penjualan lama. Pencatatan ini belum memotong stok atau mencatat pengiriman aktual. Jalankan migration untuk membuat tabel penjualan sebelum membuka halaman ini.

### Pemeriksaan IC dan penyerahan ke Production Plan

Titik merah pada menu **Inventory Control** menandai pekerjaan yang belum selesai. Tombol **Kesiapan Penjualan** ditandai selama ada penjualan yang belum diserahkan ke Production Plan; **Barang Masuk** ditandai selama ada pesanan dengan sisa penerimaan, termasuk penerimaan sebagian. Penanda diperbarui setiap 30 detik serta setelah penerimaan atau penyerahan dari IC, tanpa me-refresh form.

1. Buka **Inventory Control → Kesiapan Penjualan**, lalu pilih **Cek kebutuhan** pada penjualan final.
2. Kebutuhan dihitung dari qty bahan BoM × qty penjualan ÷ jumlah hasil acuan, dengan pembulatan ke atas hingga 3 desimal. Acuan kebutuhan disimpan pada pemeriksaan pertama. BoM yang belum lengkap atau diameter/kemurnian katalog yang berbeda dari spesifikasi penjualan akan memblokir pemeriksaan.
3. Jika stok kurang, isi tanggal kebutuhan bahan lalu pilih **Ajukan pembelian kekurangan**. Setiap pengajuan terhubung ke nomor penjualan; pengajuan berjalan diperhitungkan agar kekurangan yang sama tidak diajukan berulang.
4. Setelah pembelian diproses dan penerimaan dicatat, perbarui stok pada pengecekan IC. Pengajuan maupun pesanan yang belum diterima tidak dianggap sebagai stok.
5. Jika seluruh bahan cukup, pilih **Serahkan ke Production Plan**. Sistem memeriksa ulang ketersediaan, mencadangkan bahan, dan menambahkan satu penyerahan ke antrean **Production Plan**.

Stok tersedia adalah stok fisik diterima dikurangi cadangan penjualan lain. Cadangan belum merupakan pemakaian fisik; pelaksanaan produksi, pengeluaran bahan, pelepasan cadangan, dan penjadwalan mesin belum termasuk tahap ini. Jalankan `php artisan migrate` setelah mengambil pembaruan ini.

- **Papan Digital** — pencatatan produksi dan penelusuran riwayat LOT.
- **Crystallizer** — monitoring lifetime dan rekap penggunaan crystallizer.
- **Copper Putus** — pencatatan kejadian dan kategori copper putus.
- **Rekap Output** — ringkasan hasil produksi per shift.
- **Ekspor laporan** — keluaran Excel dan PNG.
- **Sino** — asisten untuk menanyakan data WIP Board.

## Prinsip tampilan dan navigasi

Tampilan mengutamakan latar terang, warna netral, garis tipis, dan hierarki informasi yang jelas. Warna digunakan seperlunya untuk membantu membaca status dan tindakan.

Sidebar memuat navigasi tingkat modul. Pada Produksi, pengguna memilih **Copper Rod** dan langsung masuk ke **Papan Digital**. Perpindahan ke **Crystallizer**, **Copper Putus**, dan **Rekap Output** menggunakan tombol di header halaman, sehingga sidebar tetap ringkas.

| Kebutuhan | File yang diedit |
| --- | --- |
| Menu utama sidebar | `frontend/resources/views/components/layouts/sidebar.blade.php` |
| Tombol navigasi Copper Rod | `frontend/resources/views/modules/produksi/components/copper-rod-navigation.blade.php` |
| Layout aplikasi bersama | `frontend/resources/views/components/layouts/app.blade.php` |
| Tema visual bersama | `frontend/resources/css/theme.css` |
| Gaya dasar dan komponen | `frontend/resources/css/base.css` |

## Arsitektur

Proyek memisahkan backend dan sumber frontend, dengan pembagian modul pada keduanya. Halaman dirender oleh **Laravel dan Livewire**; folder frontend berisi template Blade, CSS, JavaScript, dan konfigurasi Vite.

```text
irrisnext-project/
├── backend/
│   ├── app/
│   │   ├── Modules/
│   │   │   ├── Produksi/       # Livewire, model, service, enum, dan routes.php
│   │   │   ├── Ppic/           # Komponen PPIC dan routes.php
│   │   │   ├── Marketing/      # Katalog, penjualan, dan routes.php
│   │   │   └── Purchasing/     # Pengadaan dan routes.php
│   │   ├── Models/            # Model bersama
│   │   └── Providers/
│   ├── config/
│   ├── database/              # Migration dan seeder PostgreSQL
│   ├── public/                # Document root dan hasil build aset
│   ├── routes/web.php         # Memuat route setiap modul
│   ├── tests/
│   └── .env.example
├── frontend/
│   ├── resources/
│   │   ├── views/
│   │   │   ├── components/layouts/
│   │   │   └── modules/{produksi,ppic,marketing,purchasing}/
│   │   ├── css/               # Entry app.css, base.css, theme.css, dan modules/
│   │   └── js/                # Entry app.js dan modules/
│   ├── package.json
│   └── vite.config.js
└── setup.ps1
```

**Teknologi:** Laravel 12, Livewire 3, Alpine.js bawaan Livewire, PostgreSQL, Vite 6, dan SheetJS untuk ekspor Excel.

## Menjalankan proyek

Database aplikasi adalah **PostgreSQL**. File `.env`, dependency PHP/JavaScript, dan hasil build tidak ikut di Git; semuanya disiapkan di komputer masing-masing setelah clone. Ikuti langkah di bawah sebelum membuka aplikasi.

Panduan ini menggunakan **Windows PowerShell**. Untuk Linux/macOS, gunakan `npm` sebagai pengganti `npm.cmd` dan ikuti instalasi manual.

### 1. Pasang kebutuhan sistem

| Kebutuhan | Versi / ketentuan | Instalasi |
| --- | --- | --- |
| Git | Tersedia melalui terminal | [Git](https://git-scm.com/downloads) |
| PHP | **Minimal 8.4.1** untuk dependency di `backend/composer.lock` saat ini | [PHP](https://www.php.net/downloads.php) atau PHP dari Laravel Herd |
| Composer | Composer 2, menggunakan PHP CLI yang sesuai | [Composer](https://getcomposer.org/download/) |
| PostgreSQL | Gunakan PostgreSQL 18 untuk menyamakan lingkungan pengembangan; server harus berjalan | [Installer PostgreSQL Windows](https://www.postgresql.org/download/windows/) |
| Node.js dan npm | Node.js **22 atau 24 LTS** kompatibel dengan Vite 6 yang digunakan proyek | [Node.js](https://nodejs.org/en/download) |
| Laravel Herd | Opsional, jika ingin menggunakan domain lokal `.test` | [Laravel Herd](https://herd.laravel.com/windows) |

Meskipun `composer.json` mencantumkan PHP `^8.2`, beberapa paket Symfony pada lockfile saat ini memerlukan **PHP >= 8.4.1**. Instalasi dari lockfile harus memenuhi kebutuhan tersebut; gunakan `composer install` untuk mempertahankan versi dependency proyek.

Aktifkan **PDO** dan **`pdo_pgsql`** pada PHP yang menjalankan aplikasi. [PDO_PGSQL](https://www.php.net/manual/en/ref.pdo-pgsql.php) adalah driver PHP untuk PostgreSQL. Dependency juga menggunakan ekstensi seperti `ctype`, `dom`, `fileinfo`, `mbstring`, `openssl`, `xml`, dan `xmlwriter`; pemeriksaan lengkap dilakukan oleh Composer.

Periksa dari terminal baru setelah memasang alat:

```powershell
php -v
php --ini
php -m
composer --version
node --version
npm.cmd --version
git --version
```

Untuk mencari PHP yang dipakai terminal dan mengecek driver PostgreSQL:

```powershell
Get-Command php
php -m | Select-String 'PDO|pdo_pgsql'
```

Jika `pdo_pgsql` belum tampil, aktifkan `extension=pdo_pgsql` di `php.ini` yang ditunjukkan oleh `php --ini`, lalu buka ulang terminal dan restart server PHP/Herd. Pastikan versi PHP situs Herd juga memenuhi kebutuhan di atas.

### 2. Clone repository dan buat konfigurasi lokal

```powershell
git clone https://github.com/hafizarrafi/irrisnext-project.git
cd irrisnext-project
Copy-Item backend/.env.example backend/.env
```

Perintah copy ditujukan untuk clone baru. Jika `.env` sudah ada, pertahankan konfigurasi lokal tersebut.

### 3. Buat database PostgreSQL

Saat memasang PostgreSQL, catat port, username, dan password yang dipilih. Nilai ini mengikuti instalasi pada komputer sendiri. PostgreSQL dan pgAdmin adalah aplikasi terpisah dari Laravel; menjalankan Herd saja tidak membuat database PostgreSQL.

Melalui **pgAdmin**:

1. Hubungkan pgAdmin ke server PostgreSQL lokal.
2. Klik kanan **Databases → Create → Database**.
3. Isi nama **`irrisnext`** dan pilih owner sesuai akun yang akan digunakan, lalu simpan.

Atau melalui terminal, jika `psql` sudah tersedia di PATH:

```powershell
psql -h 127.0.0.1 -p 5432 -U postgres -d postgres
```

Masukkan password PostgreSQL saat diminta, kemudian jalankan di dalam `psql`:

```sql
CREATE DATABASE irrisnext;
\conninfo
\l
\q
```

Jika database `irrisnext` sudah ada, gunakan database tersebut dan lewati `CREATE DATABASE`. Jika `psql` tidak dikenali, gunakan path instalasinya, misalnya:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 5432 -U postgres -d postgres
```

Sesuaikan folder versi, port, dan user jika instalasi berbeda.

### 4. Isi `backend/.env`

Edit bagian koneksi database:

```dotenv
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=irrisnext
DB_USERNAME=postgres
DB_PASSWORD="isi_password_postgresql_lokal"
```

Ganti contoh password dengan password akun PostgreSQL yang sebenarnya. Gunakan tanda kutip bila password mengandung spasi atau karakter `#`.

| Variabel | Isi |
| --- | --- |
| `DB_HOST` | `127.0.0.1` jika PostgreSQL berjalan di komputer yang sama dengan Laravel |
| `DB_PORT` | Port server PostgreSQL; contoh ini memakai `5432` |
| `DB_DATABASE` | Nama database yang sudah dibuat: `irrisnext` |
| `DB_USERNAME` | Akun PostgreSQL yang memiliki akses ke database |
| `DB_PASSWORD` | Password akun tersebut; tidak otomatis sama dengan nama user |

Untuk mengecek koneksi sebelum menjalankan Laravel:

```powershell
psql -h 127.0.0.1 -p 5432 -U postgres -d irrisnext
```

Jika berhasil, `\conninfo` menampilkan koneksi aktif dan `\q` keluar dari psql. Di pgAdmin, **server → Properties → Connection** menampilkan pengaturan koneksi server.

### 5. Instal dependency dan jalankan migration

Setelah database dibuat dan `.env` sesuai, jalankan dari **root proyek**:

```powershell
powershell -ExecutionPolicy Bypass -File .\setup.ps1
```

Script akan:

- Memeriksa PHP, driver `pdo_pgsql`, dan versi Node.js.
- Menjalankan `composer install` dan `composer check-platform-reqs`.
- Membersihkan cache konfigurasi dan memeriksa koneksi PostgreSQL.
- Membuat `APP_KEY` hanya jika masih kosong; key yang sudah ada dipertahankan.
- Menjalankan migration untuk membuat atau memperbarui tabel PostgreSQL.
- Menjalankan `npm.cmd ci` dan `npm.cmd run build` di folder frontend.

Script tidak memasang PostgreSQL atau membuat database secara otomatis. Jika `.env` belum ada, script membuatnya dari contoh lalu berhenti agar koneksinya diisi terlebih dahulu.

Alternatif **instalasi manual pertama** setelah menyelesaikan langkah 2–4:

```powershell
cd backend
composer install
composer check-platform-reqs
php artisan config:clear
php artisan key:generate
php artisan migrate
php artisan migrate:status
cd ../frontend
npm.cmd ci
npm.cmd run build
```

`key:generate` pada contoh manual hanya untuk instalasi pertama dengan `APP_KEY` kosong. Untuk memperbarui proyek yang sudah digunakan, ikuti bagian pembaruan di bawah.

### 6. Jalankan aplikasi

Dari root proyek:

```powershell
cd backend
php artisan serve
```

Buka **http://127.0.0.1:8000** dan biarkan terminal server tetap berjalan. `artisan` ada di `backend`, sedangkan `package.json` ada di `frontend`.

Untuk pembaruan CSS/JavaScript otomatis, buka terminal kedua dari root proyek:

```powershell
cd frontend
npm.cmd run dev
```

Vite hanya melayani aset pengembangan. Server Laravel atau Herd tetap perlu berjalan. Jika tidak memakai Vite dev server, jalankan `npm.cmd run build` setelah mengubah CSS atau JavaScript.

### Menggunakan Laravel Herd

Konfigurasikan situs Herd agar document root-nya menunjuk ke **`backend/public`**, lalu ubah URL di `backend/.env` sesuai nama situs:

```dotenv
APP_URL=http://irrisnext-project.test
```

Konfigurasi PostgreSQL tetap seperti langkah 4. Dari folder `backend`, jalankan:

```powershell
php artisan config:clear
```

Dengan Herd, `php artisan serve` tidak diperlukan. PostgreSQL tetap harus berjalan secara terpisah. Untuk pemeriksaan awal yang tidak bergantung pada konfigurasi domain Herd, gunakan `php artisan serve`.

### Memperbarui proyek setelah pull

Dari root proyek, dengan `.env` lokal yang sudah dikonfigurasi:

```powershell
git pull
cd backend
composer install
php artisan config:clear
php artisan migrate
cd ../frontend
npm.cmd ci
npm.cmd run build
```

Pertahankan `.env` dan `APP_KEY` yang sudah digunakan. Migration memperbarui struktur tabel; tidak perlu menghapus atau membuat ulang database. Data operasional tidak disediakan oleh clone repository.

### Login dan akun untuk mencoba

Semua modul memerlukan login melalui `/login`. Setelah menjalankan migration, buat akun dummy pada environment `local` dari folder `backend`:

```powershell
php artisan demo:users
```

Username akun dummy: `produksi`, `asman`, `manager`, `ppic`, `marketing`, `purchasing`, dan `it`. Password awal masing-masing sama dengan username. Perintah ini mempertahankan password akun yang sudah ada. Untuk menyamakan ulang username dan password akun dummy lama, gunakan `php artisan demo:users --reset-passwords`.

Akun `it` memiliki role IT Super Admin dan akses CRUD Master Data melalui tombol **Master Data**. Tombol **Menu Utama** kembali ke modul operasional. Role lain tidak dapat mengakses Master Data. Rincian profil, hak akses, dan akun dummy tersedia di [panduan akses pengguna](docs/user-access.md).

## Halaman yang tersedia

| Halaman | URL | Nama route |
| --- | --- | --- |
| Papan Digital | `/` | `board` |
| Crystallizer | `/crystallizer` | `crystallizer` |
| Copper Putus | `/copper-break` | `copper-break` |
| Rekap Output | `/output` | `output` |
| Dashboard PPIC | `/ppic` | `ppic.dashboard` |
| Production Plan | `/ppic/production-plan` | `ppic.production-plan` |
| Inventory Control | `/ppic/inventory-control` | `ppic.inventory-control` |
| Marketing (halaman awal) | `/marketing` | `marketing.index` |
| Penjualan (Marketing) | `/marketing/sales` | `marketing.sales` |
| Purchasing | `/purchasing` | `purchasing.dashboard` |

## Panduan pengembangan

Referensi input Papan Digital dan detail Copper Putus dikelola melalui satu dropdown **Produksi Copper Rod** di menu Master Data. Lihat [panduan master produksi](docs/production-master-data.md) untuk daftar master, integrasi form, dan perilaku riwayat transaksi.

Penyerahan pesanan dari IC dapat dijadwalkan per tanggal dan jumlah melalui **PPIC → Production Plan**. Satu pesanan dapat dibagi ke beberapa hari; lihat [panduan Production Planning](docs/production-planning.md) untuk langkah penjadwalan, pengubahan, dan pembatalan.

1. Tempatkan logika backend di `backend/app/Modules/<Modul>` dan daftarkan halaman melalui `routes.php` milik modul.
2. Tempatkan template di `frontend/resources/views/modules/<modul>`, dengan CSS dan JavaScript khusus modul di folder `modules` masing-masing.
3. Gunakan layout dan tema bersama agar tampilan antarmodul konsisten.
4. Tambahkan tautan utama di file sidebar. Navigasi internal Copper Rod dikelola melalui komponen tombol header.
5. Gunakan migration untuk perubahan skema database.

File `.env`, dependency, cache runtime, dan hasil build diabaikan oleh Git. Konfigurasi awal tersedia di `backend/.env.example`. Data PostgreSQL tersimpan pada server database masing-masing, sedangkan struktur tabel disediakan oleh migration.

### Verifikasi

**Aplikasi menggunakan PostgreSQL.** Pengujian otomatis saat ini dikonfigurasi di `backend/phpunit.xml` untuk memakai **SQLite in-memory** agar tidak menyentuh database operasional. Khusus menjalankan tes, aktifkan juga ekstensi PHP `pdo_sqlite`. Ini tidak mengubah `DB_CONNECTION=pgsql` pada `.env` aplikasi.

Dari root proyek:

```powershell
cd backend
php artisan test
php artisan route:list
cd ../frontend
npm.cmd run build
```

## Kendala instalasi yang sering muncul

| Pesan / gejala | Yang diperiksa |
| --- | --- |
| `php`, `composer`, atau `npm.cmd` tidak dikenali | Pasang alatnya, masukkan ke PATH, lalu buka terminal baru. Gunakan `Get-Command php` untuk memastikan PHP yang digunakan. |
| Composer meminta PHP `>= 8.4.1` | Pilih PHP CLI 8.4.1+ yang memenuhi lockfile. Samakan versi PHP situs Herd. |
| `could not find driver` pada koneksi `pgsql` | Aktifkan `pdo_pgsql` pada PHP CLI dan PHP web. Periksa `php --ini` dan `php -m`. |
| `connection refused` | PostgreSQL belum berjalan atau host/port salah. Uji dengan `psql -h 127.0.0.1 -p 5432 -U postgres -d irrisnext`. |
| `password authentication failed` | Periksa akun dan password PostgreSQL komputer tersebut di `backend/.env`. |
| `database "irrisnext" does not exist` | Buat database di PostgreSQL terlebih dahulu; migration hanya membuat tabel di database yang sudah ada. |
| `relation ... does not exist` / `Undefined table` | Jalankan `php artisan config:clear`, lalu `php artisan migrate` dari `backend`. Periksa `php artisan migrate:status`. |
| `Could not open input file: artisan` | Jalankan perintah Artisan dari folder `backend`. |
| npm tidak menemukan `package.json` | Jalankan perintah npm dari folder `frontend`. |
| `vendor/autoload.php` tidak ditemukan | Jalankan `composer install` dari folder `backend`. |
| `No application encryption key` | Pada instalasi baru dengan key kosong, jalankan `php artisan key:generate` dari `backend`. |
| `Vite manifest not found` | Jalankan `npm.cmd ci` dan `npm.cmd run build` dari `frontend`. |
| Pengaturan `.env` baru belum terbaca | Jalankan `php artisan config:clear` dari `backend`, lalu restart server aplikasi. |
| `could not find driver` saat `php artisan test` | Tes memakai SQLite in-memory; aktifkan `pdo_sqlite` pada PHP CLI. Database aplikasi tetap PostgreSQL. |

## Jika CSS atau JavaScript tidak termuat

Jika Vite berhenti tidak normal, file `backend/public/hot` dapat tertinggal dan membuat Laravel tetap meminta aset dari server Vite yang sudah mati.

Untuk kembali memakai hasil build, pastikan Vite sudah berhenti, lalu jalankan dari root proyek:

```powershell
Remove-Item -LiteralPath backend/public/hot -ErrorAction SilentlyContinue
cd frontend
npm.cmd run build
```

Refresh browser dengan **Ctrl + Shift + R**. Untuk kembali menggunakan pembaruan otomatis, jalankan `npm.cmd run dev`.
