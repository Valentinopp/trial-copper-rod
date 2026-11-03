# Rencana Master Supplier dan Master Customer

Status: diimplementasikan pada modul Master Data di branch `feature/master-data`. Panduan penggunaan tersedia di [Master Data](master-data.md). Integrasi Purchasing/Penjualan tetap menjadi pekerjaan terpisah di branch masing-masing.

## Tujuan

Menambah rumah data supplier dan customer. Supplier dapat dihubungkan dengan barang yang mereka sediakan dari Master Barang yang sudah ada. Satu supplier dapat menyediakan banyak barang, dan satu barang dapat disediakan banyak supplier.

Istilah produk pada daftar supplier berarti item dari `master_data_items`, termasuk barang, material, dan sparepart. Tidak dibuat katalog barang supplier terpisah atau aturan khusus cathode/tonase.

## Struktur menu

```text
MASTER DATA
  Master Barang
  Master Kategori
  Master UoM
  Master Supplier
  Master Customer
```

Halaman baru: `/master-data/suppliers` dan `/master-data/customers`. Navigasi tetap vertikal pada sidebar.

## Field supplier dan customer

Kedua master memakai field dasar yang sama dengan record dan kode masing-masing:

| Field | Ketentuan |
| --- | --- |
| ID | Identitas internal |
| Kode | Wajib, unik pada masing-masing master, dinormalisasi ke huruf besar |
| Nama | Wajib; nama supplier/customer |
| Contact person | Opsional |
| Telepon | Opsional, teks agar awalan + dan 0 tetap tersimpan |
| Email | Opsional; validasi format bila diisi |
| Alamat | Opsional |
| Catatan | Opsional |
| Status aktif | Aktif/nonaktif |
| Created/updated at | Timestamp |

Contoh kode: `SUP-001` dan `CUS-001`. Nama tidak dijadikan kunci unik. Bila perusahaan yang sama menjadi supplier dan customer, identitasnya dicatat pada masing-masing master untuk tahap ini.

## Struktur data

| Tabel | Isi |
| --- | --- |
| `master_data_suppliers` | Identitas dan kontak supplier |
| `master_data_customers` | Identitas dan kontak customer |
| `master_data_supplier_items` | Relasi supplier–barang |

Tabel penghubung berisi ID, `supplier_id`, `item_id`, serta timestamps. Pasangan `(supplier_id, item_id)` memiliki constraint unik, dengan foreign key ke tabel supplier dan `master_data_items`. Relasi dua arah: `Supplier.items` dan `Item.suppliers`.

Contoh:

```text
Supplier A → Copper Cathode, Arang
Supplier B → Copper Cathode, Sparepart
```

Copper Cathode tetap satu record Master Barang yang sama meskipun terhubung ke dua supplier. Mengubah nama barang di master otomatis terbaca saat daftar supplier dimuat kembali.

Customer pada tahap ini menyimpan identitas/kontak. Belum ada hubungan customer–barang atau hubungan ke transaksi penjualan.

## Alur halaman

### Master Supplier

1. Daftar supplier memuat kode, nama, kontak, jumlah barang terhubung, status, dan aksi lihat/ubah.
2. Tombol Tambah supplier membuka form identitas/kontak.
3. Bagian Barang yang disediakan menyediakan pencarian/pemilihan beberapa barang dari Master Barang. Tampilkan kode, nama, kategori, dan UoM agar barang mudah dibedakan.
4. Barang terpilih tampil sebagai daftar dengan aksi Lepas hubungan. Daftar dipaginasi/dicari agar tidak perlu memuat seluruh katalog ketika jumlah barang bertambah.
5. Simpan identitas dan perubahan relasi dalam satu transaksi. Supplier boleh disimpan tanpa barang terlebih dahulu.
6. Edit supplier memuat kembali relasi yang tersimpan. Melepas hubungan hanya menghapus relasi, bukan Master Barang.

### Master Customer

Daftar dan form identitas/kontak mengikuti pola supplier, tanpa pemilihan barang. Tersedia tambah, lihat/ubah, pencarian kode/nama, pagination, serta aktif/nonaktif.

Gunakan halaman Livewire khusus Supplier dan Customer dalam namespace MasterData, dengan gaya form/layout yang konsisten dengan tiga master sebelumnya. Komponen MasterDataPage yang menangani barang/kategori/UoM tidak perlu menampung seluruh logika partner.

## Aturan data

- Kode supplier/customer unik pada masing-masing master dan tidak peka huruf besar/kecil melalui normalisasi.
- Validasi panjang field dan email dilakukan sebelum penyimpanan.
- Item yang dihubungkan harus benar-benar ada; pilihan baru menggunakan item aktif.
- Satu item tidak dapat terhubung dua kali ke supplier yang sama, termasuk saat request dikirim ulang.
- Item nonaktif yang sudah terhubung tetap tampil dengan penanda nonaktif dan dapat dilepas secara eksplisit. Membuka/menyimpan form tidak boleh menghilangkan relasi tersebut secara diam-diam.
- Kegagalan validasi barang membatalkan seluruh penyimpanan; tidak boleh meninggalkan identitas/relasi tersimpan sebagian.
- Supplier/customer dinonaktifkan melalui status, tanpa hapus permanen. Status supplier tidak menghapus daftar barangnya.
- Relasi ini menyatakan barang yang disediakan supplier; belum menetapkan harga, stok supplier, atau komitmen pembelian.

## Urutan implementasi

1. Tambahkan satu migration untuk dua master dan tabel penghubung; migration hanya membuat struktur/data Master Data yang diperlukan.
2. Tambahkan model Supplier dan Customer serta relasi supplier–Item.
3. Tambahkan validasi dan penyimpanan transaksional untuk identitas serta attach/detach barang.
4. Tambahkan dua halaman/form dan menu vertikal beserta label layout dan registrasi Livewire.
5. Tambahkan pengujian fitur dan regresi, lalu periksa form/navigasi melalui browser.
6. Perbarui panduan Master Data dengan field, menu, dan cara menghubungkan barang.

## Kriteria penerimaan

- Supplier dan Customer dapat ditambah, diubah, dicari, dan dinonaktifkan.
- Satu supplier dapat memiliki beberapa barang; satu barang dapat dipakai beberapa supplier.
- Edit form mengembalikan pilihan tersimpan dan tidak menggandakan relasi.
- Melepas barang pada Supplier A tidak memengaruhi relasi Supplier B maupun data barangnya.
- Kode duplikat, email tidak valid, dan item palsu ditolak tanpa perubahan parsial.
- Relasi item yang kemudian dinonaktifkan tetap terlihat dan tidak hilang saat edit.
- Tabel maupun service modul operasional tidak berubah; tidak ada PO, penjualan, perubahan stok, atau transaksi operasional yang dibuat dari penyimpanan master.
- Migration up/down diuji pada database pengujian dan tidak mengubah tabel lama. Pengujian regresi tetap lulus.

## Ditunda

Pemilihan supplier di Purchasing, pemilihan customer di Penjualan, daftar harga, mata uang, termin pembayaran, rekening, NPWP, kontrak, MOQ, lead time, dan approval. Kode barang khusus supplier juga dapat ditambahkan ke tabel penghubung pada tahap berikutnya bila dibutuhkan.
