# Master BoM

Menu **Master Data → Master BoM** (`/master-data/boms`) menyimpan komposisi material untuk produk dari Master Barang. Modul ini berdiri sendiri, belum menjalankan produksi atau mengubah stok.

- Identitas: kode unik, nama BoM, catatan, status aktif/nonaktif.
- Produk hasil: hanya barang dengan kategori langsung **Produk**. Barang kategori Material atau Sparepart tidak dapat dipilih meskipun kategori induknya Produk.
- Jumlah hasil acuan: jumlah produk yang menjadi acuan kebutuhan seluruh material.
- Material penyusun: satu atau lebih barang berkategori **Material** atau subkategorinya, dengan jumlah masing-masing.
- Satuan hasil dan material mengikuti UoM masing-masing barang. Tidak ada konversi otomatis, perhitungan yield, atau loss.

Contoh: hasil acuan 1.000 KG Copper Rod menggunakan 1.000 KG Cathode dan 5 KG Arang. Angka tersebut diisi pengguna, bukan rumus bawaan sistem.

Material tidak boleh berulang dalam satu BoM. Jumlah harus positif, maksimal enam angka desimal. BoM aktif hanya dapat disimpan dengan barang, kategori, dan UoM aktif. BoM dapat dilihat, diubah, dicari berdasarkan kode/nama BoM atau produk, dan dinonaktifkan. Beberapa BoM untuk produk yang sama diperbolehkan selama kode BoM berbeda.
