# Rencana desain

## Arah

Brief mengunci arah visual: kertas, tinta, garis tipis, serif, dan aksen mesin tik. Palet dan huruf mengikuti FILE 4 persis. Palet krem dan terakota serta tata letak surat kabar termasuk gaya yang paling sering dihasilkan secara otomatis, jadi pembedanya ada pada disiplin penerapan, bukan pada warna.

## Token

Warna: Archive Paper #F6F0E4, Soft White #FAF7F0, Ink #202723, Archive Rust #9C4935, Moss #5C6B56, Brass #B18A50. Tambahan yang tidak ada di blueprint: Ink Soft #4B554F untuk teks sekunder (kontras 6,8), Rust Deep #843A29 untuk hover (7,1), dan Oxblood #7A2418 untuk error (8,8). Brass hanya untuk garis karena kontrasnya 2,8.

Huruf: Playfair Display untuk judul, Inter untuk antarmuka dan paragraf, Courier Prime untuk tanggal, catatan kaki, dan label verifikasi. Paragraf panjang dan kontrol form tidak pernah memakai mesin tik.

## Tata letak

Konten rata kiri. Lebar baca teks dibatasi sekitar 62 karakter. Masthead berupa nameplate besar rata kiri di bawah baris tanggal, dengan garis ganda sebagai penutup, mengikuti kepala surat kabar. Tanggal pada baris itu adalah tanggal hari ini, bukan ornamen.

## Yang sengaja tidak dilakukan

Tidak ada label huruf kapital di atas setiap judul. Huruf kapital hanya untuk kelas .meta. Tidak ada penomoran 01, 02, 03 pada konten yang bukan urutan. Tidak ada kartu seragam dengan bayangan lembut. Tidak ada animasi masuk per seksi. Gerak hanya pada respons terhadap aksi (hover, buka menu) dan dimatikan saat pengguna memilih reduced motion. Tidak ada panah pada teks tautan.

## Dasar aksesibilitas

Tombol dan target sentuh minimal 44 piksel. Fokus terlihat berupa garis tinta 2 piksel. Error dibedakan oleh ikon, garis 3 piksel, dan teks, bukan warna saja. Tautan lewati-ke-konten tersedia. Menu mobile memakai aria-expanded dan tombol Planner tetap terlihat tanpa membuka menu.

## Tinjauan sendiri

Rencana awal memakai tagline di atas judul halaman dan nomor dokumen di setiap kartu. Keduanya dibuang karena tidak membawa informasi. Nomor arsip akan kembali di Fase 2 hanya bila memang ada nomor yang bermakna, misalnya ID catatan lapangan dari data.
