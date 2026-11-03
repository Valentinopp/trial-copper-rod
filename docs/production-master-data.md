# Master Data Produksi Copper Rod

IT Super Admin mengelola referensi melalui menu **Master Data**:

Satu dropdown **Produksi Copper Rod** memuat Take Up, Coiling, Crystallizer, Spesifikasi Cu Rod, Kejadian Saat Putus, Kondisi Setelah Putus, dan Kategori Putus.

SPV menggunakan **Organisasi & Karyawan → Karyawan**, bukan master terpisah. Pilihan di Copper Putus hanya mencakup karyawan aktif dengan jabatan SPV/Supervisor (termasuk SPV Produksi/Supervisor Produksi) dan divisi Produksi, Production, atau Produksi Copper Rod. Jabatan dan divisi juga harus aktif. Identitas pilihan memakai ID karyawan sehingga nama yang sama tidak tertukar. Saat belum ada karyawan yang sesuai, form menampilkan petunjuk untuk melengkapi data Karyawan.

Setiap master memiliki kode, nama, deskripsi, dan status aktif. Take Up dan Coiling juga memiliki servo X/Y; kombinasi pada papan harus memakai servo yang sama. Spesifikasi Cu Rod menyimpan diameter dalam mm (maksimal satu desimal) dan bobot rod dalam ton/m. Bobot ini digunakan untuk menghitung output: bobot rod (MT/m) × (kecepatan take up (mm/menit) ÷ 1.000) × 60 × jam operasi.

Form **Input data papan** mengambil Take Up, Coiling, Crystallizer, dan diameter dari master aktif. Form **Detail Copper Putus** mengambil SPV dari Karyawan serta kejadian, kondisi setelah putus, dan kategori dari master aktif. Pilihan bebas tidak dapat disimpan. SPV wajib; kejadian dan kategori diperlukan agar detail ditandai lengkap pada monitoring. Kondisi setelah putus, frekuensi, dan catatan tetap opsional. Data sumber Copper Putus tetap berasal dari catatan papan berstatus Putus.

Data master dapat ditambah, dilihat, diubah, dicari, dan dinonaktifkan. Penonaktifan mencegah pemakaian pada transaksi baru. Catatan lama tetap mempertahankan pilihan yang sudah tersimpan dan dapat diedit tanpa mengganti pilihan tersebut. Untuk mengganti pilihan, gunakan master aktif.

Transaksi menyimpan ID master beserta teks saat pencatatan. Transaksi papan juga menyimpan bobot rod dan servo saat pencatatan. Perubahan kode/nama, bobot, atau servo pada master tidak otomatis mengubah riwayat dan output transaksi tersebut.

Jalankan `php artisan migrate` dari folder `backend` saat memasang perubahan ini. Migrasi menyiapkan pilihan bawaan yang sebelumnya ditetapkan di kode, mengimpor nilai yang sudah digunakan, dan menghubungkan transaksi lama tanpa mengganti teksnya. Daftar crystallizer berasal dari catatan papan yang sudah ada; bila belum ada, admin perlu mendaftarkannya. Spesifikasi diameter lama yang bobotnya tidak diketahui dan kode peralatan lama yang servonya tidak terbaca diimpor sebagai nonaktif sampai dilengkapi admin.

Pembanding Excel: pada rod 8 mm dengan bobot 0,00045 MT/m, input 1590 mm/menit berarti 1,59 m/menit. Durasi 30,25 jam dan 35,5 jam menghasilkan 1,2986325 + 1,524015 = 2,8226475 MT. Input 1200 mm/menit selama 70 jam menghasilkan 2,268 MT. Setiap LOT dihitung sebagai satu line; total menjumlahkan hasil tiap LOT, tanpa mengalikan jumlah line dua kali. Satuan input, riwayat, ekspor, dan konteks Sino menggunakan mm/menit.
