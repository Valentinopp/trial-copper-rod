# Trial Copper Rod mandiri

Branch `trial/copper-rod` menjalankan Copper Rod secara mandiri. Pengaturan
`config/copper_rod.php` mengaktifkan `COPPER_ROD_STANDALONE=true` secara default
pada branch ini. Tidak perlu membuat SO, kesiapan material, atau jadwal PPIC.
Untuk menjalankan alur integrasi kembali, set `COPPER_ROD_STANDALONE=false` di
`.env`, lalu jalankan `php artisan config:clear`.

## Persiapan

- Pakai database trial di `.env`. Pindah branch tidak mengganti database.
- Jalankan `composer install` dan `php artisan migrate` di `backend`, kemudian
  `npm ci` dan `npm run build` di `frontend`.
- Siapkan master Take Up, Coiling, Crystallizer, dan spesifikasi Cu Rod yang aktif
  beserta bobot MT/m. Master kejadian, kondisi, dan kategori putus serta karyawan
  SPV Produksi tetap digunakan untuk detail Copper Putus.
- Login dengan akun operator produksi. Tidak perlu login sebagai Marketing atau PPIC.

## Alur trial

1. Pada Papan Digital, klik Tambah catatan dan pilih spesifikasi Cu Rod dari master.
2. Isi LOT, Take Up, Coiling, Crystallizer, kecepatan mm/menit, serta waktu upcasting.
3. Simpan sebagai LOT berjalan. Estimasi mengikuti bobot MT/m × (kecepatan mm/menit ÷
   1.000) × 60 × jam berjalan, maksimal 70 jam. Estimasi pickup otomatis +70 jam.
4. Saat Panen/Putus, edit catatan yang sama. Isi waktu aktual pickup, status, berat
   timbang, dan reject. Aktual hasil baik = berat timbang − reject. Tanpa pickup
   lengkap, berat/reject tidak dapat disimpan. Putus mereset estimasi menjadi nol.
5. Lengkapi kejadian Putus melalui Copper Putus. Pantau Crystallizer dan Rekap Output.

## Rekap dan ekspor

Tabel bulanan serta Excel/PNG menampilkan aktual hasil baik, aktual MTD, hasil
kalkulasi, total line, dan output shift. Baris target, selisih, serta pencapaian PPIC
disembunyikan. Aktual masuk pada hari produksi saat pickup: pukul 08.00 sampai
07.59 hari berikutnya. Pickup tanggal 1 sebelum 08.00 masuk ke bulan sebelumnya.
Output kalkulasi tetap dibagi berdasarkan durasi operasi tiap shift, sehingga
nilainya bisa berbeda dari hasil timbang.

Data trial tersimpan sebagai Board Entry tanpa `production_schedule_id`. Tidak
ada transaksi SO, material, atau jadwal baru yang dibuat otomatis. Jika integrasi
diaktifkan lagi, LOT trial tidak otomatis ditautkan atau dihitung sebagai aktual
pesanan. Database trial sebaiknya tetap dipisahkan dari penggunaan terintegrasi.

## Verifikasi

`php artisan test --filter=CopperRodStandaloneTest` menguji alur tanpa SO/PPIC,
master wajib, duplikasi LOT, validasi pickup dan reject, detail Putus, pergantian
bulan, serta ekspor. Tes alur lama menggunakan konfigurasi integrasi dari
`phpunit.xml`; tes mandiri mengaktifkan mode trial secara eksplisit.
