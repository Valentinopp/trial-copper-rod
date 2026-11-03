# Login dan akses pengguna

Login menggunakan **username** dan **password** di `/login`. Akun baru tidak dapat didaftarkan sendiri.

Setelah login, pengguna masuk ke modul paling atas pada sidebar sesuai aksesnya: PPIC ke Production Plan, Marketing ke Penjualan, Purchasing ke modul Purchasing, dan Operator Produksi/Asisten Manager/Manager/IT Super Admin ke Copper Rod. Halaman yang sempat diminta sebelum login tidak mengganti tujuan awal ini.

| Data | Penggunaan |
| --- | --- |
| Username | Identitas login unik; disimpan dengan huruf kecil |
| Nama lengkap | Identitas pengguna di aplikasi |
| NIK (`employee_number`) | Identitas karyawan unik; boleh kosong |
| Jabatan | Posisi karyawan, misalnya Operator atau Manager |
| Departemen | Unit kerja karyawan |
| Role | Aturan akses lama untuk modul yang belum diintegrasikan; Marketing dapat mengikuti Hak Akses jabatan–divisi |
| Email | Kontak opsional; bukan isian login |
| Status aktif | Akun nonaktif tidak dapat mengakses ERP |
| Login terakhir | Waktu login berhasil yang terakhir |
| Password | Disimpan sebagai hash, tidak sebagai teks biasa |

| Role | Akses modul | Sino |
| --- | --- | --- |
| Operator Produksi | Produksi saja | Tidak tersedia |
| Asisten Manager | Semua modul operasional, tanpa Master Data | Tersedia |
| Manager | Semua modul operasional, tanpa Master Data | Tersedia |
| Staff PPIC | PPIC saja | Bidang dan data PPIC |
| Staff Marketing | Marketing saja | Bidang dan data Marketing |
| Staff Purchasing | Purchasing saja | Bidang dan data Purchasing |
| IT Super Admin | Seluruh modul dan CRUD Master Data | Tersedia |

Pembatasan berlaku untuk halaman serta permintaan pembaruan Livewire. Menu yang tidak berhak diakses disembunyikan. Tombol Keluar mengakhiri sesi.

Master Data hanya dapat diakses IT Super Admin. Tombol **Master Data** di kanan atas membuka tampilan dengan sidebar khusus Master Barang, Kategori, UoM, Supplier, dan Customer. Tombol **Menu Utama** membuka modul operasional. Role lain ditolak walaupun menggunakan URL langsung atau mengirim ulang permintaan dari halaman yang sebelumnya terbuka. Pemeriksaan izin juga berlaku pada service penyimpanan dan penghapusan.

IT Super Admin dapat menambah, melihat, mengubah, menonaktifkan, dan menghapus master data. Penghapusan memerlukan konfirmasi. Kategori, UoM, dan barang yang masih dipakai tidak dapat dihapus. Menghapus supplier melepas hubungan barangnya tanpa menghapus barang tersebut.

Sino mengambil cakupan dari role pengguna yang sedang login, bukan dari pertanyaan atau parameter browser. Konteks staff hanya memuat data yang tersedia di modulnya. Data bersama tetap mengikuti fungsi modul: PPIC mendapat kesiapan penjualan untuk produksi dan pengajuan barang, Marketing mendapat master material untuk BoM tanpa jumlah stok, Purchasing mendapat pengajuan pembelian dan penerimaan pesanan tanpa penjualan pelanggan. Permintaan eksplisit untuk modul atau topik operasional lain ditolak sebelum data konteks dibuat. Instruksi model juga membatasi jawaban ke pekerjaan bidang yang diizinkan. Asisten Manager dan Manager mendapat konteks seluruh modul. Ketika role berubah, riwayat chat lama dikosongkan pada pembaruan berikutnya.

Untuk membuat tujuh akun dummy di environment `local` atau `testing`, jalankan dari folder `backend`:

```sh
php artisan migrate
php artisan demo:users
```

| Role | Username | Password dummy |
| --- | --- | --- |
| Operator Produksi | `produksi` | `produksi` |
| Asisten Manager | `asman` | `asman` |
| Manager | `manager` | `manager` |
| Staff PPIC | `ppic` | `ppic` |
| Staff Marketing | `marketing` | `marketing` |
| Staff Purchasing | `purchasing` | `purchasing` |
| IT Super Admin | `it` | `it` |

Menjalankan perintah lagi mempertahankan username dan password akun yang sudah ada. Untuk menerapkan username sederhana dan menyamakan password pada semua akun dummy lama, jalankan secara eksplisit:

```sh
php artisan demo:users --reset-passwords
```

Akun dummy dikenali dari NIK `DUMMY-001` sampai `DUMMY-007`. Reset mempertahankan ID akun, mengganti `operator.produksi` menjadi `produksi` dan `asisten.manager` menjadi `asman`, serta mengaktifkan akun dummy. Akun lain tidak diubah. Benturan username membatalkan seluruh transaksi. Password tetap disimpan sebagai hash. Seeder dummy tidak dijalankan otomatis oleh `DatabaseSeeder`.

## Hak Akses Marketing berdasarkan jabatan dan divisi

IT Super Admin mengatur checklist melalui **Master Data → Organisasi & Karyawan → Hak Akses**. Pilih kombinasi jabatan dan divisi, lalu aktifkan **Lihat penjualan** (`marketing.sales.view`) dan/atau **Catat penjualan** (`marketing.sales.create`). Catat penjualan otomatis menyertakan Lihat penjualan.

Izin disimpan pada `access_profiles.permissions`; daftar pilihan berasal dari `config/access_permissions.php`. Checklist kosong atau pengaturan/jabatan/divisi nonaktif menolak akses Marketing untuk kombinasi tersebut. Aturan ini berlaku pula untuk Manager dan Asisten Manager jika sudah memiliki pengaturan. IT Super Admin tetap memiliki akses penuh.

Selama transisi, akun tanpa pengaturan kombinasi masih mengikuti role lama untuk Marketing. Akun dengan pengaturan yang sudah ada wajib mengisi checklist; nilai awal kosong tidak memberikan izin. Modul selain Marketing tetap mengikuti aturan role sebelumnya.

Izin diperiksa pada route, setiap request Livewire, dan layanan penyimpanan. Sidebar dan tombol mengikuti izin yang sama. Perubahan izin serta penempatan karyawan diperiksa ulang pada aksi berikutnya. Akun Marketing yang tidak memiliki izin lihat diarahkan ke `/access-unavailable` setelah login agar tetap dapat keluar dari akun dan menghubungi administrator.

Sino untuk role Marketing membutuhkan izin Lihat penjualan. Konteks Marketing pada Sino ERP tidak disertakan jika izin lihat ditolak. Data penjualan yang digunakan alur PPIC tetap mengikuti akses modul PPIC.
## Hak Akses Inventory Control

Di **Hak Akses → PPIC — Inventory Control**, pilih **Gunakan checklist Inventory Control** untuk menerapkan aturan IC pada kombinasi jabatan–divisi. Memilih izin IC otomatis mengaktifkan opsi tersebut. Jika opsi aktif dan semua izin IC kosong, akses IC ditolak. Pengaturan lama tanpa opsi ini masih mengikuti role PPIC sebelumnya; Marketing tetap mengikuti checklist Marketing yang sudah ada.

Status penerapan modul disimpan di `access_profiles.configured_modules`. Menyimpan izin IC tidak menghapus izin Marketing. IT Super Admin tetap memiliki seluruh akses. Production Plan dan dashboard PPIC tetap dibatasi role lama, terpisah dari izin IC.

| Bagian | Permission |
| --- | --- |
| Lihat kesiapan penjualan | `ppic.ic.sales.view` |
| Periksa kebutuhan bahan | `ppic.ic.sales.check` |
| Ajukan kekurangan bahan | `ppic.ic.sales.request` |
| Serahkan ke Production Plan | `ppic.ic.sales.handover` |
| Lihat pengajuan pembelian | `ppic.ic.requests.view` |
| Buat pengajuan pembelian | `ppic.ic.requests.create` |
| Lihat barang masuk | `ppic.ic.receipts.view` |
| Catat penerimaan barang | `ppic.ic.receipts.create` |
| Lihat stok | `ppic.ic.stock.view` |

Izin tindakan otomatis menyertakan izin lihat pada bagian terkait. Pemeriksaan kebutuhan menyimpan pilihan BoM dan kebutuhan material; tombol **Lihat bahan** hanya membaca hasil yang sudah tersimpan. Pengguna pengaju kekurangan atau penyerah ke PP dapat memakai hasil pemeriksaan pengguna lain tanpa memiliki izin pemeriksaan sendiri.

Menu IC hanya muncul jika ada izin lihat IC. Tab awal menggunakan Pengajuan Pembelian jika diizinkan, lalu Kesiapan Penjualan, Barang Masuk, atau Stok sesuai izin. Query halaman hanya memuat data tab yang boleh dibuka. Indikator hanya menghitung penjualan/penerimaan yang boleh dilihat. Konteks Sino PPIC menyertakan stok, kesiapan, dan pengajuan sesuai izin IC; data Production Plan tetap mengikuti akses PP lama.

Pengujian manual: gunakan akun karyawan biasa dengan kombinasi yang cocok, aktifkan checklist IC, dan coba izin lihat per tab. Pastikan tombol mutasi tidak muncul tanpa izin tindakan, akses URL PP tidak ikut terbuka untuk akun khusus IC, dan pencabutan izin penerimaan saat form terbuka menolak penyimpanan. Checklist IC kosong dengan opsi penerapan aktif harus menolak IC, tanpa mengubah akses PP atau Marketing.

## Hak Akses Purchasing

Di **Hak Akses → Purchasing**, aktifkan **Gunakan checklist Purchasing** pada kombinasi jabatan–divisi yang ingin diatur. Memilih izin Purchasing otomatis mengaktifkan checklist. Jika aktif tetapi seluruh izin kosong, akses Purchasing ditolak. Profil lama yang belum mengaktifkannya tetap mengikuti role lama. IT Super Admin aktif tetap mendapat akses penuh.

| Permission | Fungsi |
| --- | --- |
| `purchasing.requests.view` | Melihat pengajuan IC yang belum memiliki PO |
| `purchasing.orders.view` | Melihat pesanan, jadwal, status, dan sisa penerimaan |
| `purchasing.orders.create` | Memproses pengajuan dan menyetujui sekaligus membuat PO |

Izin membuat PO otomatis menyertakan kedua izin lihat. Persetujuan dan pembuatan PO masih satu tindakan; tidak ada tahap approval tambahan. Penerimaan barang tetap menggunakan izin IC yang terpisah.

Menu muncul jika minimal satu izin lihat diberikan. Bagian halaman dan query data mengikuti izin. Pengguna lihat saja tidak mendapatkan tombol proses atau form. Akses URL, setiap request Livewire, dan service pembuatan PO memeriksa izin; pencabutan izin tetap berlaku pada form yang sudah terbuka. Saat halaman dimuat ulang setelah izin tindakan dicabut, form tersebut dibersihkan.

Konteks Purchasing yang dibaca Sino, termasuk untuk Manager/Asisten Manager, mengikuti izin lihat. Saran Sino Purchasing juga mengikuti bagian yang dapat dibaca. Staff Purchasing tanpa akses diarahkan setelah login ke Marketing atau IC jika memiliki izin terkait, atau ke halaman akses tidak tersedia. Pengaturan Marketing dan IC dipertahankan ketika formulir hak akses lengkap disimpan.

Validasi: jalankan `php vendor/phpunit/phpunit/phpunit` dari `backend`, dan `npm run build` dari `frontend`. Tes Purchasing mencakup checklist, fallback role, izin lihat terpisah, query, service, form terbuka, request Livewire baru, Sino, akun/master nonaktif, perubahan jabatan/divisi, serta tujuan login. Tes pengadaan yang ada memverifikasi alur IC → PO → penerimaan sebagian/lengkap dan stok.

### Navigasi Purchasing

Purchasing dipisah menjadi `/purchasing/requests` (pengajuan IC dan form proses PO) serta `/purchasing/orders` (daftar PO dan status penerimaan). Navigasi berada pada submenu Purchasing di sidebar dan hanya menampilkan halaman yang diizinkan. Submenu dapat dibuka/ditutup saat modul lain aktif dan selalu terbuka selama Purchasing aktif. Halaman tidak lagi memuat tombol navigasi Pengajuan/Pesanan. Setiap halaman hanya memuat tabel aktif. URL `/purchasing` tetap menjadi pintu masuk dan memilih pengajuan atau pesanan sesuai izin. Setelah PO dibuat, pengguna diarahkan ke daftar pesanan dengan pesan berhasil.


Dashboard `/purchasing` kini menjadi halaman awal dengan pesan **Dalam tahap pengembangan. Stay tune!** Klik induk Purchasing langsung membuka dashboard. Submenu tetap terbuka selama Purchasing aktif; animasi tinggi dan opacity memakai transisi 240 ms serta mengikuti preferensi reduced motion. Dashboard tidak memuat tabel pengajuan/pesanan.


### Struktur halaman Purchasing

Tiga komponen terpisah: `DashboardPage.php` → `dashboard.blade.php`, `PengajuanPage.php` → `pengajuan.blade.php`, dan `PembelianPage.php` → `pembelian.blade.php`. Dashboard berisi pesan pengembangan, Pengajuan memuat pengajuan IC dan proses PO, Pembelian memuat daftar PO. Setiap halaman memeriksa izin pada setiap request. Jika izin halaman dicabut, halaman menolak akses; pengguna dapat memilih halaman lain yang masih diizinkan melalui sidebar.


## Navigasi PPIC

Induk PPIC hanya membuka/menutup dropdown. Dashboard dan Production Plan memakai `access-ppic`. Label Inventory Control mengelompokkan Kesiapan Penjualan (`/ppic/inventory-control/sales`), Pengajuan Pembelian (`/ppic/inventory-control/requests`), Barang Masuk (`/ppic/inventory-control/receipts`), dan Stok (`/ppic/inventory-control/stock`). Menu dan URL mengikuti izin lihat masing-masing. Navigasi tab di konten dihapus. URL lama `/ppic/inventory-control` tetap memilih bagian pertama sesuai izin. Halaman dengan URL bagian eksplisit menolak request berikutnya jika izin bagian dicabut. Indikator PPIC merangkum penjualan belum diserahkan dan PO belum diterima lengkap, sesuai izin pengguna.


## Barang Keluar

Empat izin IC baru: lihat (`ppic.ic.issues.view`), kelola draft (`.create`), konfirmasi (`.confirm`), dan pembalikan (`.reverse`). Rincian alur dan validasi ada di [barang-keluar.md](barang-keluar.md).
