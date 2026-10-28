# Impor foto massal

Memasang banyak foto sekaligus dari sebuah folder, dengan nama berkas mengikuti nama tempat. Perintahnya `php artisan archive:import-photos`.

## Langkah

1. Siapkan folder dan daftar nama berkas yang diharapkan:

       php artisan archive:photo-list

   Ini membuat `storage/app/photos-import/` berisi subfolder `places/` dan `hotels/`, berkas kosong `keterangan.csv`, dan `daftar-nama-foto.csv` yang memuat nama berkas untuk setiap tempat dan hotel yang belum punya foto. Kolom `id_dataset` menunjukkan id baris asal di CSV impor (misalnya combined150:B64), untuk mencocokkan kembali dengan dataset. Daftar dibuat dari database, bukan dari CSV, sehingga nama berkas memakai slug yang tersimpan di sana. Tambahkan `--all` untuk menyertakan yang sudah punya foto. Buka daftar itu di spreadsheet sebagai contekan.

2. Taruh foto tempat di `places/` dan foto hotel di `hotels/`. Nama berkas mengikuti slug atau nama tempat:

       museum-yadnya.jpg          foto pertama
       museum-yadnya-2.jpg        foto kedua
       Museum Yadnya.JPG          juga cocok (huruf besar dan spasi tidak masalah)

   Hanya JPG, PNG, dan WebP. Pencocokan dilakukan terhadap slug dan terhadap nama. Nama yang cocok dengan lebih dari satu record dilaporkan ambigu dan dilewati, tidak ditebak.

3. Lihat rencananya tanpa menulis apa pun:

       php artisan archive:import-photos --dry-run

4. Pasang fotonya, sambil menyatakan hak pakainya:

       php artisan archive:import-photos --credit="Tim The Dewata Archive" --license="Foto milik sendiri"

   Kredit dan lisensi tidak pernah diisi diam-diam, dan perintah menolak berjalan tanpa keduanya (atau tanpa `keterangan.csv`). Isilah hanya bila Anda memang berhak memakai foto itu. Foto dari Google Maps atau situs lain bukan milik Anda.

5. Baca laporannya: jumlah terpasang, nama berkas yang tidak cocok (biasanya salah eja), yang ambigu, yang tidak valid, dan berapa tempat yang masih tanpa foto.

Menjalankan perintah dua kali aman. Foto yang sama (diperiksa dari hash isi berkasnya) dilewati.

## Alt text dan keterangan per foto

Tanpa keterangan, alt text berbunyi "Foto {nama tempat}". Itu memenuhi syarat terbit, tetapi alt text yang baik menjelaskan isi gambar. Untuk foto yang penting, isi `keterangan.csv`:

    file,alt_text,caption,credit,license_note
    museum-yadnya.jpg,Gerbang museum dengan arca penjaga,Pintu masuk museum,Budi Santoso,CC BY 4.0

Isi per berkas menimpa nilai dari opsi `--credit` dan `--license`. Beda kredit per foto cukup ditulis di sini.

## Yang dilakukan pada gambar

Foto yang lebih lebar dari 2000 piksel diperkecil (ubah dengan `--max-width`). Gambar disimpan ulang, sehingga metadata EXIF termasuk lokasi GPS ikut terbuang. Orientasi dari ponsel diterapkan lebih dulu agar foto tidak berputar. Berkas asli di folder impor tidak diubah. Nama berkas di server diacak.

Bila ekstensi PHP exif tidak aktif, JPG disalin apa adanya (metadata tidak dibuang) dan perintah mencatatnya di audit log.

## Setelah foto terpasang

Foto yang punya alt text dan kredit atau lisensi memenuhi syarat terbit. Tempat yang tadinya hanya kurang foto kini bisa diterbitkan lewat Terbit massal. Foto tampil di kartu, halaman detail, dan sebagai sorotan beranda. Di editor tempat, bagian Foto menampilkan semuanya dan Anda bisa memperbaiki alt text atau menghapus.
