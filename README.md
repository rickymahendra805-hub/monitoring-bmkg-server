# Monitoring BMKG

Versi awal website Monitoring Cuaca Indonesia untuk pengembangan lokal menggunakan Laragon dan PHP.

## Menjalankan
1. Extract folder `monitoring-bmkg` ke `C:\laragon\www\`
2. Jalankan Apache di Laragon.
3. Buka `http://localhost/monitoring-bmkg/`

## Sumber data
- Prakiraan cuaca: API publik BMKG `https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=...`
- Gempa bumi: `https://data.bmkg.go.id/DataMKG/TEWS/...`
- Deteksi lokasi: geolocation perangkat + OpenStreetMap Nominatim + kode wilayah Wilayah.id

`data/cuaca.json` hanya dipertahankan sebagai arsip pengembangan dan **tidak lagi dipakai** oleh halaman aplikasi. Hasil API disimpan sementara di `data/cache/` agar tidak membebani server BMKG.

## Skrip Python

| Skrip | Masukan | Keluaran |
| --- | --- | --- |
| `proses_netcdf.py` | `data/netcdf/OLR`, `data/netcdf/PWAT` (file `.waves.init.YYYYMMDD.nc`) | `assets/map/OLR`, `assets/map/PWAT` |
| `proses_hovmoller.py` | `data/netcdf/CFS/OLR/olr.waves.cfs.nc`, `data/netcdf/CFS/PWAT/pwat.waves.cfs.nc` | `assets/hovmoller/render/<OLR\|PWAT>/<YYYYMMDD>/` |
| `proses_kota.py` | `data/netcdf/OLR`, `data/netcdf/PWAT` + `data/wilayah.json` | `data/kota/` |

Batas analisis dan prakiraan pada Diagram Hovmoller dibaca dari atribut `lastObsTime` /
`lastObsInd` di dalam file NetCDF, bukan ditentukan manual. Gambar hasil render ditulis ke
folder `render/` sehingga tidak menimpa gambar resmi InAWMS yang sudah ada, dan tetap
terindeks otomatis oleh `galeri-produk.php` serta `inawms.php`.

```
python proses_hovmoller.py                       # semua sabuk lintang, kedua produk
python proses_hovmoller.py --produk OLR --pita eqtr wide
python proses_hovmoller.py --inisial 20261003 --belakang 120 --depan 45
```

Project ini menggunakan PHP tanpa database.
