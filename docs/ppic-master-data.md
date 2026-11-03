# PPIC berbasis Master Data

Inventory Control membaca SO dari `marketing_sales_orders`. Identitas customer/produk dan qty/UoM ditampilkan dari snapshot transaksi Marketing, termasuk pada Production Plan.

## Pemeriksaan kebutuhan

1. Buka Inventory Control → Kesiapan Penjualan.
2. Pilih Master BoM aktif yang produknya sama dengan SO. Jika hanya ada satu BoM aktif, pemilihan otomatis. Jika lebih dari satu, pilihan wajib ditentukan pengguna.
3. Klik Cek kebutuhan. Produk, kategori, material, dan UoM harus aktif. SO lama tanpa referensi Master Barang belum bisa diperiksa; riwayat yang telah diperiksa tetap tersedia.
4. Sistem menghitung `jumlah material BoM × qty SO ÷ jumlah hasil acuan`, dibulatkan ke atas sampai enam desimal. Setiap material tetap memakai UoM-nya sendiri. Tidak ada konversi tonase otomatis atau rumus loss/yield.
5. BoM, hasil acuan, material, jumlah, dan UoM disimpan sebagai snapshot pada SO dan kebutuhan material. Perubahan master setelah pemeriksaan tidak mengubah kebutuhan SO. BoM SO yang sudah diperiksa tidak dapat diganti lewat proses ini.
6. Kekurangan dapat diajukan ke Purchasing. Pesanan berjalan belum menjadi stok sebelum diterima. Pengajuan ulang hanya mencakup kekurangan yang belum memiliki pengajuan.
7. Jika cukup, serahkan ke Production Plan. Bahan dicadangkan agar tidak dipakai untuk SO lain; penyerahan ulang tidak menggandakan cadangan.

UoM SO harus sama dengan UoM produk hasil BoM saat diperiksa. Contoh: SO 2,5 TON, BoM hasil acuan 1 TON dengan kebutuhan 1.000 KG Cathode menghasilkan kebutuhan 2.500 KG Cathode. Jumlah 1.000 ditetapkan pada BoM, bukan konversi bawaan sistem.

## Referensi material dan stok

Tabel pengajuan, penerimaan, kebutuhan SO, dan cadangan tetap digunakan. Foreign key barangnya sekarang mengarah ke `master_data_items`, bukan `inventory_products`. UoM dipertahankan per transaksi; hanya stok dengan barang dan UoM yang sama yang dihitung. Pengajuan manual memakai Master Barang kategori Material atau subkategori Material yang aktif. Nama dan satuan tidak diisi ulang.

UoM barang yang telah dipakai pada BoM atau transaksi tidak dapat diubah melalui Master Barang. Buat barang terpisah untuk satuan berbeda.

Migrasi `000014` tidak membuat tabel baru. Stok lama dipetakan ke Master Barang dengan nama dan UoM yang cocok secara unik. Jika belum cocok, dibuat entri `LEGACY-INV-<id>` nonaktif untuk menjaga riwayat; periksa sebelum diaktifkan. Tabel katalog lama tidak dihapus karena masih merupakan sumber historis. Migrasi tidak mendukung rollback otomatis; simpan backup sebelum penerapan.
