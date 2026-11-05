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
