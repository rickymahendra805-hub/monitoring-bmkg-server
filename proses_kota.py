# ============================================================
# EKSTRAKSI TIME SERIES KOTA DARI NETCDF
# BMKG KEMAYORAN - momitoring-bmkg2
#
# Sumber:
#   data/netcdf/OLR/<init>/olr.waves.init.<init>.nc
#   data/netcdf/PWAT/<init>/pwat.waves.init.<init>.nc
#   data/wilayah.json  (nama kota + lat/lng)
#
# Hasil:
#   data/kota/<VAR>_<init>.json
#     {
#       "init": "...", "variabel": "...", "unit": "...",
#       "tanggal": ["YYYY-MM-DD", ...],
#       "kota": {
#         "<Nama Kota>": {
#           "lat": ..., "lng": ...,
#           "total": [...], "mjo": [...], "kelvin": [...],
#           "er": [...], "low": [...], "mrgtd": [...]
#         }, ...
#       }
#     }
#
# Nilai diambil di titik grid terdekat dari koordinat kota.
# Jalankan ulang setiap ada NetCDF init baru:
#   .venv/Scripts/python.exe proses_kota.py
# ============================================================

from pathlib import Path
import json
import numpy as np
import xarray as xr


# ============================================================
# KONFIGURASI
# ============================================================

BASE_DIR = Path(__file__).resolve().parent

NETCDF_DIR = BASE_DIR / "data" / "netcdf"
WILAYAH_FILE = BASE_DIR / "data" / "wilayah.json"
OUTPUT_DIR = BASE_DIR / "data" / "kota"

VARIABEL = {
    "OLR": {"prefix": "olr.waves.init.", "main": "olr", "unit": "W m^-2"},
    "PWAT": {"prefix": "pwat.waves.init.", "main": "pr_wtr", "unit": "kg m^-2"},
}

KOMPONEN = ["mjo", "kelvin", "er", "low", "mrgtd"]


# ============================================================
# FUNGSI BANTU
# ============================================================

def load_cities():
    """Membaca daftar kota + koordinat dari wilayah.json."""

    if not WILAYAH_FILE.exists():
        raise FileNotFoundError(f"wilayah.json tidak ditemukan: {WILAYAH_FILE}")

    with open(WILAYAH_FILE, encoding="utf-8") as f:
        data = json.load(f)

    cities = []

    for item in data:
        nama = str(item.get("nama", "")).strip()
        lat = item.get("lat")
        lng = item.get("lng")

        if nama and lat is not None and lng is not None:
            cities.append({"nama": nama, "lat": float(lat), "lng": float(lng)})

    return cities


def find_nc_files(var_name, prefix):
    """Mencari semua file .nc di semua folder init untuk satu variabel."""

    root = NETCDF_DIR / var_name

    if not root.exists():
        return []

    files = sorted(root.rglob(f"{prefix}*.nc"))

    return files


def tanggal_init_dari_nama(path):
    """Mengambil tanggal init 8 digit dari nama file, mis. ...20261006.nc."""

    for part in Path(path).stem.split("."):
        if len(part) == 8 and part.isdigit():
            return part

    return "unknown"


def koordinat_0360(lng):
    """Konversi longitude ke konvensi 0-360 (NetCDF memakai 0-360)."""

    lng = float(lng) % 360.0

    return lng


def ambil_seri(da, lat, lng):
    """Mengambil time series di titik grid terdekat."""

    pilihan = da.sel(lat=lat, lon=lng, method="nearest")

    nilai = np.asarray(pilihan.values, dtype=float).ravel()
    nilai[~np.isfinite(nilai)] = None

    return [None if v is None else round(float(v), 2) for v in nilai]


def proses_file(nc_path, var_name, main_var, unit, cities):
    """Mengekstrak semua kota dari satu file NetCDF."""

    ds = xr.open_dataset(nc_path, decode_times=True)

    if "time" not in ds.coords:
        print(f"  [SKIP] tidak ada koordinat time: {nc_path.name}")
        ds.close()
        return None

    tanggal = [str(d)[:10] for d in ds["time"].values]

    hasil_kota = {}

    for city in cities:
        nama = city["nama"]
        lat = city["lat"]
        lng = koordinat_0360(city["lng"])

        data_kota = {"lat": city["lat"], "lng": city["lng"]}

        try:
            if main_var in ds.data_vars:
                data_kota["total"] = ambil_seri(ds[main_var], lat, lng)
            else:
                print(f"  [SKIP] variabel utama {main_var} tidak ada di {nc_path.name}")
                ds.close()
                return None

            for kom in KOMPONEN:
                if kom in ds.data_vars:
                    data_kota[kom] = ambil_seri(ds[kom], lat, lng)
                else:
                    data_kota[kom] = [None] * len(tanggal)

            hasil_kota[nama] = data_kota

        except Exception as e:
            print(f"  [ERROR] kota {nama}: {e}")

    ds.close()

    return {
        "init": tanggal_init_dari_nama(nc_path),
        "variabel": var_name,
        "unit": unit,
        "tanggal": tanggal,
        "kota": hasil_kota,
    }


# ============================================================
# MAIN
# ============================================================

def main():
    print("=" * 60)
    print("     EKSTRAKSI TIME SERIES KOTA (NETCDF -> JSON)")
    print("=" * 60)

    cities = load_cities()
    print(f"Kota dari wilayah.json : {len(cities)}")

    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    for var_name, cfg in VARIABEL.items():
        files = find_nc_files(var_name, cfg["prefix"])
        print()
        print(f"[{var_name}] file ditemukan: {len(files)}")

        for nc_path in files:
            init = tanggal_init_dari_nama(nc_path)
            print(f"  -> {nc_path.name}")

            hasil = proses_file(
                nc_path,
                var_name,
                cfg["main"],
                cfg["unit"],
                cities,
            )

            if hasil is None:
                continue

            out_file = OUTPUT_DIR / f"{var_name}_{init}.json"

            with open(out_file, "w", encoding="utf-8") as f:
                json.dump(hasil, f, separators=(",", ":"))

            n_kota = len(hasil["kota"])
            n_hari = len(hasil["tanggal"])
            ukuran = out_file.stat().st_size / 1024

            print(
                f"     [OK] {out_file.name} | {n_kota} kota | "
                f"{n_hari} hari | {ukuran:.0f} KB"
            )

    print()
    print("=" * 60)
    print("SELESAI")
    print("=" * 60)


if __name__ == "__main__":
    main()
