# Barang Keluar — Maintenance dan Operasional

Menu: **PPIC → Barang Keluar** (`/ppic/inventory-control/issues`). Tahap ini tidak membuat NPB, pekerjaan Produksi, atau integrasi PP baru.

## Menggunakan

1. Siapkan divisi aktif dan Master Barang dengan UoM/kategori aktif. Persediaan yang didukung: Material, Sparepart, Perlengkapan, Barang Habis Pakai, termasuk subkategori langsungnya.
2. Barang masuk tetap melalui pengajuan IC → PO Purchasing → penerimaan IC. Sparepart bawaan (`SP`) kini ikut dapat dipilih. Tidak ada saldo awal atau stok fiktif yang dibuat otomatis.
3. Pilih **Buat pengeluaran**, isi tanggal, keperluan Maintenance/Operasional, divisi, penerima, aset/referensi/keterangan jika ada, dan maksimal 50 baris barang. Satu barang hanya boleh muncul sekali. Jumlah menggunakan satuan master tanpa konversi otomatis, maksimal enam desimal.
4. **Simpan draft**. Stok belum berubah. Detail memperlihatkan nomor otomatis `BK-000001`, barang/satuan saat disimpan, identitas petugas, dan waktu tindakan.
5. Setelah barang diserahkan, gunakan **Konfirmasi pengeluaran**. Stok diperiksa lagi, termasuk reservasi PP dan tanggal penerimaan. Pengeluaran sebelum stok diterima atau melebihi stok bebas ditolak secara atomik.
6. Draft boleh diubah atau dibatalkan. Dokumen dikonfirmasi tidak bisa diedit/dihapus. Gunakan **Balikkan pengeluaran** dengan alasan minimal lima karakter hanya jika pencatatan salah atau barang dikembalikan seluruhnya. Ini pembalikan penuh dokumen, bukan fitur retur sebagian.

Riwayat dapat dicari menggunakan nomor BK, referensi permintaan, penerima, atau nama divisi. Tersedia filter tanggal, divisi, keperluan, dan status.

## Hak akses

Di checklist **PPIC — Inventory Control**:

- `ppic.ic.issues.view`: lihat daftar/detail.
- `ppic.ic.issues.create`: buat, ubah, batalkan draft.
- `ppic.ic.issues.confirm`: konfirmasi dan kurangi stok.
- `ppic.ic.issues.reverse`: pembalikan penuh dan pengembalian stok.

Setiap izin tindakan otomatis menyertakan izin lihat. Konfirmasi/pembalikan tidak otomatis mengizinkan pengeditan draft. Semua request Livewire dan service memeriksa izin terkini. Profil yang sudah mengaktifkan checklist IC harus diberi izin Barang Keluar secara eksplisit; role lama tetap mengikuti fallback yang ada. Super Admin aktif tetap mendapat seluruh izin.

## Stok dan konsistensi

Stok fisik = penerimaan − pengeluaran bersih. Stok tersedia = stok fisik − reservasi aktif. Halaman Stok, kebutuhan bahan penjualan, dan konteks Sino membaca perhitungan yang sama. Sino hanya membaca riwayat Barang Keluar jika izin lihat diberikan.

`inventory_issues` menyimpan status/audit dokumen. `inventory_issue_lines.quantity` mempertahankan jumlah asli; `issued_quantity` adalah dampak stok aktif (nol untuk draft/batal/dibalikkan). Konfirmasi dan perubahan dampak stok dijalankan dalam satu transaksi. Stok dikunci pada baris master barang berurutan, sama dengan proses reservasi PP. Pembacaan terkunci memakai saldo terkini agar dua transaksi tidak mengambil stok yang sama. Token draft unik serta pemeriksaan status mencegah efek ganda ketika request diulang.

## Validasi pengembangan

- `php vendor/phpunit/phpunit/phpunit` dari `backend`.
- `npm run build` dari `frontend`.
- Uji konkurensi PostgreSQL: `php tests/Support/verify-goods-issue-concurrency.php` dari `backend`, menggunakan konfigurasi PostgreSQL lokal. Membuat schema sementara `codex_goods_issue_*`, menjalankan migrasi/fixture di sana, menguji dua pengeluaran bersamaan, konfirmasi ulang, dan pembalikan ulang. Hanya schema sementara yang dihapus setelah pengujian; tabel aplikasi tidak diubah. Akun database membutuhkan izin membuat schema.

Migrasi penambahan tabel: `php artisan migrate`. Tidak ada perubahan atau pengurangan stok lama saat migrasi.