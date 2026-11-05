# Asisten chat Gemini (Fase 6)

Asisten menjawab dari katalog terbit lewat tool allowlist. Fitur utama situs tetap berjalan
tanpa Gemini: bila `ARCHIVE_CHATBOT_ENABLED=false` atau `GEMINI_API_KEY` kosong, widget tidak
dirender dan `POST /chat` mengembalikan 404.

## Alur

Browser (widget Alpine) -> `POST /chat` (CSRF + `throttle:chat`) -> `ChatRequest` ->
`ChatOrchestrator` <-> `ChatModel` (Gemini) dan `ChatTools` (database).

- Percakapan tidak disimpan. Browser mengirim ulang riwayat (maks. 10 pesan, tiap pesan dipotong)
  dan riwayat hanya hidup di memori halaman (bukan localStorage).
- Log hanya mencatat kode status. Isi pertanyaan dan jawaban tidak dicatat.
- Yang dikirim ke Google: pertanyaan pengguna, riwayat singkat, dan hasil tool (data katalog terbit).
  Tidak ada email, nama, atau data akun. Cantumkan ini di kebijakan privasi sebelum production.

## Tool

| Tool | Fungsi |
|---|---|
| `get_catalog_options` | Slug wilayah, kategori, tema, dan jenis hotel yang sah |
| `search_places` | Cari tempat terbit (wilayah, kategori, tema, kata kunci) memakai `PlaceSearch` |
| `get_place_details` | Detail satu tempat terbit menurut slug |
| `search_hotels` | Cari hotel terbit menurut wilayah, jenis, dan harga per kamar per malam memakai `HotelSearch` |

Tool tidak menerima SQL, URL, atau nama berkas. Argumen divalidasi dan slug dicocokkan dengan
data terbit. Kartu tempat dan hotel dibuat server dari database, bukan dari teks model.

## Pengaman

- `PriceGuard`: nominal rupiah pada jawaban harus berasal dari hasil tool atau budget pengguna.
  Bila tidak, jawaban diberi catatan peringatan.
- Rate limit: 8 per menit, 100 per hari (login) atau 40 per hari (tamu), plus batas harian global
  `ARCHIVE_CHATBOT_DAILY_CAP`.
- Tanpa total biaya trip, tanpa klaim ketersediaan kamar (sesuai FILE 02 dan FILE 09).

## Mengubah isi

- FAQ, sapaan, dan tombol pertanyaan cepat: `config/chatbot.php`.
- Aturan perilaku model: `app/Services/Chat/ChatPrompt.php`.
- Ganti model: `GEMINI_MODEL` di `.env` (periksa nama model terbaru di dokumentasi Gemini).
- Ganti penyedia: tulis kelas baru yang mengimplementasikan `ChatModel`, ubah satu binding di
  `ArchiveServiceProvider`.

## Tes

`php artisan test --filter=Chat` dan `php artisan test --filter=PriceGuard`.
`npm run test:js` untuk helper widget.


## Percakapan tersimpan (pengguna login)

Tamu: percakapan hanya hidup di memori halaman dan hilang saat pindah halaman atau menutup tab. Tidak ada yang disimpan, di browser maupun di server.

Pengguna login: setiap pertanyaan dan jawaban yang berhasil disimpan di tabel chat_messages (satu utas per akun) dan dimuat ulang di widget pada setiap halaman. Konteks untuk Gemini diambil dari salinan server, bukan dari kiriman browser, sehingga riwayat tidak bisa dipalsukan. Kartu tempat dan hotel disimpan sebagai kunci (place:slug, hotel:slug) dan dibangun ulang dari katalog terbit saat dimuat: harga yang sudah berubah tampil dengan angka terbaru, dan record yang tidak lagi terbit tidak muncul. Angka di dalam teks jawaban lama tidak ikut diperbarui, dan widget menandai percakapan lama dengan catatan itu.

Batas: 200 pesan per akun (chatbot.max_stored_messages), 30 pesan terakhir dimuat di widget (chatbot.widget_messages), dan pesan lebih tua dari ARCHIVE_CHATBOT_RETENTION_DAYS (bawaan 30 hari) dihapus oleh archive:purge-chat yang dijadwalkan pukul 03.30. Tombol "Mulai baru" menghapus seluruh percakapan pengguna itu. Menghapus akun menghapus percakapannya (cascade).

Privasi: halaman Kebijakan Privasi wajib menyebut bahwa percakapan pengguna login disimpan di server selama masa simpan itu, dan bahwa isi pertanyaan dikirim ke Google Gemini untuk diproses.

## Tampilan C2

Widget bukan jendela mengambang. Bilah tipis selalu ada di tepi bawah semua halaman publik dan menjadi satu-satunya tempat mengetik: tertutup, tombolnya "Buka"; terbuka, tombolnya "Kirim". Menekan Buka (atau Enter) memunculkan jendela percakapan: di desktop mengisi separuh kanan layar dari atas sampai bilah, di ponsel naik dari bawah selebar layar. Bilah tidak bergerak, dan sisa halaman diredupkan 15 persen; klik di luar jendela menutupnya. Escape atau tombol tutup menutupnya. Halaman yang sudah punya bilah aksi sendiri di ponsel (editor planner) memakai `chat-bar="desktop"` pada layout: di ponsel bilah tidak tampil dan jendela menempel ke tepi bawah; `chat-bar="none"` mematikan bilah tetapi jendela tetap bisa dibuka lewat event `archive-chat-ask`.
