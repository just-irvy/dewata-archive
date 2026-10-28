#!/usr/bin/env python3
"""
Menyusun combined_dataset_150_v2.csv dari tiga sumber mentah.

  A  bali_destinations_*.csv      hasil pencarian per kata kunci dan area (kategori = kata kunci, bukan fakta)
  B  dataset_tempat_wisata_bali   wisata menurut kabupaten, rating, tautan Google Maps
  C  data_traveloka               hotel dengan harga, tanpa koordinat

Aturan pemilihan sengaja eksplisit dan deterministik. Ubah QUOTAS atau aturan di bawah lalu jalankan ulang.
Tidak ada teks deskriptif yang dikarang. Ringkasan dibiarkan kosong untuk ditulis editor.

Pemakaian:
    python tools/curate_dataset.py --a A.csv --b B.csv --c C.csv --out combined_dataset_150_v2.csv
"""
import argparse, collections, csv, difflib, hashlib, json, re, sys
import numpy as np
import pandas as pd

KABUPATEN = ['Badung', 'Bangli', 'Buleleng', 'Denpasar', 'Gianyar', 'Jembrana', 'Karangasem', 'Klungkung', 'Tabanan']

# Jumlah baris per kabupaten. Wilayah sengaja lebih menentukan daripada popularitas (FILE 07).
QUOTAS = {
    'wisata': dict(Badung=8, Bangli=7, Buleleng=10, Denpasar=5, Gianyar=9, Jembrana=6, Karangasem=9, Klungkung=7, Tabanan=9),
    'kuliner': dict(Badung=4, Bangli=1, Buleleng=2, Denpasar=3, Gianyar=4, Jembrana=1, Karangasem=1, Klungkung=2, Tabanan=2),
    'kafe': dict(Badung=4, Bangli=1, Buleleng=2, Denpasar=3, Gianyar=4, Jembrana=1, Karangasem=1, Klungkung=2, Tabanan=2),
    'hotel': dict(Badung=6, Bangli=3, Buleleng=6, Denpasar=3, Gianyar=6, Jembrana=3, Karangasem=5, Klungkung=4, Tabanan=4),
}

# Area pencarian pada sumber A -> kabupaten. Label area pada A adalah kueri, bukan lokasi pasti,
# sehingga dipakai hanya sebagai pembanding terhadap kabupaten hasil koordinat.
AREA_KAB = {
    'Abiansemal': 'Badung', 'Amed': 'Karangasem', 'Amlapura': 'Karangasem', 'Bangli': 'Bangli', 'Banjar': 'Buleleng',
    'Bedugul': 'Tabanan', 'Blahbatuh': 'Gianyar', 'Canggu': 'Badung', 'Dawan': 'Klungkung',
    'Denpasar Barat': 'Denpasar', 'Denpasar Selatan': 'Denpasar', 'Denpasar Timur': 'Denpasar', 'Denpasar Utara': 'Denpasar',
    'Gerokgak': 'Buleleng', 'Gianyar': 'Gianyar', 'Gilimanuk': 'Jembrana', 'Jatiluwih': 'Tabanan', 'Jimbaran': 'Badung',
    'Kediri': 'Tabanan', 'Kintamani': 'Bangli', 'Kuta': 'Badung', 'Legian': 'Badung', 'Lovina': 'Buleleng',
    'Manggis': 'Karangasem', 'Marga': 'Tabanan', 'Medewi': 'Jembrana', 'Mengwi': 'Badung', 'Munduk': 'Buleleng',
    'Negara': 'Jembrana', 'Nusa Ceningan': 'Klungkung', 'Nusa Dua': 'Badung', 'Nusa Lembongan': 'Klungkung',
    'Nusa Penida': 'Klungkung', 'Payangan': 'Gianyar', 'Pekutatan': 'Jembrana', 'Penelokan': 'Bangli', 'Petang': 'Badung',
    'Rendang': 'Karangasem', 'Sanur': 'Denpasar', 'Selat': 'Karangasem', 'Selemadeg': 'Tabanan', 'Semarapura': 'Klungkung',
    'Seminyak': 'Badung', 'Seririt': 'Buleleng', 'Sidemen': 'Karangasem', 'Singaraja': 'Buleleng', 'Sukawati': 'Gianyar',
    'Susut': 'Bangli', 'Tabanan': 'Tabanan', 'Tegallalang': 'Gianyar', 'Tejakula': 'Buleleng', 'Tembuku': 'Bangli',
    'Tulamben': 'Karangasem', 'Ubud': 'Gianyar', 'Uluwatu': 'Badung',
}

# Label lokasi pada sumber C (Traveloka) -> kabupaten. Dipakai untuk memastikan pencocokan nama hotel ke sumber A
# tidak salah properti: nama umum seperti "Hotel Puri Bali" ada di banyak tempat.
C_LABEL_KAB = {
    'Kuta': 'Badung', 'Ubud': 'Gianyar', 'Seminyak': 'Badung', 'Legian': 'Badung', 'Sanur': 'Denpasar', 'Nusa Penida': 'Klungkung',
    'Jimbaran': 'Badung', 'West Denpasar': 'Denpasar', 'South Denpasar': 'Denpasar', 'North Denpasar': 'Denpasar', 'East Denpasar': 'Denpasar',
    'Nusa Dua Beach': 'Badung', 'Nusa Dua': 'Badung', 'Canggu': 'Badung', 'Monkey Forest': 'Gianyar', 'Tanjung Benoa': 'Badung', 'Tuban': 'Badung',
    'Lovina': 'Buleleng', 'Nusa Lembongan': 'Klungkung', 'Tegallalang': 'Gianyar', 'Kedewatan': 'Gianyar', 'Pecatu': 'Badung', 'Poppies': 'Badung',
    'Candidasa': 'Karangasem', 'Ungasan': 'Badung', 'Kintamani': 'Bangli', 'Payangan': 'Gianyar', 'Tampaksiring': 'Gianyar', 'Tanah Lot': 'Tabanan',
    'Menjangan': 'Buleleng', 'Tulamben': 'Karangasem', 'Amed': 'Karangasem', 'Sayan': 'Gianyar', 'Mengwi': 'Badung', 'Sebatu': 'Gianyar',
    'Negara': 'Jembrana', 'Singaraja': 'Buleleng', 'Pemuteran': 'Buleleng', 'Sukawati': 'Gianyar', 'Kerobokan': 'Badung', 'Tabanan Kota': 'Tabanan',
    'Kemenuh': 'Gianyar', 'Keramas': 'Gianyar', 'Seririt': 'Buleleng', 'Amlapura': 'Karangasem', 'Bedugul': 'Tabanan', 'Nusa Ceningan': 'Klungkung',
    'Mendoyo': 'Jembrana', 'Umalas': 'Badung', 'Munduk': 'Buleleng', 'Kerambitan': 'Tabanan', 'Batubulan': 'Gianyar', 'Selemadeg': 'Tabanan',
    'Gilimanuk': 'Jembrana', 'Kutuh': 'Badung', 'Tejakula': 'Buleleng', 'Gianyar Kota': 'Gianyar', 'Marga': 'Tabanan', 'Pekutatan': 'Jembrana',
    'Semarapura Kelod': 'Klungkung', 'Banjar': 'Buleleng', 'Padangbai': 'Karangasem', 'Klungkung': 'Klungkung', 'Saba': 'Gianyar', 'Abiansemal': 'Badung',
}

BOX = dict(lat=(-9.0, -8.0), lng=(114.4, 115.8))
SCRAPE_DATE_A = None  # diisi dari nama berkas bila memuat tanggal


def nf(s):
    s = str(s).lower().replace('&', ' and ')
    return ' '.join(re.sub(r'[^a-z0-9 ]+', ' ', s).split())


def haversine(lat1, lng1, lat2, lng2):
    p1, p2 = np.radians(lat1), np.radians(lat2)
    h = np.sin((p2 - p1) / 2) ** 2 + np.cos(p1) * np.cos(p2) * np.sin(np.radians(lng2 - lng1) / 2) ** 2
    return 2 * 6371 * np.arcsin(np.sqrt(h))


class KabupatenLocator:
    """Menentukan kabupaten dari koordinat dengan k tetangga terdekat pada titik B yang kabupatennya diketahui."""

    def __init__(self, lat, lng, kab, k=3):
        self.lat, self.lng, self.kab, self.k = np.asarray(lat), np.asarray(lng), np.asarray(kab), k

    def locate(self, la, lo):
        d = haversine(la, lo, self.lat, self.lng)
        idx = np.argsort(d)[: self.k]
        votes = collections.Counter(self.kab[idx]).most_common(1)[0][0]
        return votes, float(d[idx[0]])


def in_box(la, lo):
    return BOX['lat'][0] <= la <= BOX['lat'][1] and BOX['lng'][0] <= lo <= BOX['lng'][1]


def stable_key(text):
    return hashlib.sha1(str(text).encode()).hexdigest()


def clean_link(url):
    return url.split('?')[0]


def link_coords(url):
    m = re.search(r'!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)', url)
    return (float(m.group(1)), float(m.group(2))) if m else (None, None)


def hotel_type(name):
    n = name.lower()
    if re.search(r'\bvill?as?\b', n): return 'villa'
    if re.search(r'\bresort\b', n): return 'resort'
    if re.search(r'guest ?house|homestay|\binn\b|hostel|cottage|bungalow|losmen', n): return 'guesthouse'
    return 'hotel'


def theme_for(name, category):
    n = name.lower()
    if category in ('Kuliner',): return 'Kuliner'
    if category in ('Kafe',): return 'Kopi'
    if re.search(r'\b(pantai|beach|bay|teluk|tanjung)\b', n): return 'Pantai'
    if re.search(r'\b(pura|temple|candi|puri|tirta|goa gajah|taman ayun)\b', n): return 'Pura dan Arsitektur'
    if re.search(r'museum|monumen|desa adat|village|heritage|art centre|art center|galeri|gallery|traditional|rumah adat', n): return 'Warisan Budaya'
    if category == 'Wisata Rekreasi': return 'Rekreasi'
    if category == 'Wisata Budaya': return 'Warisan Budaya'
    return 'Alam' if category == 'Wisata Alam' else ''


JUNK_NAME = re.compile(r'\b(kantor|dinas|sekolah|toko|bank|apotek|klinik|puskesmas|kelurahan|kecamatan|stasiun|terminal|bengkel)\b', re.I)


def pick(df, quota, rank_cols, key):
    """Ambil sebanyak kuota per kabupaten menurut urutan rank_cols lalu hash stabil."""
    out = []
    for kab, n in quota.items():
        pool = df[df.kab == kab].sort_values(rank_cols + ['_h'], ascending=[False] * len(rank_cols) + [True])
        out.append(pool.head(n))
    return pd.concat(out) if out else df.head(0)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--a', required=True); ap.add_argument('--b', required=True); ap.add_argument('--c', required=True)
    ap.add_argument('--out', default='combined_dataset_150_v2.csv'); ap.add_argument('--report', default='curation_report.json')
    ap.add_argument('--a-date', default=None, help='tanggal pengambilan sumber A (YYYY-MM-DD), default dari nama berkas')
    args = ap.parse_args()

    rep = {'funnel': {}, 'excluded': collections.Counter()}
    a = pd.read_csv(args.a, dtype=str); b = pd.read_csv(args.b, dtype=str); c = pd.read_csv(args.c, dtype=str)

    m = re.search(r'(\d{4})(\d{2})(\d{2})', args.a)
    a_date = args.a_date or (f'{m.group(1)}-{m.group(2)}-{m.group(3)}' if m else '')

    # ---------- B: koordinat dari tautan Google (pin tempat), dedupe, filter kualitas
    b[['lat', 'lng']] = b.link.apply(lambda u: pd.Series(link_coords(u)))
    rep['funnel']['B_total'] = len(b)
    rep['funnel']['B_koordinat_kolom_rusak_dipulihkan_dari_tautan'] = int((~b.latitude.str.match(r'^-?\d+\.\d+$')).sum())
    b['kab'] = b.kabupaten_kota.str.replace('Kabupaten ', '').str.replace('Kota ', '')
    b['rating'] = pd.to_numeric(b.rating, errors='coerce')
    b['n'] = b.nama.map(nf)
    b = b[b.lat.notna()]
    b = b[b.apply(lambda r: in_box(r.lat, r.lng), axis=1)]
    before = len(b)
    b = b.sort_values('rating', ascending=False).drop_duplicates('n').drop_duplicates(['lat', 'lng'])
    rep['excluded']['B duplikat nama atau koordinat'] = before - len(b)
    j = b.nama.str.contains(JUNK_NAME)
    rep['excluded']['B bukan objek wisata (kantor, toko, dan sebagainya)'] = int(j.sum()); b = b[~j]
    low = b.rating.isna() | (b.rating < 4.3) | (b.rating >= 5.0)
    rep['excluded']['B rating <4,3, kosong, atau tepat 5,0 (sedikit ulasan)'] = int(low.sum()); b = b[~low]
    rep['funnel']['B_memenuhi_syarat'] = len(b)

    loc = KabupatenLocator(b.lat.values, b.lng.values, b.kab.values)

    # ---------- A: bersihkan, tentukan kabupaten dari koordinat, bandingkan dengan label area
    a['lat'] = a.coordinates_lat.astype(float); a['lng'] = a.coordinates_lng.astype(float)
    rep['funnel']['A_total'] = len(a)
    a = a[a.apply(lambda r: in_box(r.lat, r.lng), axis=1)]
    a['n'] = a.place_name.map(nf)
    a['area'] = a.location_region.str.replace(' Bali', '', regex=False)
    a['kab_area'] = a.area.map(AREA_KAB)
    res = a.apply(lambda r: loc.locate(r.lat, r.lng), axis=1)
    a['kab'] = [x[0] for x in res]; a['nn_km'] = [x[1] for x in res]
    a = a.drop_duplicates(['n']).drop_duplicates(['lat', 'lng'])
    a = a[(a.place_name.str.len() > 4) & (a.place_name != a.place_name.str.lower())]
    rep['funnel']['A_setelah_dedupe_dan_nama_layak'] = len(a)
    agree = a.kab == a.kab_area
    rep['funnel']['A_kabupaten_koordinat_cocok_dengan_label_area'] = int(agree.sum())
    rep['excluded']['A kabupaten dari koordinat berbeda dengan label area (label kueri tidak bisa dipercaya)'] = int((~agree).sum())
    a = a[agree & (a.nn_km <= 15)]
    a['_h'] = a.place_id.map(stable_key)
    a['lengkap'] = (a.price_min.notna().astype(int) + a.contact_wa.notna().astype(int))

    FOOD = re.compile(r'warung|restaurant|resto|restoran|nasi|bakso|mie|ayam|bebek|babi|sate|kitchen|depot|rumah makan|eatery|grill|seafood|pizza|burger|sushi|ramen|bistro|dapur|bar\b|cafe|kafe|lesehan|soto|rawon|pecel|gado|jukung|warteg|kedai|ikan|laut|bbq|steak|pasta|noodle|food|dine|dining', re.I)
    COFFEE = re.compile(r'coffee|coffe|kopi|cafe|kafe|roast|espresso|brew|tea|bakery|eatery', re.I)
    LODGE = re.compile(r'hotel|villa|resort|homestay|guest ?house|\binn\b|cottage|bungalow|lodge|hostel|\bstay\b|suite|residence|bnb|b&b|losmen|penginapan', re.I)

    kuliner = a[(a.category == 'Kuliner') & a.place_name.str.contains(FOOD)]
    kafe = a[(a.category == 'Coffee Shop') & a.place_name.str.contains(COFFEE)]
    lodging = a[(a.category == 'Akomodasi') & a.place_name.str.contains(LODGE)]
    rep['funnel'].update(A_kuliner_layak=len(kuliner), A_kafe_layak=len(kafe), A_akomodasi_layak=len(lodging))

    # ---------- C: harga hotel, dicocokkan ke A (koordinat) dengan nama persis
    rp = lambda v: int(re.sub(r'[^\d]', '', v))
    c['row'] = np.arange(2, len(c) + 2)
    c['disc'] = c['Price after discount'].map(rp); c['orig'] = c['Original price'].map(rp)
    c['n'] = c['Hotel Name'].map(nf)
    rep['funnel']['C_total'] = len(c)
    c = c.sort_values('disc').drop_duplicates('n')
    a_acc = a[a.category == 'Akomodasi']
    # cocokkan hanya dengan kesetaraan nama persis atau penghilangan kata "bali" dan "hotel" di akhir. Tidak ada tebakan fuzzy.
    def key2(s): return re.sub(r'\b(bali)\b', '', s).strip()
    a_idx = {}
    for _, r in a_acc.iterrows(): a_idx.setdefault(key2(r.n), r)
    matches = []
    rep['funnel']['C_cocok_nama_ke_A'] = 0
    rep['excluded']['C cocok nama tetapi label lokasi Traveloka bertentangan dengan koordinat A (kemungkinan properti lain bernama sama)'] = 0
    rep['excluded']['C cocok nama tetapi label lokasi tidak dikenal (tidak bisa diverifikasi)'] = 0
    for _, r in c.iterrows():
        q = a_idx.get(key2(r.n))
        if q is None: continue
        rep['funnel']['C_cocok_nama_ke_A'] += 1
        label = r.location.replace(', Bali', '').strip()
        kab_c = C_LABEL_KAB.get(label)
        if kab_c is None:
            rep['excluded']['C cocok nama tetapi label lokasi tidak dikenal (tidak bisa diverifikasi)'] += 1; continue
        if kab_c != q.kab:
            rep['excluded']['C cocok nama tetapi label lokasi Traveloka bertentangan dengan koordinat A (kemungkinan properti lain bernama sama)'] += 1; continue
        matches.append((r, q))
    rep['funnel']['C_hotel_terverifikasi_cocok_ke_koordinat_A'] = len(matches)

    # ---------- susun kandidat hotel
    hotels = []
    used = set()
    for r, q in matches:
        hotels.append(dict(id=f'C{int(r.row)}', name=r['Hotel Name'].strip(), kab=q.kab, area=q.area, lat=q.lat, lng=q.lng,
                           price_min=int(r.disc), price_max='', price_source='Traveloka', hotel_type=hotel_type(r['Hotel Name']),
                           source_url='', source_date='', note=f"Harga Traveloka (tanpa tanggal), harga normal Rp{int(r.orig):,}; koordinat dari sumber A {q.place_id[:8]}; rating Traveloka {r.Rating}".replace(',', '.'),
                           prio=0, _h=stable_key(r['Hotel Name'])))
        used.add(q.n)
    for _, q in lodging.iterrows():
        if q.n in used: continue
        pm = pd.to_numeric(q.price_min, errors='coerce')
        hotels.append(dict(id=f'A{q.place_id[:8]}', name=q.place_name.strip(), kab=q.kab, area=q.area, lat=q.lat, lng=q.lng,
                           price_min=int(pm) if pd.notna(pm) and pm >= 100000 else '', price_max='', price_source='Bali Destinations' if pd.notna(pm) and pm >= 100000 else '',
                           hotel_type=hotel_type(q.place_name), source_url='', source_date=a_date,
                           note=f'Sumber A {q.place_id[:8]}; harga sumber A tidak jelas satuannya', prio=1, _h=q._h))
    hdf = pd.DataFrame(hotels)

    out = []

    def add(df, category, theme_fn, extra):
        for _, r in df.iterrows():
            out.append(dict(id=r['id'], name=r['name'], category=category, kabupaten=r['kab'], location_area=r['area'],
                            latitude=f"{r['lat']:.7f}", longitude=f"{r['lng']:.7f}",
                            price_min=extra.get('price_min', lambda r: '')(r), price_max=extra.get('price_max', lambda r: '')(r),
                            price_source=extra.get('price_source', lambda r: '')(r), theme=theme_fn(r, category),
                            hotel_type=extra.get('hotel_type', lambda r: '')(r), source_url=r.get('source_url', ''),
                            source_date=r.get('source_date', ''), summary='', note=r.get('note', '')))

    # wisata dari B, urut kategori bergilir agar beragam
    b['id'] = ['B' + str(i + 2) for i in b.index]
    b['name'] = b.nama.str.strip(); b['area'] = b.kab
    b['source_url'] = b.link.map(clean_link); b['source_date'] = ''
    b['note'] = b.apply(lambda r: f"Sumber B; rating Google {r.rating:g}/5; koordinat dari tautan Google Maps", axis=1)
    b['_h'] = b.n.map(stable_key)
    b['cat'] = 'Wisata ' + b.kategori
    chosen_b = []
    for kab, n in QUOTAS['wisata'].items():
        pool = b[b.kab == kab].sort_values(['rating', '_h'], ascending=[False, True])
        by = {k: list(v.index) for k, v in pool.groupby('kategori')}
        order = [k for k in ['Alam', 'Budaya', 'Rekreasi', 'Umum'] if k in by]
        take = []
        while len(take) < n and any(by.values()):
            for k in order:
                if by.get(k) and len(take) < n: take.append(by[k].pop(0))
        chosen_b += take
    wisata = b.loc[chosen_b]
    for _, r in wisata.iterrows():
        out.append(dict(id=r['id'], name=r['name'], category=r['cat'], kabupaten=r['kab'], location_area=r['area'],
                        latitude=f"{r['lat']:.7f}", longitude=f"{r['lng']:.7f}", price_min='', price_max='', price_source='',
                        theme=theme_for(r['name'], r['cat']), hotel_type='', source_url=r['source_url'], source_date='', summary='', note=r['note']))

    def to_rows(df, category, quota_key):
        df = df.copy(); df['id'] = 'A' + df.place_id.str[:8]; df['name'] = df.place_name.str.strip()
        df['source_url'] = ''; df['source_date'] = a_date; df['note'] = 'Sumber A ' + df.place_id.str[:8] + '; harga sumber A diabaikan'
        sel = pick(df, QUOTAS[quota_key], ['lengkap'], None)
        for _, r in sel.iterrows():
            out.append(dict(id=r['id'], name=r['name'], category=category, kabupaten=r['kab'], location_area=r['area'],
                            latitude=f"{r['lat']:.7f}", longitude=f"{r['lng']:.7f}", price_min='', price_max='', price_source='',
                            theme=theme_for(r['name'], category), hotel_type='', source_url='', source_date=a_date, summary='', note=r['note']))
        return sel

    to_rows(kuliner, 'Kuliner', 'kuliner'); to_rows(kafe, 'Kafe', 'kafe')

    # hotel: prioritas yang punya harga Traveloka, lalu sisanya, per kuota kabupaten
    hsel = []
    for kab, n in QUOTAS['hotel'].items():
        pool = hdf[hdf.kab == kab].copy()
        pool['_has_price'] = (pool.price_min != '').astype(int)
        pool = pool.sort_values(['prio', '_has_price', '_h'], ascending=[True, False, True])
        hsel.append(pool.head(n))
    hsel = pd.concat(hsel)
    for _, r in hsel.iterrows():
        out.append(dict(id=r['id'], name=r['name'], category='Hotel', kabupaten=r['kab'], location_area=r['area'],
                        latitude=f"{r['lat']:.7f}", longitude=f"{r['lng']:.7f}", price_min=r['price_min'], price_max=r['price_max'],
                        price_source=r['price_source'], theme='', hotel_type=r['hotel_type'], source_url=r['source_url'],
                        source_date=r['source_date'], summary='', note=r['note']))

    cols = ['id', 'name', 'category', 'kabupaten', 'location_area', 'latitude', 'longitude', 'price_min', 'price_max', 'price_source',
            'theme', 'hotel_type', 'source_url', 'source_date', 'summary', 'note']
    df = pd.DataFrame(out, columns=cols)
    df = df.sort_values(['category', 'kabupaten', 'name']).reset_index(drop=True)
    df.to_csv(args.out, index=False, quoting=csv.QUOTE_MINIMAL)

    rep['hasil'] = dict(total=len(df), per_kategori=df.category.value_counts().to_dict(),
                        per_kabupaten=df.kabupaten.value_counts().to_dict(),
                        matriks=pd.crosstab(df.kabupaten, df.category).to_dict('index'),
                        hotel_dengan_harga_traveloka=int((df.price_source == 'Traveloka').sum()),
                        hotel_dengan_harga_sumber_A=int((df.price_source == 'Bali Destinations').sum()),
                        hotel_tanpa_harga=int(((df.category == 'Hotel') & (df.price_source == '')).sum()),
                        baris_dengan_tautan_sumber=int((df.source_url != '').sum()))
    rep['excluded'] = dict(rep['excluded'])
    shortfall = {k: {kab: n - int(((df.category == cat) & (df.kabupaten == kab)).sum()) for kab, n in QUOTAS[k].items()
                     if n - int(((df.category == cat) & (df.kabupaten == kab)).sum()) > 0}
                 for k, cat in [('kuliner', 'Kuliner'), ('kafe', 'Kafe'), ('hotel', 'Hotel')]}
    shortfall['wisata'] = {kab: n - int(df[(df.category.str.startswith('Wisata')) & (df.kabupaten == kab)].shape[0]) for kab, n in QUOTAS['wisata'].items()
                           if n - int(df[(df.category.str.startswith('Wisata')) & (df.kabupaten == kab)].shape[0]) > 0}
    rep['kuota_tidak_terpenuhi'] = shortfall
    json.dump(rep, open(args.report, 'w'), ensure_ascii=False, indent=2)
    print(json.dumps(rep, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
