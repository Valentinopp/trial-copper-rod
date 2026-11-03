# Penjualan Marketing

Menu `/marketing/sales` mencatat penjualan final untuk satu produk per transaksi.

- Customer dipilih dari Master Customer yang aktif.
- Produk dipilih dari Master Barang dengan kategori bernama **Produk**. Barang, kategori, dan UoM beserta acuannya harus aktif.
- Qty mengikuti UoM barang, tanpa konversi otomatis. Qty positif, maksimal enam angka desimal.
- Tanggal penjualan tidak boleh di masa depan; tanggal harus dikirim tidak boleh sebelum tanggal penjualan.
- Data customer, produk, dan UoM saat penjualan dicatat disimpan sebagai snapshot, sehingga perubahan master tidak menulis ulang riwayat transaksi.
- Token pengiriman unik mencegah transaksi ganda saat permintaan yang sama dikirim ulang.

Transaksi disimpan dalam `marketing_sales_orders`, melalui model `SalesOrder` dan service `SalesService`, dengan referensi ke `master_data_customers`, `master_data_items`, dan `master_data_units`. Tidak diperlukan BoM untuk mencatat penjualan. Integrasi ke PPIC, produksi, dan stok dikerjakan pada tahap terpisah.

Migrasi `000013` mempertahankan ID transaksi lama beserta referensi yang mengarah kepadanya. Identitas customer/produk lama dipertahankan sebagai snapshot; relasi master yang belum dipetakan dibiarkan kosong. Transaksi dari tabel sementara `marketing_sales` dipindahkan, lalu tabel sementara dihapus. Nomor transaksi yang dipindahkan mengikuti ID baru di tabel tujuan. Migrasi historis `000012` tetap disimpan agar urutan migrasi konsisten; skema akhir hanya memiliki satu tabel transaksi penjualan. Penggabungan tidak mendukung rollback otomatis; backup diperlukan untuk kembali ke skema sebelumnya.
