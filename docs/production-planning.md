# Production Planning

Menu **PPIC → Production Plan** (`/ppic/production-plan`) dipakai planner untuk menentukan jumlah produksi per hari berdasarkan pesanan yang sudah diserahkan Inventory Control.

## Alur penggunaan

1. Selesaikan **IC → Kesiapan Penjualan → Serahkan ke Production Plan**. Penyerahan harus memiliki kebutuhan bahan dan cadangan material yang lengkap.
2. Buka Production Plan. Tanggal awal adalah hari ini; gunakan kalender mingguan, tombol Hari ini, atau pemilih tanggal untuk berpindah hari.
3. Pada **Pesanan siap dijadwalkan**, pilih **Jadwalkan**. Daftar diurutkan berdasarkan batas kirim terdekat dan dapat dicari dengan nomor SO/PP, produk, atau customer.
4. Isi tanggal serta jumlah produksi, kemudian **Simpan jadwal**. Jumlah awal mengikuti sisa yang belum dijadwalkan. Gunakan tombol **Gunakan semua sisa** bila ingin menjadwalkan seluruh sisa pesanan.
5. Untuk membagi pesanan, misalnya 1.000 KG menjadi 600 KG dan 400 KG, simpan jadwal pertama lalu jadwalkan sisa pesanan. Jadwal berikutnya boleh memakai tanggal yang sama atau berbeda. Catatan dapat dipakai untuk membedakan masing-masing jadwal.
6. Gunakan **Ubah** untuk mengganti tanggal, jumlah, atau catatan. **Batalkan** meminta konfirmasi, mempertahankan riwayat, dan mengembalikan jumlah ke sisa pesanan yang dapat dijadwalkan.

Ringkasan harian menampilkan jumlah rencana per satuan, jumlah pesanan pada hari terpilih, dan jumlah pesanan yang belum dijadwalkan penuh. Satuan berbeda ditampilkan terpisah. Filter **Semua pesanan dari IC** juga menampilkan pesanan yang sudah dijadwalkan penuh. Jadwal dibatalkan dapat dilihat dengan **Tampilkan yang dibatalkan**.

## Aturan data

- Jadwal tersimpan di database `production_schedules`, terhubung ke penyerahan IC dan pesanan asli. Produk, customer, batas kirim, dan satuan mengikuti data pesanan.
- Satu tanggal dapat memuat banyak pesanan dan satu pesanan dapat memiliki beberapa jadwal aktif pada tanggal yang sama. Total seluruh jadwal aktif tetap tidak boleh melebihi jumlah pesanan. Ringkasan membedakan jumlah pesanan unik dan jumlah jadwal.
- Tanggal baru harus hari ini atau sesudahnya. Tanggal produksi setelah batas kirim diperbolehkan dengan peringatan yang terlihat pada form dan daftar jadwal.
- Pengiriman formulir berulang tidak membuat jadwal ganda. Penguncian per pesanan mencegah alokasi berlebih; pemeriksaan revisi menolak penyimpanan atau pembatalan berdasarkan data yang sudah diubah planner lain.
- Akses baca dan tulis mengikuti hak Production Plan (`access-ppic`), termasuk pemeriksaan langsung pada service dan setiap permintaan Livewire.
- Penjadwalan menyimpan planner, waktu perubahan, revisi, serta informasi pembatalan. Menjadwalkan atau membatalkan jadwal tidak menambah/mengurangi stok atau menghapus cadangan bahan IC.

Tahap ini mencatat rencana tanggal dan jumlah serta realisasi timbang dari Copper Rod. Kapasitas mesin/shift, pemakaian stok bahan, penerimaan barang jadi, dan pengiriman belum dihitung otomatis. Status **Sudah terjadwal** berarti seluruh jumlah pesanan sudah dialokasikan ke tanggal produksi.

## Integrasi dengan Papan Digital Copper Rod

Spesifikasi Copper Rod dihubungkan sekali pada **Master Barang**. Saat membuat SO, diameter, bobot rod, dan ID spesifikasi disimpan sebagai snapshot produk. Production Plan otomatis memakai snapshot SO dan hanya menampilkan diameternya sebagai informasi; planner cukup mengisi tanggal, jumlah, dan catatan. Input diameter dari client diabaikan. Perubahan master tidak mengubah spesifikasi SO atau jadwal lama. SO lama tanpa snapshot memakai diameter eksplisit bila tersedia, atau hubungan master produk, tanpa membaca diameter dari nama produk. Produk umum tanpa spesifikasi tetap dapat dijadwalkan, tetapi tidak dapat dipakai untuk Input LOT Copper Rod sampai spesifikasinya tersedia.

Pada **Copper Rod → Papan Digital**, operator memilih tanggal rencana pada panel **Plan vs Aktual Copper Rod**, lalu klik **Input LOT**. Jadwal juga dapat dipilih dalam formulir Input data papan. Satu jadwal dapat mempunyai banyak LOT, termasuk LOT pada beberapa crystallizer atau Take Up. Setiap catatan papan wajib memilih jadwal aktif yang terhubung ke SO. Pilihan pencatatan mandiri dihapus. Kewajiban ini diperiksa oleh validasi form dan service penyimpanan, termasuk saat mengubah catatan lama. Diameter selalu mengikuti jadwal dan tidak memiliki input manual.

Perbandingan yang sama muncul pada kedua halaman dan diperbarui berkala setiap 30 detik:

- **Plan**: jumlah pada jadwal Production Plan.
- **Aktual**: akumulasi berat timbang dikurangi berat reject dari LOT yang terhubung, setelah tanggal/jam aktual pick up dan status Panen/Putus lengkap.
- **Selisih**: aktual dikurangi plan. Nilai negatif menunjukkan kekurangan, nilai positif menunjukkan hasil melebihi target. Hasil melebihi target tetap dicatat sesuai timbangan.
- **Progres**: Belum mulai, Sedang produksi, Sebagian selesai, atau Target tercapai; jumlah LOT berjalan juga ditampilkan.

Berat wajib diisi untuk LOT terhubung yang selesai pick up. Berat reject dapat diisi 0 dan tidak boleh melebihi berat timbang. Kejadian Putus tetap dapat menghasilkan berat baik; hanya berat reject yang dikeluarkan dari aktual. LOT yang masih berjalan dan estimasi output berdasarkan kecepatan tidak menambah aktual timbang.

Estimasi pick up dihitung otomatis dari waktu upcasting ditambah 70 jam dan tidak diisi manual. Pengingat status Panen/Putus yang belum dipilih muncul saat operator menekan Simpan, bukan saat mengisi aktual pick up. Nilai selisih plan dan aktual ditampilkan merah pada kedua halaman; judul kolom memakai warna normal.

Kalender mingguan Papan Digital menampilkan target dan jumlah jadwal aktif per hari, dipisahkan menurut satuan pesanan. Operator dapat memilih tanggal, minggu sebelumnya/berikutnya, dan kembali ke hari ini untuk melihat target mendatang. Jadwal hanya dapat diubah di Production Planning sesuai izin yang sudah ada.

Rekap Output memakai matriks bulanan: tanggal 1 sampai akhir bulan sebagai kolom, indikator produksi sebagai baris. Pilihan bulan dan tombol bulan sebelumnya/berikutnya berlaku untuk semua bulan, termasuk Februari tahun kabisat. Baris memakai latar putih dan abu-abu bergantian, termasuk kolom indikator dan shift yang tetap terlihat saat digeser. Ekspor Excel/PNG mengikuti orientasi yang sama, dengan periode pada header. Ekspor Excel bulanan memakai header bulan dua tingkat, gabungan sel indikator, warna baris putih/abu-abu, garis tabel, font navy, selisih merah, lebar kolom, dan format dua desimal seperti tabel. Persentase disimpan sebagai angka persentase Excel. Modul ekspor berformat dimuat hanya saat unduhan bulanan; ekspor modul lain tetap menggunakan jalur sebelumnya. Nilai ditampilkan dua desimal.

Baris laporan: TOTAL LINE, TARGET OUTPUT, TARGET MTD, AKTUAL OUTPUT, AKTUAL MTD, Output/Day, Month to Date, Achievement, Selisih Aktual − Plan, serta Output/Shift 1–3. Target memakai total jadwal PPIC aktif, dikonversi ke MT menggunakan snapshot KG; target manual tidak ditampilkan sebagai target laporan bulanan. Aktual adalah berat baik LOT selesai yang terhubung ke jadwal asal, walaupun pick up dilakukan di tanggal/bulan berikutnya. LOT berjalan, berat tanpa tautan jadwal, dan jadwal dibatalkan tidak menambah aktual. TOTAL LINE adalah total jam operasi harian dibagi 24 (rata-rata line ekuivalen). Output/Day dan Month to Date tetap merupakan kalkulasi kecepatan/durasi.

Target MTD dan Aktual MTD diakumulasi sejak tanggal 1 dan dimulai ulang setiap bulan. Hari tanpa jadwal ditampilkan “—” pada target/aktual harian; kumulatif mempertahankan nilai sebelumnya. Achievement adalah aktual timbang ÷ target PPIC × 100%, atau “—” jika tidak ada target. Jika ada satuan tanpa konversi KG, target/selisih/pencapaian terkait dan target MTD sejak hari tersebut ditampilkan “—” agar angka sebagian tidak dianggap lengkap. Kartu ringkasan memakai nilai hingga tanggal produksi saat ini untuk bulan berjalan, atau akhir bulan untuk bulan lainnya.

Estimasi LOT yang belum ditimbang ditampilkan terpisah dari aktual pada kedua modul dan riwayat Papan Digital. Sesuai rumus Excel, kecepatan input (mm/menit) dibagi 1.000 menjadi m/menit. Panjang rod (m) = kecepatan (m/menit) × 60 × jam berjalan. Estimasi (MT) = panjang (m) × snapshot bobot rod (MT/m); hasil KG internal = MT × 1.000. Area estimasi Input Papan selalu tampil setelah waktu produksi, dengan placeholder jika data belum lengkap, dan menggunakan satuan MT. Panen tanpa waktu pick up belum menghentikan estimasi berjalan; Putus tetap mereset estimasi. Durasi estimasi dibatasi pada siklus 70 jam agar tidak terus naik saat menunggu timbang; jam operasi dan perhitungan output shift tidak diubah. Perkiraan berat saat 70 jam tidak ditampilkan di halaman maupun ekspor. Tidak memakai angka tetap 2–3 ton atau berat manual. Estimasi WIP pada rekap menjadi 0 saat status Putus, atau setelah pick up selesai dan berat timbang tersedia; aktual tetap berdasarkan berat baik. Estimasi pada form Input/Ubah Papan adalah pratinjau teoritis: tetap dihitung meskipun berat aktual sudah diisi, sampai waktu Aktual Pick Up jika lengkap atau waktu sekarang jika belum diisi. Status Putus tetap mereset pratinjau menjadi 0. Perubahan tanggal/jam, kecepatan, status, berat, dan reject diperbarui melalui Livewire; indikator loading ditampilkan saat parameter estimasi sedang diperbarui. Nilai tidak dapat dihitung ditampilkan “—”, bukan ditebak. Perbandingan jadwal memakai satuan pesanan melalui snapshot konversi KG. Perubahan tanggal kalender hanya menyaring jadwal; estimasi dihitung sampai waktu sekarang, bukan simulasi waktu pada tanggal yang dipilih.

Jumlah memakai satuan pesanan. Untuk KG, faktor ke berat timbang adalah 1. Untuk satuan lain, konversi hanya tersedia jika UoM terkait di database mengarah ke KG dengan faktor positif; jadwal menyimpan faktor saat digunakan. Satuan tanpa konversi tidak diperlakukan sebagai KG. Total satuan berbeda tetap terpisah.

Koreksi atau penghapusan LOT otomatis menghitung ulang aktual pada kedua halaman. Duplikasi LOT pada jadwal, crystallizer, dan waktu upcasting yang sama ditolak. Jadwal yang sudah mempunyai LOT tidak dapat dibatalkan atau diganti tanggal/diameternya; target tidak dapat diturunkan di bawah aktual. Tautan jadwal pada LOT yang sudah disimpan dikunci agar hasil tidak berpindah pesanan secara tidak sengaja.

Integrasi ini tidak membuat transaksi stok baru dan tidak menautkan catatan lama secara otomatis berdasarkan nama atau LOT. Riwayat lama yang belum terhubung tetap dapat dilihat; sebelum perubahan disimpan, operator wajib menautkannya ke jadwal/SO dengan diameter yang sesuai.

Jalankan `php artisan migrate` dari `backend` dan `npm.cmd run build` dari `frontend` setelah memasang perubahan.

Pola yang digunakan mengacu pada [penjadwalan produksi berbasis tanggal dan jumlah di Dynamics 365](https://learn.microsoft.com/en-us/dynamics365/supply-chain/production-control/operations-scheduling) dan [pemecahan kuantitas pesanan produksi di Odoo](https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/manufacturing/workflows/split_merge.html).

Form Input Papan memakai validasi server saat submit agar field otomatis/tersembunyi tidak memicu validasi browser yang mendahului sinkronisasi. Diameter kosong dimuat ulang dari jadwal sebelum validasi dan saat render, sedangkan diameter yang berbeda dari jadwal tetap ditolak. Pesan kesalahan menyebut nama kolom yang bermasalah dan tidak lagi diganti menjadi pesan umum.

Berat dan reject pada Input Papan selalu dapat diisi. Popup tidak muncul saat klik/fokus atau mengetik. Saat submit, bila berat telah diisi (termasuk 0) atau reject bukan nol tetapi tanggal/jam Aktual Pick Up belum lengkap, muncul warning “Anda belum mengisi tanggal dan jam Aktual Pick Up” dan data belum disimpan. Isian berat/reject tetap dipertahankan agar operator dapat melengkapi waktu pick up. Service juga menolak penyimpanan tanpa waktu pick up lengkap. LOT berjalan tetap dapat disimpan dengan berat kosong dan reject default 0. Tombol Kosongkan berat dan reject dihapus.

Tabel Pesanan siap dijadwalkan menampilkan kolom Aktual: akumulasi berat baik LOT selesai dari seluruh jadwal aktif pesanan, lintas tanggal, menggunakan snapshot konversi satuan tiap jadwal. LOT berjalan dan catatan tanpa tautan jadwal tidak dihitung. Aktual ditampilkan dalam satuan SO; jika konversi KG tidak tersedia ditampilkan tanda strip. Sisa dijadwalkan tetap jumlah pesanan dikurangi alokasi jadwal, bukan dikurangi aktual. Data jadwal/aktual dimuat sekaligus untuk daftar pesanan agar tidak menambah query per baris.
Kolom Sisa Aktual ditambahkan terpisah: aktual produksi dikurangi jumlah pesanan dalam satuan SO, dengan presisi desimal yang sama. Nilai positif diberi tanda + untuk kelebihan produksi, nilai negatif menunjukkan kekurangan, dan nilai 0 berarti aktual pas dengan jumlah pesanan. Jika konversi aktual belum tersedia, Sisa Aktual juga ditampilkan tanda strip. Kolom Sisa dijadwalkan dan batas alokasi jadwal tetap dihitung dari jumlah pesanan dikurangi jumlah terjadwal.