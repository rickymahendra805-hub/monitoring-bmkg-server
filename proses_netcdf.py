# ============================================================
# PROSES NETCDF BMKG
# Membuat visualisasi:
# OLR / PWAT
# MJO / KELVIN / ER / LOW / MRGTD
# + Time Series
#
# Hasil render per file inisial:
#   - peta globe (40 LS - 40 LU, seluruh bujur)
#   - peta khusus Indonesia (94 - 141 BT, 12 LS - 8 LU)
#   - seluruh lead time prakiraan (harian lalu mingguan), bukan hanya
#     langkah waktu pertama
#   - label "Initial" dan "Forecast" langsung di dalam gambar
# ============================================================

from pathlib import Path
import argparse
import json
import math
import sys
import warnings
from multiprocessing import Pool, cpu_count

warnings.filterwarnings("ignore")

import numpy as np
import xarray as xr
import matplotlib

matplotlib.use("Agg")

import matplotlib.pyplot as plt

try:
    import cartopy.crs as ccrs
    import cartopy.feature as cfeature

    PAKAI_CARTOPY = True
except ImportError:
    PAKAI_CARTOPY = False


# ============================================================
# KONFIGURASI PROJECT
# ============================================================

BASE_DIR = Path(__file__).resolve().parent

NETCDF_DIR = BASE_DIR / "data" / "netcdf"
OUTPUT_DIR = BASE_DIR / "assets" / "map"

OLR_DIR = NETCDF_DIR / "OLR"
PWAT_DIR = NETCDF_DIR / "PWAT"

OLR_OUTPUT = OUTPUT_DIR / "OLR"
PWAT_OUTPUT = OUTPUT_DIR / "PWAT"

# Garis pantai Natural Earth 110m (unduhan lokal, dipakai bila Cartopy
# tidak tersedia).
COASTLINE_GEOJSON = BASE_DIR / "assets" / "geodata" / "coastline110.geojson"

# Warna dasar peta: laut tampak berbeda dari daratan.
WARNA_LAUT = "#a6cee3"
WARNA_DARAT = "#e6e6e6"
WARNA_PANTAI = "black"

# Region yang dirender. Globe memakai seluruh cakupan data,
# versi Indonesia dibuat agar wilayah kita tidak terlalu kecil.
REGION = {
    "dunia": [-180.0, 180.0, -40.0, 40.0],
    "indonesia": [94.0, 141.0, -12.0, 8.0],
}
# Kebijakan lead time: harian pada 14 hari pertama sejak tanggal inisial,
# kemudian diambil per 7 hari sampai horizon akhir file.
LEAD_HARIAN = 14
LEAD_MINGGU = 7

# Nama variabel gelombang beserta label yang tampil di judul gambar.
VARIABEL_GELOMBANG = {
    "mjo": "MJO",
    "kelvin": "Kelvin Wave",
    "er": "Equatorial Rossby Wave",
    "low": "Low Frequency",
    "mrgtd": "MRG / TD",
}

NAMA_BULAN = [
    "",
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
]

_COASTLINE_CACHE = None


# ============================================================
# GARIS PANTAI (FALLBACK TANPA CARTOPY)
# ============================================================

def load_coastline():
    """
    Memuat garis pantai dari GeoJSON Natural Earth.
    Koordinat disimpan sebagai daftar array (lon, lat).
    """

    global _COASTLINE_CACHE

    if _COASTLINE_CACHE is not None:
        return _COASTLINE_CACHE

    if not COASTLINE_GEOJSON.exists():
        print(f"[WARNING] GeoJSON garis pantai tidak ditemukan: {COASTLINE_GEOJSON}")
        _COASTLINE_CACHE = []
        return _COASTLINE_CACHE

    try:
        with open(COASTLINE_GEOJSON, encoding="utf-8") as f:
            geo = json.load(f)

        lines = []

        def add_coords(coords):
            arr = np.asarray(coords, dtype=float)
            if arr.ndim == 2 and arr.shape[0] >= 2 and arr.shape[1] >= 2:
                lines.append(arr[:, :2])

        for feature in geo.get("features", []):
            geometry = feature.get("geometry", {})
            gtype = geometry.get("type")
            coords = geometry.get("coordinates", [])

            if gtype == "LineString":
                add_coords(coords)
            elif gtype == "MultiLineString":
                for line in coords:
                    add_coords(line)
            elif gtype == "Polygon":
                for ring in coords:
                    add_coords(ring)
            elif gtype == "MultiPolygon":
                for polygon in coords:
                    for ring in polygon:
                        add_coords(ring)

        _COASTLINE_CACHE = lines

        print(f"Garis pantai dimuat: {len(lines)} segmen.")

    except Exception as e:
        print(f"[WARNING] Gagal memuat garis pantai: {e}")
        _COASTLINE_CACHE = []

    return _COASTLINE_CACHE


def draw_coastline(ax, lon_min, lon_max):
    """
    Menggambar garis pantai di atas axes biasa (fallback tanpa Cartopy).
    Data NetCDF memakai bujur 0..360, sedangkan Natural Earth
    memakai -180..180, sehingga perlu digeser bila perlu.
    """

    coastline = load_coastline()

    if not coastline:
        return

    perlu_geser = lon_max > 180

    for arr in coastline:
        x = arr[:, 0].copy()
        y = arr[:, 1]

        if perlu_geser:
            x = np.where(x < 0, x + 360.0, x)

        ax.plot(x, y, color=WARNA_PANTAI, linewidth=0.55, alpha=0.75, zorder=5)


# ============================================================
# FUNGSI BANTU
# ============================================================

def find_all_nc(folder, prefix):
    """
    Mencari seluruh file NetCDF berdasarkan prefix,
    diurutkan dari tanggal terlama ke terbaru.
    Contoh:
    olr.waves.init.20261006.nc
    pwat.waves.init.20261006.nc
    """

    if not folder.exists():
        return []

    return sorted(folder.rglob(f"{prefix}*.nc"), key=lambda p: p.name)


def tanggal_dari_nc(nc_file):
    """
    Mengambil tanggal inisialisasi dari nama file NetCDF.
    """

    for part in nc_file.stem.split("."):
        if len(part) == 8 and part.isdigit():
            return part

    return "20261006"


def buat_folder_output(root, tanggal):
    """
    Output dibuat berdasarkan tanggal inisialisasi.
    """

    folder = root / tanggal
    folder.mkdir(parents=True, exist_ok=True)

    return folder


def get_variable(ds, names):
    """
    Mencari nama variable yang tersedia.
    """

    for name in names:
        if name in ds.variables:
            return ds[name]

    return None


def satuan_variabel(da, cadangan=""):
    """
    Satuan terbaca dari atribut NetCDF, dirapikan ke bentuk superskrip.
    Dipakai untuk label legenda garis karena berkas OLR dan PWAT memakai
    satuan berbeda untuk variabel gelombang yang sama.
    """

    teks = str((da.attrs or {}).get("units", cadangan))

    return teks.replace("m-2", "m⁻²")


def clean_data(values):
    """
    Mengubah data menjadi array numpy dan menangani NaN / infinite.
    """

    arr = np.asarray(values, dtype=float)
    arr[~np.isfinite(arr)] = np.nan

    return arr


def tanggal_indonesia(yyyymmdd):
    """
    20261017 -> 17 Oktober 2026
    """

    tahun = int(yyyymmdd[0:4])
    bulan = int(yyyymmdd[4:6])
    hari = int(yyyymmdd[6:8])

    return f"{hari} {NAMA_BULAN[bulan]} {tahun}"


def daftar_lead(da, tanggal_init):
    """
    Menentukan langkah waktu yang akan dirender untuk satu variabel.

    Dimensi waktu pada file inisial mencakup masa lalu (dipakai untuk
    filter gelombang), jadi prakiraan diambil mulai tanggal inisial,
    harian selama LEAD_HARIAN hari lalu dilanjutkan per LEAD_MINGGU hari
    sampai akhir horizon.

    Hasil: daftar (indeks, 'YYYYMMDD', lead_hari).
    """

    if "time" not in da.dims:
        return [(0, tanggal_init, 0)]

    waktu = np.asarray(da["time"].values, dtype="datetime64[ns]")

    tanggal = [
        str(t).replace("-", "")
        for t in np.datetime_as_string(waktu, unit="D")
    ]
    awal = np.datetime64(
        f"{tanggal_init[0:4]}-{tanggal_init[4:6]}-{tanggal_init[6:8]}", "D"
    )

    # Indeks pertama yang tidak lebih awal dari tanggal inisial.
    mulai = int(np.searchsorted(waktu, awal))
    mulai = min(mulai, len(tanggal) - 1)

    indeks = [mulai]

    for i in range(mulai + 1, len(tanggal)):
        lead = i - mulai
        if lead <= LEAD_HARIAN or lead % LEAD_MINGGU == 0:
            indeks.append(i)

    hasil = []
    seen = set()

    for i in indeks:
        tanggal_fc = str(tanggal[i])

        if tanggal_fc in seen:
            continue

        seen.add(tanggal_fc)
        hasil.append((int(i), tanggal_fc, int(i - mulai)))

    return hasil


# ============================================================
# RENDER PETA
# ============================================================

# Tata letak gambar dibuat dari rentang wilayah, bukan figsize tetap.
# Kalau figsize dipaksa (12 x 6.5) sementara wilayahnya 360 x 80 derajat,
# Cartopy menjaga rasio lintang:bujur sehingga peta hanya memenuhi
# sebagian kecil kanvas - Papua dan Australia terlihat pipih.
LEBAR_PETA = 11.0
MARJIN_ATAS = 0.95
MARJIN_BAWAH = 0.70
MARJIN_KIRI = 0.80
LEBAR_COLORBAR = 0.32
JARAK_COLORBAR = 0.30

# Colorbar dibuat lebih pendek dari peta dan diberi jarak, agar tidak
# menempel ke label sumbu kanan.
RASIO_COLORBAR = 0.72

DPI_PETA = 150


def skala_warna(nilai, batas_kunci=None):
    """
    Menentukan vmin/vmax colorbar.

    Bila batas_kunci diberikan, skala dikunci ke angka bulat terluar dari
    min/max data sehingga warna antar frame animasi tetap sama.
    Tanpa penguncian, tiap gambar memakai min/max dirinya sendiri dan
    warna melompat-lompat saat animasi berjalan.
    """

    if batas_kunci is not None:
        vmin, vmax = float(batas_kunci[0]), float(batas_kunci[1])
        if np.isfinite(vmin) and np.isfinite(vmax) and vmax > vmin:
            return vmin, vmax

    arr = np.asarray(values_aman(nilai), dtype=float)
    arr = arr[np.isfinite(arr)]

    if arr.size == 0:
        return None

    vmin, vmax = float(arr.min()), float(arr.max())

    if vmax <= vmin:
        return vmin - 1.0, vmax + 1.0

    langkah = 10.0 ** math.floor(math.log10(vmax - vmin))
    langkah = langkah / 2.0 if (vmax - vmin) / langkah < 4.0 else langkah

    return (
        math.floor(vmin / langkah) * langkah,
        math.ceil(vmax / langkah) * langkah,
    )


def values_aman(nilai):
    return np.asarray(nilai, dtype=float)


def ukuran_figur(batas):
    """
    Lebar peta tetap, tinggi mengikuti rasio wilayah agar proporsinya benar.
    """

    lon_min, lon_max, lat_min, lat_max = batas

    rasio = (lat_max - lat_min) / max(lon_max - lon_min, 1e-6)
    tinggi_peta = LEBAR_PETA * rasio

    # Ruang ekstra di kanan untuk angka dan label colorbar.
    lebar_figur = (
        MARJIN_KIRI + LEBAR_PETA + JARAK_COLORBAR + LEBAR_COLORBAR + 0.85
    )
    tinggi_figur = MARJIN_ATAS + tinggi_peta + MARJIN_BAWAH

    return lebar_figur, tinggi_figur, tinggi_peta


def sumbu_peta(da):
    """
    Mengambil koordinat lat/lon serta data 2D, dengan bujur dirapikan
    ke rentang -180..180 agar sesuai proyeksi peta dunia.
    """

    lat_name = next(
        (n for n in ("lat", "latitude", "Latitude") if n in da.coords), None
    )
    lon_name = next(
        (n for n in ("lon", "longitude", "Longitude") if n in da.coords), None
    )

    if lat_name is None or lon_name is None:
        return None

    values = clean_data(da.values)

    if values.ndim != 2:
        return None

    lat = np.asarray(da[lat_name].values, dtype=float)
    lon = np.asarray(da[lon_name].values, dtype=float) % 360.0

    lon = np.where(lon > 180.0, lon - 360.0, lon)
    urutan = np.argsort(lon)

    return lat, lon[urutan], values[:, urutan]


def render_peta(
    lat, lon, values, output_file, judul, subjudul, unit, batas, kunci=None,
    kontur=None, level_kontur=None, satuan_kontur=None,
):
    """
    Satu gambar peta: overlay garis pantai + batas negara, latar laut
    berwarna, dan label inisial/prakiraan di dalam gambar.

    Posisi axes dan colorbar dihitung manual (tanpa tight_layout) supaya
    proporsi wilayah dan ruang judul konsisten di semua frame.

    kontur: dict nama_var -> array 2D pada grid yang sama, dipakai untuk
    menggambar garis ambang supaya ikut bergerak saat dianimasikan.
    """

    try:
        lebar_figur, tinggi_figur, tinggi_peta = ukuran_figur(batas)

        batas_warna = skala_warna(values, kunci)
        norm = None

        if batas_warna is not None:
            norm = matplotlib.colors.Normalize(
                vmin=batas_warna[0], vmax=batas_warna[1]
            )

        fig = plt.figure(figsize=(lebar_figur, tinggi_figur))

        posisi_peta = [
            MARJIN_KIRI / lebar_figur,
            MARJIN_BAWAH / tinggi_figur,
            LEBAR_PETA / lebar_figur,
            tinggi_peta / tinggi_figur,
        ]

        if PAKAI_CARTOPY:
            ax = fig.add_axes(posisi_peta, projection=ccrs.PlateCarree())

            ax.set_extent(batas, crs=ccrs.PlateCarree())

            # Laut dan daratan di bawah data agar posisi wilayah terbaca.
            ax.add_feature(cfeature.OCEAN, facecolor=WARNA_LAUT, edgecolor="none", zorder=0)
            ax.add_feature(cfeature.LAND, facecolor=WARNA_DARAT, edgecolor="none", zorder=1)

            mesh = ax.pcolormesh(
                lon, lat, values,
                shading="auto",
                cmap="turbo",
                norm=norm,
                transform=ccrs.PlateCarree(),
                alpha=0.93,
                zorder=2,
            )

            ax.add_feature(cfeature.BORDERS, linewidth=0.4, edgecolor="black", zorder=3)
            ax.add_feature(
                cfeature.COASTLINE, linewidth=0.6, edgecolor=WARNA_PANTAI, zorder=4
            )

            grid = ax.gridlines(
                crs=ccrs.PlateCarree(),
                draw_labels=True,
                linewidth=0.4,
                color="gray",
                alpha=0.28,
                linestyle="--",
                x_inline=False,
                y_inline=False,
            )

            # Label hanya di kiri dan bawah; ruang atas dipakai judul.
            grid.top_labels = False
            grid.right_labels = False

            ax.set_xlabel("Bujur (°BT)", fontsize=9)
            ax.set_ylabel("Lintang (°LU)", fontsize=9)
            ax.tick_params(labelsize=8)
        else:
            ax = fig.add_axes(posisi_peta)

            ax.set_facecolor(WARNA_LAUT)

            mesh = ax.pcolormesh(
                lon, lat, values, shading="auto", cmap="turbo", norm=norm
            )

            draw_coastline(ax, float(np.nanmin(lon)), float(np.nanmax(lon)))

            ax.set_xlim(float(np.nanmin(lon)), float(np.nanmax(lon)))
            ax.set_ylim(float(np.nanmin(lat)), float(np.nanmax(lat)))

            ax.set_xlabel("Bujur (°BT)", fontsize=9)
            ax.set_ylabel("Lintang (°LU)", fontsize=9)
            ax.grid(True, alpha=0.2, linestyle="--")
            ax.spines["top"].set_visible(False)
            ax.spines["right"].set_visible(False)
            ax.tick_params(labelsize=8)

        garis = []

        for nama_var, nilai_kontur in (kontur or {}).items():
            if not level_kontur or nama_var not in level_kontur:
                continue

            label, warna = WARNA_KONTUR[nama_var]
            minus, plus = level_kontur[nama_var]

            ax.contour(
                lon, lat, nilai_kontur,
                levels=[minus, plus],
                colors=warna,
                linewidths=1.2,
                linestyles=["-", "-"],
                zorder=3.5,
                **({"transform": ccrs.PlateCarree()} if PAKAI_CARTOPY else {}),
            )

            garis.append(
                plt.Line2D(
                    [], [], color=warna, linestyle="-", linewidth=1.8,
                    label="{} ±{:.0f} {}".format(
                        label,
                        abs(plus),
                        (satuan_kontur or {}).get(nama_var, unit),
                    ),
                )
            )

        if garis:
            legenda = ax.legend(
                handles=garis,
                loc="upper left",
                bbox_to_anchor=(0.0, 1.0),
                fontsize=8,
                title="Kontur gelombang",
                title_fontsize=8.5,
                framealpha=0.9,
                borderpad=0.4,
                handlelength=2.4,
            )
            legenda.set_zorder(10)

        tinggi_colorbar = tinggi_peta * RASIO_COLORBAR

        cax = fig.add_axes([
            (MARJIN_KIRI + LEBAR_PETA + JARAK_COLORBAR) / lebar_figur,
            (MARJIN_BAWAH + (tinggi_peta - tinggi_colorbar) / 2.0) / tinggi_figur,
            LEBAR_COLORBAR / lebar_figur,
            tinggi_colorbar / tinggi_figur,
        ])

        cbar = fig.colorbar(mesh, cax=cax)
        cbar.ax.tick_params(labelsize=8)
        cbar.set_label(unit, fontsize=9)

        ax.set_title(judul, fontsize=13, fontweight="bold", pad=8)

        if subjudul:
            fig.text(
                0.5,
                0.018,
                subjudul,
                ha="center",
                va="bottom",
                fontsize=10,
                color="#1f2933",
            )

        fig.savefig(output_file, dpi=DPI_PETA)
        plt.close(fig)

        return True

    except Exception as e:
        print(f"  [ERROR] {output_file.name}: {e}")
        plt.close("all")
        return False


def sebaran_wilayah(da, tanggal_init, batas):
    """
    Seluruh nilai variabel pada satu wilayah untuk semua lead yang dirender.

    Dipakai bersama oleh peta reguler dan panel supaya warna dan garis
    ambangnya dihitung dari data yang sama, bukan dari tanggal yang
    kebetulan tampil pada gambar.
    """

    ada_waktu = "time" in da.dims
    titik = []

    for indeks, _, _ in daftar_lead(da, tanggal_init):
        hasil = sumbu_peta(da.isel(time=indeks) if ada_waktu else da)

        if hasil is None:
            continue

        _, _, nilai = potong_wilayah(*hasil, batas)
        titik.append(np.asarray(nilai, dtype=float).ravel())

    return titik


def kunci_warna_variabel(da, tanggal_init):
    """vmin/vmax colorbar yang dipakai semua produk untuk variabel ini."""

    titik = sebaran_wilayah(da, tanggal_init, REGION["dunia"])

    if not titik:
        return None

    return skala_warna(np.concatenate(titik))


def skala_simetris(nilai, langkah, persentil=None):
    """
    Batas warna simetris di nol, dibulatkan ke kelipatan langkah.

    Dipakai overlay OLR karena colormaps-nya diverging: nol harus jatuh
    tepat di tengah palet, tidak boleh miring walau datanya asimetris.
    Bila persentil diisi, besarnya diambil dari persentil nilai absolut,
    bukan nilai mutlak terbesar, supaya satu piksel pencil tidak membuat
    seluruh peta jadi pucat.
    """

    arr = np.asarray(nilai, dtype=float)
    arr = arr[np.isfinite(arr)]

    if arr.size == 0:
        return None

    absolut = np.abs(arr)

    if persentil is not None:
        terbesar = float(np.percentile(absolut, persentil))
    else:
        terbesar = float(np.max(absolut))

    if not (np.isfinite(terbesar) and terbesar > 0):
        return None

    batas = math.ceil(terbesar / langkah) * langkah

    return (-batas, batas)


def ambang_kontur(titik):
    """Ambang garis ±FAKTOR_SIGMA simpangan data dari sebaran nilainya."""

    if not titik:
        return None

    sigma = float(np.nanstd(np.concatenate(titik)))

    if not (np.isfinite(sigma) and sigma > 0):
        return None

    ambang = round(FAKTOR_SIGMA * sigma)

    return (-ambang, ambang) if ambang else None


def simpan_peta(
    da, output_folder, nama_var, judul_var, tanggal_init, unit,
    ds=None, gelombang_kontur=None,
):
    """
    Merender seluruh lead time untuk satu variabel pada dua wilayah.

    Struktur output mengikuti konvensi halaman INA-OWS:
    map/{VARIABEL}/{inisial}/{prakiraan}/{variabel}.png sehingga tanggal
    inisial dan tanggal prakiraan terbaca dari nama folder.

    gelombang_kontur dipakai untuk menumpuk garis ambang pada tiap frame
    supaya garisnya ikut bergerak saat gambar dianimasikan.
    """

    ada_waktu = "time" in da.dims

    da_kontur = {}

    for nama_var_kontur, _, _, _ in gelombang_kontur or []:
        sumber = get_variable(ds, [nama_var_kontur]) if ds is not None else None

        if sumber is not None:
            da_kontur[nama_var_kontur] = sumber

    satuan_kontur = {
        nama_var_kontur: satuan_variabel(sumber, unit)
        for nama_var_kontur, sumber in da_kontur.items()
    }

    # Kumpulkan seluruh lead yang akan dirender lebih dulu. Skala warna
    # dihitung dari min/max gabungan supaya satu variabel memakai warna
    # yang sama di semua frame animasi.
    potongan = []

    for indeks, tanggal_fc, lead in daftar_lead(da, tanggal_init):
        langkah = da.isel(time=indeks) if ada_waktu else da

        hasil = sumbu_peta(langkah)

        if hasil is None:
            print(f"  [SKIP] koordinat/data tidak sesuai: {judul_var} {tanggal_fc}")
            continue

        lat, lon, values = hasil
        wilayah = {
            nama_region: potong_wilayah(lat, lon, values, batas)
            for nama_region, batas in REGION.items()
        }

        wilayah_kontur = {}

        for nama_var_kontur, sumber in da_kontur.items():
            langkah_kontur = (
                sumber.isel(time=indeks) if "time" in sumber.dims else sumber
            )

            hasil_kontur = sumbu_peta(langkah_kontur)

            if hasil_kontur is None:
                continue

            k_lat, k_lon, k_values = hasil_kontur

            wilayah_kontur[nama_var_kontur] = {
                nama_region: potong_wilayah(k_lat, k_lon, k_values, batas)[2]
                for nama_region, batas in REGION.items()
            }

        potongan.append((tanggal_fc, lead, wilayah, wilayah_kontur))

    if not potongan:
        return

    kunci = kunci_warna_variabel(da, tanggal_init)

    # Ambang garis dihitung dari sebaran data variabel itu sendiri pada
    # wilayah yang sama, sama seperti produk panel, lalu dipakai seragam
    # di semua frame supaya gerak garisnya murni dari datanya.
    level_kontur = {nama_region: {} for nama_region in REGION}

    for nama_region in REGION:
        for nama_var_kontur in da_kontur:
            titik = [
                k[nama_var_kontur][nama_region].ravel()
                for _, _, _, k in potongan
                if nama_var_kontur in k
            ]

            if not titik:
                continue

            ambang = ambang_kontur(titik)

            if ambang is not None:
                level_kontur[nama_region][nama_var_kontur] = ambang

    ada_garis = any(level_kontur[n].values() for n in REGION)

    for tanggal_fc, lead, wilayah, wilayah_kontur in potongan:
        lead_teks = "Initial (lead 0)" if lead == 0 else f"Lead +{lead} hari"
        subjudul = (
            f"Initial: {tanggal_indonesia(tanggal_init)}   |   "
            f"Forecast: {tanggal_indonesia(tanggal_fc)}   |   {lead_teks}"
        )

        if ada_garis:
            subjudul += f"   |   Garis: ±{FAKTOR_SIGMA:.0f} simpangan data"

        folder_lead = output_folder / tanggal_fc
        folder_lead.mkdir(parents=True, exist_ok=True)

        for nama_region, batas in REGION.items():
            akhiran = "" if nama_region == "dunia" else f".{nama_region}"
            output_file = folder_lead / f"{nama_var}{akhiran}.png"

            c_lat, c_lon, c_values = wilayah[nama_region]

            kontur = {
                nama_var_kontur: nilai[nama_region]
                for nama_var_kontur, nilai in wilayah_kontur.items()
            }

            judul = f"{judul_var} - {unit} - {tanggal_fc}"

            if render_peta(
                c_lat, c_lon, c_values, output_file, judul, subjudul, unit,
                batas, kunci, kontur, level_kontur[nama_region], satuan_kontur,
            ):
                print(f"  [OK] {folder_lead.name}/{output_file.name}")


def potong_wilayah(lat, lon, values, batas):
    """
    Mengambil sebagian grid data yang masih dipengaruhi wilayah peta.
    Margin 5 derajat menjaga agar warna di tepi peta tetap halus.
    """

    lon_min, lon_max, lat_min, lat_max = batas
    margin = 5.0

    pilih_lat = (lat >= lat_min - margin) & (lat <= lat_max + margin)
    pilih_lon = (lon >= lon_min - margin) & (lon <= lon_max + margin)

    if np.count_nonzero(pilih_lat) < 2 or np.count_nonzero(pilih_lon) < 2:
        return lat, lon, values

    return (
        lat[pilih_lat],
        lon[pilih_lon],
        values[np.ix_(pilih_lat, pilih_lon)],
    )


# ============================================================
# PLOT OVERLAY GAYA PNJ
# ============================================================

# Satu gambar gabungan: isian OLR (hijau = basah, cokelat = kering),
# kontur ambang OLR, dan garis amplitudo gelombang MJO/Kelvin/ER.
# Isian OLR memakai BrBG terbalik: nilai rendah (basah, konveksi aktif)
# hijau, nilai tinggi (kering) cokelat. Sama seperti produk InaWMS resmi
# sehingga warnanya tidak bertentangan antar halaman.
WARNA_ISI_OVERLAY = "BrBG_r"

# Garis gelombang dibuat sama warnanya dengan diagram Hovmoller
# (proses_hovmoller.py) agar satu produk tidak memakai dua bahasa warna.
GELOMBANG_OVERLAY = [
    ("mjo", "MJO", "black", "-"),
    ("kelvin", "Kelvin", "#1f4fd8", "--"),
    ("er", "ER", "red", "-."),
    ("low", "Low", "purple", ":"),
    ("mrgtd", "MRG/TD", "#0f766e", (0, (5, 1, 1, 1))),
]

# Kontur ambang OLR: hanya rentang ini yang digambar sesuai permintaan
# pembimbing, di luar rentang tersebut tidak ada garis.
AMBANG_OLR = [-36.0, -30.0, -24.0, -18.0, -12.0]

# Batas warna OLR pada peta overlay. Hanya dipakai sebagai cadangan kalau
# sebaran data tidak bisa dihitung; normalnya batasnya diambil dari data.
BATAS_OLR_OVERLAY = (-40.0, 40.0)

# Batas kelas warna dibuat kelipatan 8 agar angka pada colorbar bulat.
LANGKAH_OLR_OVERLAY = 8.0

# Batas warna overlay diambil dari persentil ini, bukan min/max, supaya
# rentangnya antar inisial mirip. Sisanya (<0,5% piksel) masuk warna ujung.
PERSANTIL_ISI_OVERLAY = 99.5

# Garis gelombang digambar pada kelipatan simpangan data, bukan angka
# acak, agar ambangnya terbaca dan bisa dipertanggungjawabkan.
FAKTOR_SIGMA = 2.0


def render_overlay(
    lat, lon, olr, gelombang, output_file, judul, subjudul,
    batas, level_olr, level_gelombang, batas_isi=None,
):
    """
    Satu gambar overlay gaya PNJ untuk satu langkah waktu.

    gelombang: dict nama_var -> array 2D pada grid yang sama.
    level_gelombang: dict nama_var -> (minus, plus) ambang garis.
    batas_isi: rentang kelas warna OLR; kosong berarti pakai BATAS_OLR_OVERLAY.
    """

    try:
        lebar_figur, tinggi_figur, tinggi_peta = ukuran_figur(batas)

        fig = plt.figure(figsize=(lebar_figur, tinggi_figur))

        posisi_peta = [
            MARJIN_KIRI / lebar_figur,
            MARJIN_BAWAH / tinggi_figur,
            LEBAR_PETA / lebar_figur,
            tinggi_peta / tinggi_figur,
        ]

        if PAKAI_CARTOPY:
            ax = fig.add_axes(posisi_peta, projection=ccrs.PlateCarree())
            ax.set_extent(batas, crs=ccrs.PlateCarree())
        else:
            ax = fig.add_axes(posisi_peta)
            ax.set_facecolor(WARNA_LAUT)

        if batas_isi is not None and batas_isi[1] > batas_isi[0]:
            kelas_bawah, kelas_atas = float(batas_isi[0]), float(batas_isi[1])
        else:
            kelas_bawah, kelas_atas = BATAS_OLR_OVERLAY

        batas_kelas = np.arange(
            kelas_bawah,
            kelas_atas + LANGKAH_OLR_OVERLAY / 2.0,
            LANGKAH_OLR_OVERLAY,
        )

        jumlah_kelas = len(batas_kelas) - 1

        # BoundaryNorm mengembalikan indeks kelas, jadi colormap harus
        # punya jumlah warna persis sama dengan kelasnya. Kalau memakai
        # colormap 256 warna langsung, semua nilai jatuh ke warna terbawah.
        cmap_olr = matplotlib.colors.ListedColormap(
            matplotlib.colormaps[WARNA_ISI_OVERLAY](
                np.linspace(0.0, 1.0, jumlah_kelas)
            )
        )

        norm = matplotlib.colors.BoundaryNorm(batas_kelas, jumlah_kelas)

        mesh = ax.pcolormesh(
            lon, lat, olr,
            shading="auto",
            cmap=cmap_olr,
            norm=norm,
            zorder=2,
            **({"transform": ccrs.PlateCarree()} if PAKAI_CARTOPY else {}),
        )

        # Hanya kontur pada rentang ambang yang diminta.
        kontur = ax.contour(
            lon, lat, olr,
            levels=level_olr,
            colors="#14431b",
            linewidths=0.7,
            zorder=4,
            **({"transform": ccrs.PlateCarree()} if PAKAI_CARTOPY else {}),
        )
        ax.clabel(kontur, fmt="%.0f", fontsize=7)

        garis = []

        for nama_var, label, warna, gaya in GELOMBANG_OVERLAY:
            if nama_var not in gelombang or nama_var not in level_gelombang:
                continue

            minus, plus = level_gelombang[nama_var]

            hs = ax.contour(
                lon, lat, gelombang[nama_var],
                levels=[minus, plus],
                colors=warna,
                linewidths=1.5,
                linestyles=[gaya, gaya],
                zorder=5,
                **({"transform": ccrs.PlateCarree()} if PAKAI_CARTOPY else {}),
            )

            garis.append(
                plt.Line2D(
                    [], [], color=warna, linestyle=gaya, linewidth=1.8,
                    label=f"{label} ±{abs(plus):.0f} W m-2",
                )
            )

        if level_olr:
            garis.append(
                plt.Line2D(
                    [], [], color="#14431b", linestyle="-", linewidth=1.0,
                    label=(
                        f"OLR {min(level_olr):.0f} s.d. {max(level_olr):.0f} "
                        "W m-2 (konveksi dalam)"
                    ),
                )
            )

        if PAKAI_CARTOPY:
            ax.add_feature(cfeature.BORDERS, linewidth=0.4, edgecolor="black", zorder=6)
            ax.add_feature(
                cfeature.COASTLINE, linewidth=0.7, edgecolor=WARNA_PANTAI, zorder=7
            )

            grid = ax.gridlines(
                crs=ccrs.PlateCarree(),
                draw_labels=True,
                linewidth=0.4,
                color="gray",
                alpha=0.28,
                linestyle="--",
                x_inline=False,
                y_inline=False,
            )
            grid.top_labels = False
            grid.right_labels = False
        else:
            draw_coastline(ax, float(np.nanmin(lon)), float(np.nanmax(lon)))
            ax.set_xlim(float(np.nanmin(lon)), float(np.nanmax(lon)))
            ax.set_ylim(float(np.nanmin(lat)), float(np.nanmax(lat)))
            ax.spines["top"].set_visible(False)
            ax.spines["right"].set_visible(False)

        ax.set_xlabel("Bujur (°BT)", fontsize=9)
        ax.set_ylabel("Lintang (°LU)", fontsize=9)
        ax.tick_params(labelsize=8)

        tinggi_colorbar = tinggi_peta * RASIO_COLORBAR

        cax = fig.add_axes([
            (MARJIN_KIRI + LEBAR_PETA + JARAK_COLORBAR) / lebar_figur,
            (MARJIN_BAWAH + (tinggi_peta - tinggi_colorbar) / 2.0) / tinggi_figur,
            LEBAR_COLORBAR / lebar_figur,
            tinggi_colorbar / tinggi_figur,
        ])

        cbar = fig.colorbar(mesh, cax=cax, extend="both")

        # Kelas warnanya bisa puluhan kalau rentang data melebar, jadi label
        # colorbar hanya dipasang pada sebagian batas agar tidak bertumpuk.
        label_kelas = LANGKAH_OLR_OVERLAY

        while (kelas_atas - kelas_bawah) / label_kelas > 12:
            label_kelas *= 2.0

        cbar.set_ticks(np.arange(
            kelas_bawah, kelas_atas + label_kelas / 2.0, label_kelas
        ))
        cbar.ax.tick_params(labelsize=8)
        cbar.set_label("OLR (W m-2)", fontsize=9)

        legenda = ax.legend(
            handles=garis,
            loc="upper left",
            bbox_to_anchor=(0.0, 1.0),
            fontsize=8,
            title="Keterangan garis",
            title_fontsize=8.5,
            framealpha=0.9,
            borderpad=0.4,
            handlelength=2.4,
        )
        legenda.set_zorder(10)

        ax.set_title(judul, fontsize=13, fontweight="bold", pad=8)

        if subjudul:
            fig.text(
                0.5,
                0.018,
                subjudul,
                ha="center",
                va="bottom",
                fontsize=9.5,
                color="#1f2933",
            )

        fig.savefig(output_file, dpi=DPI_PETA)
        plt.close(fig)

        return True

    except Exception as e:
        print(f"  [ERROR] overlay {output_file.name}: {e}")
        plt.close("all")
        return False


def simpan_overlay(ds, output_folder, tanggal_init):
    """
    Merender overlay gaya PNJ untuk satu file inisial.

    Data yang dipakai: OLR sebagai isian dan MJO/Kelvin/ER sebagai garis.
    Ambang garis dihitung dari simpangan data satu variabel sehingga
    sama di semua frame dan bisa disebut pada legenda gambar.
    """

    utama = get_variable(ds, ["olr"])

    if utama is None or "time" not in utama.dims:
        print("  [SKIP] overlay: variabel olr atau dimensi time tidak ada.")
        return

    gelombang = {}

    for nama_var, _, _, _ in GELOMBANG_OVERLAY:
        da = get_variable(ds, [nama_var])

        if da is not None:
            gelombang[nama_var] = da

    batas = REGION["indonesia"]

    langkah = []

    for indeks, tanggal_fc, lead in daftar_lead(utama, tanggal_init):
        hasil = sumbu_peta(utama.isel(time=indeks))

        if hasil is None:
            continue

        lat, lon, olr = hasil
        c_lat, c_lon, c_olr = potong_wilayah(lat, lon, olr, batas)

        isi = {}

        for nama_var, da in gelombang.items():
            g = sumbu_peta(da.isel(time=indeks))

            if g is None:
                continue

            _, _, nilai = g
            _, _, isi[nama_var] = potong_wilayah(g[0], g[1], g[2], batas)

        langkah.append((tanggal_fc, lead, c_lat, c_lon, c_olr, isi))

    if not langkah:
        return

    level_gelombang = {}

    for nama_var in gelombang:
        gabungan = np.concatenate([
            isi[nama_var].ravel()
            for _, _, _, _, _, isi in langkah
            if nama_var in isi
        ])
        sigma = float(np.nanstd(gabungan))

        if np.isfinite(sigma) and sigma > 0:
            ambang = round(FAKTOR_SIGMA * sigma)
            level_gelombang[nama_var] = (-ambang, ambang)

    # Skala warna isian diambil dari sebaran OLR wilayah yang sama dan
    # semua lead yang dirender, lalu dibuat simetris di nol dan dibulatkan
    # ke kelipatan langkah kelas. Dengan patok mati -40..40, hampir seluruh
    # wilayah jatuh ke kelas cokelat paling gelap karena median OLR di
    # kotak Indonesia ada di sekitar +30 dan p99-nya dekat +78.
    gabungan_olr = np.concatenate([c_olr.ravel() for _, _, _, _, c_olr, _ in langkah])
    batas_isi = skala_simetris(
        gabungan_olr, LANGKAH_OLR_OVERLAY, PERSANTIL_ISI_OVERLAY
    )

    for tanggal_fc, lead, c_lat, c_lon, c_olr, isi in langkah:
        lead_teks = "Initial (lead 0)" if lead == 0 else f"Lead +{lead} hari"

        folder_lead = output_folder / tanggal_fc
        folder_lead.mkdir(parents=True, exist_ok=True)

        render_overlay(
            c_lat, c_lon, c_olr, isi,
            folder_lead / "olr.overlay.png",
            f"Overlay OLR + Gelombang - {tanggal_fc}",
            (
                f"Initial: {tanggal_indonesia(tanggal_init)}   |   "
                f"Forecast: {tanggal_indonesia(tanggal_fc)}   |   {lead_teks}   |   "
                f"Garis: ±{FAKTOR_SIGMA:.0f} simpangan data per gelombang"
            ),
            batas, AMBANG_OLR, level_gelombang, batas_isi,
        )

    # Panel overlay memakai potongan data yang sama dengan gambar tunggal,
    # termasuk ambang garis dan skala isian, jadi tampilannya identik hanya
    # disusun delapan tanggal dalam satu gambar.
    panel = langkah[:JUMLAH_PANEL]

    if len(panel) >= 2:
        folder_panel = output_folder / tanggal_init
        folder_panel.mkdir(parents=True, exist_ok=True)

        render_panel(
            panel,
            folder_panel / "olr.overlay.panel.indonesia.png",
            f"Panel Overlay OLR + Gelombang - {tanggal_indonesia(tanggal_init)}",
            "   |   ".join([
                f"Initial: {tanggal_indonesia(tanggal_init)}",
                f"Panel: {tanggal_indonesia(tanggal_init)} sampai "
                f"{tanggal_indonesia(panel[-1][0])}",
                "Semua panel adalah prakiraan CFS, bukan observasi",
                f"Wilayah: {batas[0]:.0f}-{batas[1]:.0f} BT, "
                f"{abs(batas[2]):.0f} LS-{batas[3]:.0f} LU",
                f"Garis: ±{FAKTOR_SIGMA:.0f} simpangan data per gelombang",
            ]),
            level_gelombang,
            label_warna="OLR (W m⁻²)",
            batas=batas,
            gaya_kontur={
                nama_var: gaya for nama_var, _, _, gaya in GELOMBANG_OVERLAY
            },
            level_olr=AMBANG_OLR,
            batas_isi=batas_isi,
        )


NAMA_BULAN_SINGKAT = [
    "",
    "Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
    "Jul", "Agu", "Sep", "Okt", "Nov", "Des",
]


def tanggal_pendek(yyyymmdd):
    tahun = int(yyyymmdd[0:4])
    bulan = int(yyyymmdd[4:6])
    hari = int(yyyymmdd[6:8])

    return f"{hari} {NAMA_BULAN_SINGKAT[bulan]}"


# ============================================================
# PANEL MULTI-TANGGAL (OLR + GARIS GELOMBANG)
# ============================================================

# Meniru produk InaWMS "1-day OLR with CFS Forecasts": beberapa tanggal
# berturut-turut dalam satu gambar, latar OLR, garis gelombang di atasnya.
# Wilayah dan skala warna panel dibuat sama dengan peta Indonesia biasa
# supaya gambar gabungan menyatu dengan produk yang sudah ada.
BATAS_PANEL = tuple(REGION["indonesia"])
JUMLAH_PANEL = 8
KOLOM_PANEL = 2

LEBAR_PANEL = 5.30
CELAH_X_PANEL = 0.55
CELAH_Y_PANEL = 0.52
MARJIN_KIRI_PANEL = 0.62
MARJIN_ATAS_PANEL = 0.62
LEBAR_LEGENDA_PANEL = 1.90

# Ruang bawah dipakai bersama oleh colorbar (beserta angka dan label
# satuan) dan satu baris keterangan, jadi harus cukup tinggi agar
# tidak saling menimpa.
MARJIN_BAWAH_PANEL = 1.55
TINGGI_COLORBAR_PANEL = 0.28
POSISI_COLORBAR_PANEL = 0.95

# Panel dirender untuk dua wilayah. Peta dunia 4,5 kali lebih lebar daripada
# tingginya (360 x 80 derajat), jadi panelnya memakai lebar sendiri supaya
# garis lintang-bujur dan kontur tetap terbaca.
PANEL_WILAYAH = {
    "indonesia": {"batas": BATAS_PANEL, "lebar": LEBAR_PANEL},
    "dunia": {"batas": tuple(REGION["dunia"]), "lebar": 8.00},
}

# Warna garis sama dengan diagram Hovmoller dan overlay.
WARNA_KONTUR = {
    "mjo": ("MJO", "black"),
    "kelvin": ("Kelvin", "#1f4fd8"),
    "er": ("ER", "red"),
    "low": ("Low", "purple"),
    "mrgtd": ("MRG/TD", "#0f766e"),
}

# Peta utama, panel, dan overlay menampilkan garis semua gelombang yang
# ada di file NetCDF, termasuk MRG/TD, supaya tidak ada gelombang yang
# hilang di satu produk tetapi muncul di produk lain.
GELOMBANG_PANEL = [
    (nama, label, warna, "-")
    for nama, (label, warna) in WARNA_KONTUR.items()
]


def kontur_diri(nama_var):
    """Garis kontur untuk panel yang melatarbelakangi variabel itu sendiri."""

    label, warna = WARNA_KONTUR[nama_var]

    return [(nama_var, label, warna, "-")]


def render_panel(
    langkah, output_file, judul, subjudul, level_gelombang,
    batas_warna=None, label_warna="OLR (W m⁻²)", satuan_kontur=None,
    batas=None, lebar_panel=None, gaya_kontur=None, level_olr=None,
    batas_isi=None,
):
    """
    Satu gambar berisi beberapa panel tanggal dengan colorbar bersama.

    Default-nya panel memakai gaya peta reguler: latar turbo dan garis
    gelombang solid. Jika batas_isi dan level_olr diisi, panel memakai gaya
    overlay: isian berkelas BrBG_r plus garis ambang OLR di atasnya.
    """

    try:
        batas = BATAS_PANEL if batas is None else batas
        lebar_panel = LEBAR_PANEL if lebar_panel is None else lebar_panel
        gaya_kontur = gaya_kontur or {}

        rentang_lintang = batas[3] - batas[2]
        rentang_bujur = batas[1] - batas[0]
        tinggi_panel = lebar_panel * rentang_lintang / rentang_bujur

        jumlah_baris = math.ceil(len(langkah) / KOLOM_PANEL)

        lebar_blok = KOLOM_PANEL * lebar_panel + (KOLOM_PANEL - 1) * CELAH_X_PANEL
        tinggi_blok = jumlah_baris * tinggi_panel + (jumlah_baris - 1) * CELAH_Y_PANEL

        # Keterangan garis disusun lebih dulu karena panjang teksnya ikut
        # menentukan lebar figur; tanpa ini label ambang OLR terpotong.
        handles = []

        for nama_var, (minus, plus) in level_gelombang.items():
            label, warna = WARNA_KONTUR[nama_var]

            handles.append(
                plt.Line2D(
                    [], [], color=warna,
                    linestyle=gaya_kontur.get(nama_var, "-"), linewidth=1.6,
                    label="{} ±{:.0f} {}".format(
                        label,
                        abs(plus),
                        (satuan_kontur or {}).get(nama_var, "W m⁻²"),
                    ),
                )
            )

        if level_olr:
            handles.append(
                plt.Line2D(
                    [], [], color="#14431b", linestyle="-", linewidth=1.0,
                    label=(
                        f"OLR {min(level_olr):.0f} s.d. {max(level_olr):.0f} "
                        "W m-2 (konveksi dalam)"
                    ),
                )
            )

        teks_terpanjang = max((h.get_label() for h in handles), key=len, default="")
        lebar_legenda = max(LEBAR_LEGENDA_PANEL, 0.45 + 0.065 * len(teks_terpanjang))

        lebar_figur = MARJIN_KIRI_PANEL + lebar_blok + 0.45 + lebar_legenda
        tinggi_figur = MARJIN_ATAS_PANEL + tinggi_blok + MARJIN_BAWAH_PANEL

        fig = plt.figure(figsize=(lebar_figur, tinggi_figur))

        if batas_isi is not None and batas_isi[1] > batas_isi[0]:
            batas_kelas = np.arange(
                batas_isi[0],
                batas_isi[1] + LANGKAH_OLR_OVERLAY / 2.0,
                LANGKAH_OLR_OVERLAY,
            )
            jumlah_kelas = len(batas_kelas) - 1

            cmap_isi = matplotlib.colors.ListedColormap(
                matplotlib.colormaps[WARNA_ISI_OVERLAY](
                    np.linspace(0.0, 1.0, jumlah_kelas)
                )
            )
            norm = matplotlib.colors.BoundaryNorm(batas_kelas, jumlah_kelas)
            alpha_isi = 1.0
        else:
            batas_kelas = None
            cmap_isi = "turbo"
            norm = (
                matplotlib.colors.Normalize(
                    vmin=batas_warna[0], vmax=batas_warna[1]
                )
                if batas_warna is not None
                else None
            )
            alpha_isi = 0.93

        mesh = None

        for nomor, (tanggal_fc, lead, c_lat, c_lon, c_olr, isi) in enumerate(langkah):
            # Diisi per kolom: tanggal berturut-turut turun ke bawah, sama
            # seperti produk contoh.
            baris = nomor % jumlah_baris
            kolom = nomor // jumlah_baris

            kiri = MARJIN_KIRI_PANEL + kolom * (lebar_panel + CELAH_X_PANEL)
            bawah = (
                MARJIN_BAWAH_PANEL
                + (jumlah_baris - 1 - baris) * (tinggi_panel + CELAH_Y_PANEL)
            )

            posisi = [
                kiri / lebar_figur,
                bawah / tinggi_figur,
                lebar_panel / lebar_figur,
                tinggi_panel / tinggi_figur,
            ]

            if PAKAI_CARTOPY:
                ax = fig.add_axes(posisi, projection=ccrs.PlateCarree())
                ax.set_extent(batas, crs=ccrs.PlateCarree())

                # Sama dengan peta Indonesia biasa: laut dan daratan di
                # bawah data agar posisi wilayah tetap terbaca.
                ax.add_feature(
                    cfeature.OCEAN, facecolor=WARNA_LAUT, edgecolor="none", zorder=0
                )
                ax.add_feature(
                    cfeature.LAND, facecolor=WARNA_DARAT, edgecolor="none", zorder=1
                )
            else:
                ax = fig.add_axes(posisi)
                ax.set_facecolor(WARNA_LAUT)

            mesh = ax.pcolormesh(
                c_lon, c_lat, c_olr,
                shading="auto",
                cmap=cmap_isi,
                norm=norm,
                alpha=alpha_isi,
                zorder=2,
                **({"transform": ccrs.PlateCarree()} if PAKAI_CARTOPY else {}),
            )

            for nama_var, nilai_kontur in isi.items():
                if nama_var not in level_gelombang:
                    continue

                _, warna = WARNA_KONTUR[nama_var]
                minus, plus = level_gelombang[nama_var]
                gaya = gaya_kontur.get(nama_var, "-")

                ax.contour(
                    c_lon, c_lat, nilai_kontur,
                    levels=[minus, plus],
                    colors=warna,
                    linewidths=1.1,
                    linestyles=[gaya, gaya],
                    zorder=5,
                    **({"transform": ccrs.PlateCarree()} if PAKAI_CARTOPY else {}),
                )

            if level_olr:
                # Tanpa angka di sepanjang garis: di ukuran panel labelnya
                # jadi coretan yang menutupi isian, ambangnya sudah ada
                # di legenda.
                ax.contour(
                    c_lon, c_lat, c_olr,
                    levels=level_olr,
                    colors="#14431b",
                    linewidths=0.7,
                    zorder=4,
                    **({"transform": ccrs.PlateCarree()} if PAKAI_CARTOPY else {}),
                )

            if PAKAI_CARTOPY:
                ax.add_feature(cfeature.BORDERS, linewidth=0.3, edgecolor="black", zorder=6)
                ax.add_feature(
                    cfeature.COASTLINE, linewidth=0.6, edgecolor=WARNA_PANTAI, zorder=7
                )

                grid = ax.gridlines(
                    crs=ccrs.PlateCarree(),
                    draw_labels=True,
                    linewidth=0.35,
                    color="gray",
                    alpha=0.25,
                    linestyle="--",
                    x_inline=False,
                    y_inline=False,
                )
                grid.top_labels = False
                grid.right_labels = False
                grid.left_labels = (kolom == 0)
                grid.bottom_labels = (baris == jumlah_baris - 1)
            else:
                draw_coastline(ax, batas[0], batas[1])
                ax.set_xlim(batas[0], batas[1])
                ax.set_ylim(batas[2], batas[3])
                ax.spines["top"].set_visible(False)
                ax.spines["right"].set_visible(False)

                if kolom != 0:
                    ax.set_yticklabels([])
                if baris != jumlah_baris - 1:
                    ax.set_xticklabels([])

            ax.tick_params(labelsize=7.5)
            ax.set_title(
                f"{tanggal_pendek(tanggal_fc)}  (lead +{lead} hari)",
                fontsize=9,
                loc="left",
                pad=3,
            )

        if handles:
            fig.legend(
                handles=handles,
                loc="upper left",
                bbox_to_anchor=(
                    (MARJIN_KIRI_PANEL + lebar_blok + 0.45) / lebar_figur,
                    (MARJIN_ATAS_PANEL + tinggi_blok * 0.62) / tinggi_figur,
                ),
                fontsize=8.5,
                framealpha=0.92,
                title="Kontur gelombang",
                title_fontsize=8.5,
                handlelength=2.2,
            )

        if mesh is not None:
            cax = fig.add_axes([
                MARJIN_KIRI_PANEL / lebar_figur,
                POSISI_COLORBAR_PANEL / tinggi_figur,
                lebar_blok / lebar_figur,
                TINGGI_COLORBAR_PANEL / tinggi_figur,
            ])

            if batas_kelas is None:
                cbar = fig.colorbar(
                    mesh, cax=cax,
                    orientation="horizontal",
                    ticks=matplotlib.ticker.MaxNLocator(11),
                )
            else:
                # Kelas isian overlay bisa puluhan, jadi jarak label dibuat
                # melekat ke kelipatan langkah kelas, bukan per kelas.
                cbar = fig.colorbar(
                    mesh, cax=cax, orientation="horizontal", extend="both"
                )
                label_kelas = LANGKAH_OLR_OVERLAY
                while (batas_kelas[-1] - batas_kelas[0]) / label_kelas > 12:
                    label_kelas *= 2.0
                cbar.set_ticks(
                    np.arange(
                        batas_kelas[0],
                        batas_kelas[-1] + label_kelas / 2.0,
                        label_kelas,
                    )
                )

            cbar.ax.tick_params(labelsize=8)
            cbar.set_label(label_warna, fontsize=9)

        fig.text(
            (MARJIN_KIRI_PANEL + lebar_blok / 2.0) / lebar_figur,
            1.0 - (MARJIN_ATAS_PANEL / 2.2) / tinggi_figur,
            judul,
            ha="center",
            va="center",
            fontsize=13,
            fontweight="bold",
        )

        if subjudul:
            fig.text(
                0.5,
                0.024,
                subjudul,
                ha="center",
                va="bottom",
                fontsize=8.5,
                color="#1f2933",
            )

            fig.text(
                0.5,
                0.006,
                "Dibuat: "
                + np.datetime_as_string(np.datetime64("now", "s"), unit="s")
                + " UTC",
                ha="center",
                va="bottom",
                fontsize=7.5,
                color="#4b5563",
            )

        fig.savefig(output_file, dpi=DPI_PETA)
        plt.close(fig)

        return True

    except Exception as e:
        print(f"  [ERROR] panel {output_file.name}: {e}")
        plt.close("all")
        return False


def panel_langkah(ds, var_cari, gelombang_kontur, tanggal_init, batas=None):
    """
    Ambil beberapa langkah waktu harian pertama untuk satu variabel,
    dipotong ke wilayah panel. Gelombang yang dipakai untuk garis kontur
    diambil dari indeks waktu yang sama.
    """

    batas = BATAS_PANEL if batas is None else batas

    utama = get_variable(ds, [var_cari])

    if utama is None or "time" not in utama.dims:
        return None

    gelombang = {}

    for nama_var, _, _, _ in gelombang_kontur:
        da = get_variable(ds, [nama_var])

        if da is not None:
            gelombang[nama_var] = da

    langkah = []

    for indeks, tanggal_fc, lead in daftar_lead(utama, tanggal_init):
        if len(langkah) >= JUMLAH_PANEL:
            break

        hasil = sumbu_peta(utama.isel(time=indeks))

        if hasil is None:
            continue

        lat, lon, nilai = hasil
        c_lat, c_lon, c_nilai = potong_wilayah(lat, lon, nilai, batas)

        isi = {}

        for nama_var, da in gelombang.items():
            g = sumbu_peta(da.isel(time=indeks))

            if g is None:
                continue

            _, _, isi[nama_var] = potong_wilayah(g[0], g[1], g[2], batas)

        langkah.append((tanggal_fc, lead, c_lat, c_lon, c_nilai, isi))

    return langkah if len(langkah) >= 2 else None


def simpan_panel_variabel(
    ds, output_folder, tanggal_init, nama_file, var_cari,
    judul_var, label_warna, gelombang_kontur, wilayah="indonesia",
):
    """Satu gambar panel multi-tanggal untuk satu variabel dan satu wilayah."""

    pengaturan = PANEL_WILAYAH[wilayah]
    batas = pengaturan["batas"]

    langkah = panel_langkah(ds, var_cari, gelombang_kontur, tanggal_init, batas)

    if langkah is None:
        print(f"  [SKIP] panel {nama_file} {wilayah}: data kurang dari dua langkah.")
        return

    level_gelombang = {}
    satuan_kontur = {}

    for nama_var, _, _, _ in gelombang_kontur:
        sumber = get_variable(ds, [nama_var])

        if sumber is None:
            continue

        satuan_kontur[nama_var] = satuan_variabel(sumber, "W m⁻²")

        # Ambang garis dihitung dari sebaran wilayah panel itu sendiri,
        # sama seperti peta reguler pada wilayah yang sama.
        ambang = ambang_kontur(sebaran_wilayah(sumber, tanggal_init, batas))

        if ambang is not None:
            level_gelombang[nama_var] = ambang

    # Skala warna diambil dari kunci yang sama dengan peta reguler, bukan
    # dari min/max delapan tanggal panel, supaya warna panel dan peta
    # aslinya sama persis untuk nilai yang sama.
    batas_warna = kunci_warna_variabel(get_variable(ds, [var_cari]), tanggal_init)

    if batas[1] - batas[0] >= 360:
        wilayah_teks = f"Wilayah: seluruh bujur, {abs(batas[2]):.0f} LS-{batas[3]:.0f} LU"
    else:
        wilayah_teks = (
            f"Wilayah: {batas[0]:.0f}-{batas[1]:.0f} BT, "
            f"{abs(batas[2]):.0f} LS-{batas[3]:.0f} LU"
        )

    keterangan = [
        f"Initial: {tanggal_indonesia(tanggal_init)}",
        f"Panel: {tanggal_indonesia(tanggal_init)} sampai "
        f"{tanggal_indonesia(langkah[-1][0])}",
        "Semua panel adalah prakiraan CFS, bukan observasi",
        wilayah_teks,
    ]

    if level_gelombang:
        if len(gelombang_kontur) == 1 and gelombang_kontur[0][0] == var_cari:
            keterangan.append(
                f"Garis: ±{FAKTOR_SIGMA:.0f} simpangan data {judul_var}"
            )
        else:
            keterangan.append(
                f"Garis: ±{FAKTOR_SIGMA:.0f} simpangan data per gelombang"
            )

    folder = output_folder / tanggal_init
    folder.mkdir(parents=True, exist_ok=True)

    # Panel Indonesia mempertahankan judul lama; hanya panel dunia yang
    # menyebut wilayahnya supaya keduanya tidak tampak produk sama.
    judul_wilayah = " Dunia" if wilayah == "dunia" else ""

    render_panel(
        langkah,
        folder / f"{nama_file}.panel.{wilayah}.png",
        f"Panel {judul_var}{judul_wilayah} - {tanggal_indonesia(tanggal_init)}",
        "   |   ".join(keterangan),
        level_gelombang,
        batas_warna,
        label_warna,
        satuan_kontur,
        batas,
        pengaturan["lebar"],
    )


def simpan_panel(ds, output_folder, tanggal_init, dataset_name):
    """
    Merender panel multi-tanggal untuk semua variabel pada satu file
    inisial, baik dataset OLR maupun PWAT. Panel berlatar variabel utama
    memakai garis semua gelombang; panel variabel gelombang memakai garis
    dari variabelnya sendiri. Label satuan dibaca dari file karena OLR dan
    PWAT memakai satuan berbeda untuk variabel gelombang yang sama.
    """

    if dataset_name == "OLR":
        nama_utama, var_utama, judul_utama, label_utama = (
            "olr", "olr", "OLR + Gelombang CFS", "OLR",
        )
        satuan_cadangan = "W m⁻²"
    else:
        nama_utama, var_utama, judul_utama, label_utama = (
            "pwat", "pr_wtr", "PWAT", "PWAT",
        )
        satuan_cadangan = "kg m⁻²"

    sumber_utama = get_variable(ds, [var_utama])

    if sumber_utama is None:
        print(f"  [SKIP] panel: variabel utama {var_utama} tidak ada.")
        return

    satuan_utama = satuan_variabel(sumber_utama, satuan_cadangan)

    produk = [
        (
            nama_utama, var_utama, judul_utama,
            f"{label_utama} ({satuan_utama})", GELOMBANG_PANEL,
        ),
    ]

    for nama_var, label in VARIABEL_GELOMBANG.items():
        sumber = get_variable(ds, [nama_var])

        if sumber is None:
            continue

        produk.append(
            (
                nama_var, nama_var, label,
                f"{label} ({satuan_variabel(sumber, satuan_utama)})",
                kontur_diri(nama_var),
            )
        )

    for nama_file, var_cari, judul_var, label_warna, kontur in produk:
        for wilayah in PANEL_WILAYAH:
            simpan_panel_variabel(
                ds, output_folder, tanggal_init, nama_file, var_cari,
                judul_var, label_warna, kontur, wilayah,
            )


# ============================================================
# TIME SERIES
# ============================================================

def save_timeseries(da, output_file, title, unit):
    """
    Membuat grafik time-series rata-rata spasial.
    """

    try:
        if "time" not in da.dims:
            print(f"  [SKIP] tidak ada dimensi time: {title}")
            return False

        spatial_dims = [
            d for d in da.dims
            if d.lower() in ["lat", "latitude", "lon", "longitude"]
        ]

        series = da.mean(dim=spatial_dims, skipna=True) if spatial_dims else da

        values = clean_data(series.values)

        waktu = np.asarray(da["time"].values, dtype="datetime64[ns]")
        label = np.datetime_as_string(waktu, unit="D")

        fig, ax = plt.subplots(figsize=(13, 5))

        ax.plot(values, linewidth=2)

        ax.set_xlabel("Tanggal")
        ax.set_ylabel(unit)
        ax.set_title(title, fontsize=14, fontweight="bold")
        ax.grid(True, alpha=0.25)

        tick = max(1, len(label) // 12)
        ax.set_xticks(range(0, len(label), tick))
        ax.set_xticklabels(label[::tick], rotation=45, ha="right", fontsize=8)

        fig.tight_layout()
        fig.savefig(output_file, dpi=180, bbox_inches="tight")
        plt.close(fig)

        print(f"  [OK] {output_file.name}")

        return True

    except Exception as e:
        print(f"  [ERROR] Time Series: {e}")
        plt.close("all")

        return False


# ============================================================
# PROSES SATU FILE NETCDF
# ============================================================

def proses_file(argumen):
    """
    Memproses satu file NetCDF. Dibuat fungsi tingkat modul agar bisa
    dijalankan paralel oleh beberapa proses.
    """

    nc_file, dataset_name, main_variable, unit, mode, root_olr, root_pwat = argumen

    tanggal_init = tanggal_dari_nc(Path(nc_file))
    output_folder = buat_folder_output(
        Path(root_olr if dataset_name == "OLR" else root_pwat), tanggal_init
    )

    print()
    print("=" * 70)
    print(f"MEMPROSES {dataset_name} INISIAL {tanggal_init}")
    print("=" * 70)
    print(f"File : {nc_file}")

    try:
        ds = xr.open_dataset(nc_file, decode_times=True)
    except Exception as e:
        print(f"Gagal membuka NetCDF: {e}")
        return

    main = get_variable(ds, [main_variable])

    if mode == "biasa":
        if main is not None:
            simpan_peta(
                main, output_folder, main_variable, dataset_name,
                tanggal_init, unit, ds, GELOMBANG_PANEL,
            )

            save_timeseries(
                main,
                output_folder / "timeseries.png",
                f"Time Series {dataset_name} - Initial {tanggal_indonesia(tanggal_init)}",
                unit,
            )
        else:
            print(f"  [WARNING] variable utama {main_variable} tidak ditemukan.")

        for variable, label in VARIABEL_GELOMBANG.items():
            da = get_variable(ds, [variable])

            if da is None:
                print(f"  [SKIP] {variable} tidak tersedia.")
                continue

            simpan_peta(
                da, output_folder, variable, label, tanggal_init, unit,
                ds, kontur_diri(variable),
            )

    # Panel dibuat untuk semua dataset; overlay gaya PNJ hanya OLR karena
    # isian dan ambang warnanya memakai OLR.
    if mode == "panel":
        simpan_panel(ds, output_folder, tanggal_init, dataset_name)
    elif dataset_name == "OLR":
        simpan_overlay(ds, output_folder, tanggal_init)

    ds.close()

    print(f"Selesai {dataset_name} inisial {tanggal_init}.")


# ============================================================
# MAIN
# ============================================================

def main():
    parser = argparse.ArgumentParser(
        description="Visualisasi NetCDF BMKG (OLR/PWAT + gelombang)."
    )
    parser.add_argument(
        "--inisial",
        nargs="*",
        default=[],
        help="Hanya proses tanggal inisial tertentu, misal 20261017 20261021.",
    )
    parser.add_argument(
        "--dataset",
        nargs="*",
        default=["OLR", "PWAT"],
        help="Dataset yang diproses: OLR, PWAT.",
    )
    parser.add_argument(
        "--proses",
        type=int,
        default=max(1, cpu_count()),
        help="Jumlah proses paralel untuk render.",
    )
    parser.add_argument(
        "--overlay",
        action="store_true",
        help="Hanya render overlay gaya PNJ, lewati peta reguler.",
    )
    parser.add_argument(
        "--panel",
        action="store_true",
        help="Hanya render panel multi-tanggal (dunia + Indonesia), lewati peta reguler dan overlay.",
    )
    parser.add_argument(
        "--out",
        default="",
        help="Folder output lain untuk uji coba tanpa menimpa assets/map.",
    )
    args = parser.parse_args()

    if args.panel:
        mode = "panel"
    elif args.overlay:
        mode = "overlay"
    else:
        mode = "biasa"

    if args.out:
        global OUTPUT_DIR, OLR_OUTPUT, PWAT_OUTPUT

        OUTPUT_DIR = Path(args.out).resolve()
        OLR_OUTPUT = OUTPUT_DIR / "OLR"
        PWAT_OUTPUT = OUTPUT_DIR / "PWAT"

    konfigurasi = {
        "OLR": (OLR_DIR, "olr.waves.init.", "olr", "W m⁻²"),
        "PWAT": (PWAT_DIR, "pwat.waves.init.", "pr_wtr", "kg m⁻²"),
    }

    tugas = []

    for nama_dataset in args.dataset:
        if nama_dataset not in konfigurasi:
            print(f"[WARNING] Dataset tidak dikenal: {nama_dataset}")
            continue

        folder, prefix, variabel, unit = konfigurasi[nama_dataset]

        for nc in find_all_nc(folder, prefix):
            if args.inisial and tanggal_dari_nc(nc) not in args.inisial:
                continue

            tugas.append(
                (
                    str(nc), nama_dataset, variabel, unit, mode,
                    str(OLR_OUTPUT), str(PWAT_OUTPUT),
                )
            )

    print()
    print("=" * 70)
    print("     VISUALISASI NETCDF BMKG")
    print("     OLR + PWAT + MJO + KELVIN + ER + LOW + MRG/TD")
    print("=" * 70)
    print(f"Project      : {BASE_DIR}")
    print(f"Cartopy      : {'ya' if PAKAI_CARTOPY else 'tidak (fallback GeoJSON)'}")
    print(f"Region       : {', '.join(REGION)}")
    print(f"Lead time    : harian {LEAD_HARIAN} hari, lalu per {LEAD_MINGGU} hari")
    print(f"Gambar rencana: {len(tugas)} file NetCDF")

    if not tugas:
        print()
        print("[WARNING] Tidak ada file NetCDF yang cocok.")
        return

    if args.proses > 1:
        with Pool(processes=args.proses) as pool:
            pool.map(proses_file, tugas)
    else:
        for t in tugas:
            proses_file(t)

    print()
    print("=" * 70)
    print("SEMUA DATA NETCDF SELESAI DIPROSES")
    print("=" * 70)
    print("Folder OLR :", OLR_OUTPUT)
    print("Folder PWAT:", PWAT_OUTPUT)


if __name__ == "__main__":
    main()
