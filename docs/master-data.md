# Master Data — fondasi modul mandiri

Modul ini menyediakan lima master melalui grup **MASTER DATA** di sidebar: **Master Barang** (`/master-data/items`), **Master Kategori** (`/master-data/categories`), **Master UoM** (`/master-data/uoms`), **Master Supplier** (`/master-data/suppliers`), dan **Master Customer** (`/master-data/customers`). `/master-data` mengarahkan ke Master Barang. Integrasi ke modul operasional dilakukan terpisah di branch masing-masing.

| Master | Data |
| --- | --- |
| Barang | Kode unik, nama, kategori, UoM, deskripsi/spesifikasi referensi, status aktif |
| Kategori | Kode unik, nama, kategori induk opsional, deskripsi, status aktif |
| UoM | Kode unik, nama, Related UoM opsional, faktor conversion, status aktif |
| Supplier | Kode unik, nama, contact person, telepon, email, alamat, catatan, status, barang yang disediakan |
| Customer | Kode unik, nama, contact person, telepon, email, alamat, catatan, status |

Barang mencakup barang umum, material, sparepart, dan barang lain sesuai kategori. Tidak ada aturan spesifik berdasarkan nama barang. Cathode diperlakukan sebagai barang standar; spesifikasi dapat dicatat sebagai keterangan referensi. Tidak ada kalkulasi komposisi, impurity, tonase, BoM, loss/yield, stok, atau aturan jual/beli pada tahap ini.

Semua master mendukung tambah, lihat/ubah, status aktif/nonaktif, pencarian kode/nama, dan pagination. Kode dinormalisasi ke huruf besar. Nama yang sama dapat dipakai dengan kode berbeda. Kode supplier dan customer unik di masing-masing tabel; satu perusahaan dapat didaftarkan dalam kedua peran.

Kategori mendukung dua tingkat tanpa siklus. Related UoM menunjuk satuan utama; faktor disimpan sebagai konfigurasi: `1 UoM ini = faktor × Related UoM`. Tanpa Related UoM, faktor adalah 1. Tidak ada satuan yang diwajibkan untuk suatu barang atau konversi otomatis pada transaksi.

Tabel modul: `master_data_categories`, `master_data_units`, dan `master_data_items`. Model dan service hanya bergantung pada tabel modul sendiri. Tidak ada pemindahan data katalog lama, foreign key ke tabel modul lain, observer sinkronisasi, atau perubahan service Penjualan, Purchasing, PPIC, maupun Produksi.

Supplier/customer menambah tabel `master_data_suppliers`, `master_data_customers`, dan penghubung `master_data_supplier_items`. Supplier dan barang mempunyai hubungan many-to-many; customer saat ini hanya menyimpan identitas/kontak.

## Barang yang disediakan supplier

1. Buka **Master Supplier → Tambah supplier** atau **Lihat / ubah** pada supplier yang sudah ada.
2. Isi kode dan nama. Contact person, telepon, email, alamat, dan catatan bersifat opsional. Telepon disimpan sebagai teks agar awalan 0 atau + tetap utuh.
3. Cari barang pada bagian **Katalog barang**, lalu pilih **Tambah**. Nama, kategori, dan UoM mengikuti Master Barang.
4. Daftar **Barang terpilih** memuat pilihan yang akan disimpan. Gunakan **Lepas** untuk membatalkan hubungan. Katalog dan pilihan memiliki pencarian/pagination terpisah.
5. Pilih **Simpan supplier** untuk menyimpan identitas dan seluruh perubahan hubungan bersama-sama. Batal tidak mengubah data. Supplier dapat disimpan tanpa barang.

Barang baru yang dipilih harus aktif. Barang nonaktif yang sudah terhubung tetap ditampilkan, tidak dihilangkan saat edit, dan dapat dilepas secara eksplisit. Pasangan supplier–barang unik, sehingga menyimpan form berulang tidak menggandakan hubungan. Melepas hubungan tidak menghapus barang maupun hubungan supplier lainnya. Menonaktifkan supplier tidak menghapus hubungan yang tersimpan.

Pencatatan ini belum membuat pesanan pembelian, penjualan, harga supplier, atau pergerakan stok. Harga, termin pembayaran, MOQ, lead time, dan penggunaan master pada Purchasing/Penjualan dikerjakan pada tahap berikutnya.

Perubahan pada file aplikasi bersama hanya memuat route, alias komponen Livewire, tautan sidebar, label layout, dan stylesheet. Kategori awal: Barang, Material, Sparepart. UoM awal: KG, PCS, M, L. Tidak ada barang contoh yang dibuat otomatis.

## Menjalankan

1. Dari `backend`, jalankan `php artisan migrate`.
2. Dari `frontend`, jalankan `npm.cmd run build`.
3. Buka `/master-data` atau pilih **Master Barang**, **Master Kategori**, atau **Master UoM** di bawah grup **MASTER DATA** pada sidebar.

Pengujian: dari `backend`, jalankan `php vendor/phpunit/phpunit/phpunit`. Test memakai SQLite in-memory. Cakupan mencakup form barang/kategori/UoM, validasi, pencarian, status, relasi tanpa siklus, request Livewire, dan isolasi tabel operasional.

## Catatan checkout lokal

Revert source percobaan integrasi sebelumnya tidak otomatis melakukan rollback PostgreSQL. Saat pemeriksaan, migration lama `2026_10_28_000008_create_master_data` masih tercatat dan tabel/kolomnya masih ada. Migration modul mandiri memakai nama dan tabel berbeda (`000009_create_standalone_master_data`) agar tidak mengubah data dari percobaan sebelumnya. Pembersihan sisa skema lama merupakan pekerjaan terpisah dan belum dilakukan pada tahap ini.
