# ============================================================
# PROSES HOVMOLLER CFS BMKG
#
# Membaca NetCDF gelombang CFS kontinu
#   data/netcdf/CFS/OLR/olr.waves.cfs.nc
#   data/netcdf/CFS/PWAT/pwat.waves.cfs.nc
# lalu membuat Diagram Hovmoller rata-rata sabuk lintang bergaya
# InAWMS: isian anomali + kontur MJO / Kelvin / ER / Low.
#
# Yang ditambah dibanding gambar bawaan InAWMS:
#   - label Initial dan garis "Mulai Prakiraan", dibaca dari atribut
#     lastObsTime / lastObsInd pada variabel time (bukan asumsi)
#   - peta kecil penunjuk posisi sabuk lintang terhadap garis pantai
#   - penanda bujur 95 BT dan 141 BT pada versi dunia
#   - versi bujur Indonesia (90 - 150 BT) dengan akhiran kode "id"
#
# Hasil TIDAK menimpa gambar resmi yang sudah ada. Semua ditulis ke
#   assets/hovmoller/render/<OLR|PWAT>/<YYYYMMDD>/<produk>.cfs.<pita>.png
# agar terindeks otomatis oleh galeri.php dan inawms.php.
#
# Contoh:
#   python proses_hovmoller.py
#   python proses_hovmoller.py --produk OLR --inisial 20261003
#   python proses_hovmoller.py --pita eqtr wide --depan 40
# ============================================================

from pathlib import Path
import argparse
import datetime as dt
import json
import sys
import warnings

warnings.filterwarnings("ignore")

import numpy as np
import xarray as xr
import matplotlib

matplotlib.use("Agg")

import matplotlib.pyplot as plt
from matplotlib.colors import BoundaryNorm


# ============================================================
# KONFIGURASI PROJECT
# ============================================================

BASE_DIR = Path(__file__).resolve().parent

NETCDF_DIR = BASE_DIR / "data" / "netcdf" / "CFS"
OUTPUT_DIR = BASE_DIR / "assets" / "hovmoller" / "render"
COASTLINE_GEOJSON = BASE_DIR / "assets" / "geodata" / "coastline110.geojson"

# Atribut time memakai satuan "days since 1800-01-01".
EPOK = dt.date(1800, 1, 1)

BUJUR_DUNIA = (0.0, 360.0)
BUJUR_ID = (90.0, 150.0)
BATAS_ID = (95.0, 141.0)

PRODUK = {
    "OLR": {
        "nc": NETCDF_DIR / "OLR" / "olr.waves.cfs.nc",
        "variabel": "olr",
        "nama_file": "olr",
        "nama_tampil": "OLR",
        "unit": "W m-2",
        "langkah_isi": 8.0,
        "langkah_kontur": 16.0,
        "batas_isi_maks": 88.0,
    },
    "PWAT": {
        "nc": NETCDF_DIR / "PWAT" / "pwat.waves.cfs.nc",
        "variabel": "pr_wtr",
        "nama_file": "pwat",
        "nama_tampil": "PWAT",
        "unit": "kg m-2",
        "langkah_isi": 2.0,
        "langkah_kontur": 3.0,
        "batas_isi_maks": 22.0,
    },
}

# Komponen gelombang yang dikontur: (nama variabel, warna, label).
GELOMBANG = [
    ("mjo", "black", "MJO"),
    ("kelvin", "#1f4fd8", "Kelvin"),
    ("er", "red", "ER"),
    ("low", "purple", "Low"),
]

# Sabuk lintang: kode pada nama file -> (selatan, utara, label).
# Kode hanya berisi huruf/angka kecil karena inawms.php membacanya
# dari pola "olr.cfs.<kode>.png".
PITA = {
    "wide":     (-20.0, 20.0, "20°S – 20°N"),
    "north":    (0.0, 10.0, "0° – 10°N"),
    "n810":     (8.0, 10.0, "8°N – 10°N"),
    "n68":      (6.0, 8.0, "6°N – 8°N"),
    "n46":      (4.0, 6.0, "4°N – 6°N"),
    "n24":      (2.0, 4.0, "2°N – 4°N"),
    "n02":      (0.0, 2.0, "0° – 2°N"),
    "eqtr":     (-5.0, 5.0, "5°S – 5°N"),
    "eqtrs2n2": (-2.0, 2.0, "2°S – 2°N"),
    "eqtrs1n1": (-1.0, 1.0, "1°S – 1°N"),
    "s02":      (-2.0, 0.0, "2°S – 0°"),
    "s24":      (-4.0, -2.0, "4°S – 2°S"),
    "s46":      (-6.0, -4.0, "6°S – 4°S"),
    "s68":      (-8.0, -6.0, "8°S – 6°S"),
    "s810":     (-10.0, -8.0, "10°S – 8°S"),
    "south":    (-10.0, 0.0, "10°S – 0°"),
}

# Versi bujur Indonesia hanya dibuat untuk pita ini.
PITA_ID = ["eqtr", "eqtrs2n2", "wide"]

NAMA_BULAN = [
    "", "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
]

WARNA_PITA = "#ffd24d"
WARNA_ID = "#2f7d32"
WARNA_MULAI_PRAKIRAAN = "black"

WARNA_ISI = matplotlib.colormaps["BrBG"]

_COAST_CACHE = None


# ============================================================
# BANTU
# ============================================================

def tanggal_indonesia(tanggal):
    return f"{tanggal.day} {NAMA_BULAN[tanggal.month]} {tanggal.year}"


def hari_dari_nilai(nilai):
    return EPOK + dt.timedelta(days=int(round(float(nilai))))


def muat_garis_pantai():
    """Garis pantai Natural Earth 110m dari GeoJSON lokal (bujo digeser 0..360)."""

    global _COAST_CACHE

    if _COAST_CACHE is not None:
        return _COAST_CACHE

    garis = []

    if not COASTLINE_GEOJSON.exists():
        print(f"[WARNING] GeoJSON garis pantai tidak ditemukan: {COASTLINE_GEOJSON}")
        _COAST_CACHE = garis
        return garis

    try:
        with open(COASTLINE_GEOJSON, encoding="utf-8") as f:
            geo = json.load(f)

        def tambah(koordinat):
            arr = np.asarray(koordinat, dtype=float)
            if arr.ndim == 2 and arr.shape[0] >= 2 and arr.shape[1] >= 2:
                x = arr[:, 0].copy()
                x[x < 0] += 360.0
                garis.append(np.column_stack([x, arr[:, 1]]))

        for fitur in geo.get("features", []):
            geometri = fitur.get("geometry", {})
            jenis = geometri.get("type")
            koordinat = geometri.get("coordinates", [])

            if jenis == "LineString":
                tambah(koordinat)
            elif jenis == "MultiLineString":
                for bagian in koordinat:
                    tambah(bagian)
            elif jenis == "Polygon":
                for cincin in koordinat:
                    tambah(cincin)
            elif jenis == "MultiPolygon":
                for poligon in koordinat:
                    for cincin in poligon:
                        tambah(cincin)

        _COAST_CACHE = garis

    except Exception as error:
        print(f"[WARNING] Gagal memuat garis pantai: {error}")
        _COAST_CACHE = []

    return _COAST_CACHE


def gambar_peta_pita(ax, batas_bujur, lat_min, lat_max):
    """Peta kecil penunjuk posisi sabuk lintang dan wilayah Indonesia."""

    ax.set_facecolor("#dceaf5")

    for arr in muat_garis_pantai():
        x, y = arr[:, 0], arr[:, 1]
        pilih = (x >= batas_bujur[0]) & (x <= batas_bujur[1])

        if pilih.sum() >= 2:
            ax.plot(x[pilih], y[pilih], color="#6b6b6b", linewidth=0.4, zorder=3)

    ax.axhspan(lat_min, lat_max, color=WARNA_PITA, alpha=0.6, zorder=2)

    ax.plot(
        [BATAS_ID[0], BATAS_ID[1], BATAS_ID[1], BATAS_ID[0], BATAS_ID[0]],
        [-12.0, -12.0, 8.0, 8.0, -12.0],
        color=WARNA_ID,
        linewidth=1.0,
        zorder=4,
    )

    ax.set_xlim(*batas_bujur)
    ax.set_ylim(-30.0, 30.0)
    ax.set_xticks([])
    ax.set_yticks([])
    ax.set_title("Posisi sabuk lintang", fontsize=7.5, pad=2)

    for tepi in ax.spines.values():
        tepi.set_color("#999999")


def bujur_tik(batas_bujur):
    if batas_bujur[1] - batas_bujur[0] > 200:
        return [v for v in (0, 60, 120, 180, 240, 300)
                if batas_bujur[0] <= v <= batas_bujur[1]]

    jarak = 10 if batas_bujur[1] - batas_bujur[0] <= 80 else 20
    mulai = int(np.ceil(batas_bujur[0] / jarak) * jarak)
    return list(range(mulai, int(batas_bujur[1]), jarak))


def label_bujur(nilai):
    nilai = float(nilai) % 360.0

    if abs(nilai - 180.0) < 1e-6:
        return "180"
    if nilai == 0:
        return "0"
    if nilai < 180.0:
        return f"{nilai:.0f}E"
    return f"{360.0 - nilai:.0f}W"


def indeks_pita(lat, lat_min, lat_max):
    """Indeks grid dalam sabuk lintang (grid boleh naik atau turun)."""

    return np.where((lat >= lat_min - 1e-6) & (lat <= lat_max + 1e-6))[0]


def indeks_terakhir_teramati(ds):
    """
    Batas analisis vs prakiraan dibaca dari atribut file.
    lastObsTime = nilai time terakhir yang masih hasil pengamatan.
    """

    variabel_time = ds["time"]
    attrs = variabel_time.attrs
    nilai = attrs.get("lastObsTime")

    if nilai is not None:
        cocok = np.where(np.isclose(variabel_time.values.astype(float), float(nilai)))[0]

        if cocok.size:
            return int(cocok[-1])

    urutan = attrs.get("lastObsInd")

    if urutan is not None:
        idx = int(round(float(urutan))) - 1

        if 0 <= idx < variabel_time.size:
            return idx

    return int(variabel_time.size) - 1


# ============================================================
# RENDER SATU GAMBAR
# ============================================================

def batas_warna(nilai, langkah, batas_maks):
    """
    Batas isian simetris untuk satu sabuk lintang, dihitung dari seluruh
    bujur sehingga versi dunia dan versi Indonesia memakai skala yang sama.
    """

    if nilai.size == 0 or not np.isfinite(nilai).any():
        return langkah

    persentil = np.percentile(np.abs(np.nan_to_num(nilai)), 99)
    batas = max(langkah, np.ceil(persentil / langkah) * langkah)

    return min(batas, batas_maks * 2)


def render_hovmoller(
    nilai_isi,
    kontur,
    konfigurasi,
    label_pita,
    lat_pita,
    waktu,
    indeks_mulai_prakiraan,
    tengah_x,
    batas_bujur,
    batas_isi,
    berkas_keluar,
    dpi,
):
    jumlah_hari = len(waktu)
    indeks_y = np.arange(jumlah_hari)

    if nilai_isi.size == 0 or not np.isfinite(nilai_isi).any():
        return False

    grid_x, grid_y = np.meshgrid(np.asarray(tengah_x, dtype=float), indeks_y)

    langkah_isi = konfigurasi["langkah_isi"]
    level = np.arange(-batas_isi, batas_isi + langkah_isi / 2.0, langkah_isi)
    norm = BoundaryNorm(level, WARNA_ISI.N)

    fig = plt.figure(figsize=(13.5, 8.6), dpi=dpi)
    ax = fig.add_axes([0.075, 0.150, 0.735, 0.740])

    ax.contourf(
        grid_x,
        grid_y,
        np.ma.masked_invalid(nilai_isi),
        levels=level,
        cmap=WARNA_ISI,
        extend="both",
    )

    langkah_kontur = konfigurasi["langkah_kontur"]
    legenda = []

    for nama, warna, label in GELOMBANG:
        peta = kontur.get(nama)

        if peta is None or not np.isfinite(peta).any():
            continue

        terbesar = float(np.nanmax(np.abs(peta)))
        tingkatan = np.arange(langkah_kontur, terbesar + langkah_kontur, langkah_kontur)

        if tingkatan.size == 0:
            continue

        for tingkatan_, gaya in (
            (list(tingkatan), "solid"),
            (list(-tingkatan[::-1]), "dashed"),
        ):
            ax.contour(
                grid_x,
                grid_y,
                np.ma.masked_invalid(peta),
                levels=tingkatan_,
                colors=warna,
                linewidths=1.1,
                linestyles=gaya,
            )

        legenda.append(
            matplotlib.lines.Line2D([], [], color=warna, linewidth=1.4, label=label)
        )

    if 0 <= indeks_mulai_prakiraan < jumlah_hari:
        garis = indeks_mulai_prakiraan + 0.5

        ax.axhline(garis, color=WARNA_MULAI_PRAKIRAAN, linewidth=2.6, zorder=8)
        ax.axhspan(garis, jumlah_hari, color="#000000", alpha=0.05, zorder=6)
        ax.annotate(
            "Mulai Forecast",
            # sumbu waktu dibalik, sehingga posisi dari bawah = 1 - fraksi indeks
            xy=(1.012, 1.0 - garis / jumlah_hari),
            xycoords="axes fraction",
            fontsize=11,
            fontweight="bold",
            va="center",
            ha="left",
        )

    if batas_bujur[1] - batas_bujur[0] > 200:
        for bujur_id, teks in zip(BATAS_ID, ("95°BT", "141°BT")):
            ax.axvline(bujur_id, color=WARNA_ID, linewidth=1.1, linestyle=":", zorder=7)
            ax.text(
                bujur_id,
                1.012,
                teks,
                transform=ax.get_xaxis_transform(),
                fontsize=8,
                color=WARNA_ID,
                va="bottom",
                ha="center",
                clip_on=False,
            )

    ax.set_ylim(jumlah_hari, 0)
    ax.set_xlim(*batas_bujur)

    # Label waktu mingguan agar tidak saling menumpuk pada jendela panjang.
    jarak_tik = 7 if jumlah_hari > 60 else (2 if jumlah_hari > 28 else 1)
    indeks_tik = indeks_y[::jarak_tik]

    ax.set_yticks(indeks_tik)
    ax.set_yticklabels([waktu[i].strftime("%b-%d") for i in indeks_tik], fontsize=9)
    ax.set_xticks(bujur_tik(batas_bujur))
    ax.set_xticklabels([label_bujur(v) for v in bujur_tik(batas_bujur)], fontsize=10)
    ax.grid(color="#bbbbbb", linewidth=0.4, alpha=0.5, zorder=1)
    ax.set_axisbelow(True)

    wilayah = "Bujur 90–150°BT (Indonesia)" if batas_bujur == BUJUR_ID else "Seluruh dunia"

    fig.suptitle(
        f"Diagram Hovmoller {konfigurasi['nama_tampil']} — Sabuk Lintang {label_pita}",
        fontsize=15,
        fontweight="bold",
        y=0.968,
    )

    tanggal_mulai = waktu[indeks_mulai_prakiraan]
    tanggal_inisial = waktu[indeks_mulai_prakiraan - 1]

    fig.text(
        0.5,
        0.932,
        f"Initial: {tanggal_indonesia(tanggal_inisial)}  ·  "
        f"Forecast: {tanggal_indonesia(tanggal_mulai)} s.d. {tanggal_indonesia(waktu[-1])}  ·  "
        f"{wilayah}  ·  {jumlah_hari} hari",
        ha="center",
        fontsize=9.5,
        color="#333333",
    )

    cax = fig.add_axes([0.14, 0.085, 0.58, 0.030])
    cb = fig.colorbar(
        matplotlib.cm.ScalarMappable(cmap=WARNA_ISI, norm=norm),
        cax=cax,
        orientation="horizontal",
        extend="both",
    )
    cb.set_ticks(list(level[::2]))
    cb.outline.set_linewidth(0.6)

    fig.text(
        0.735,
        0.100,
        f"{konfigurasi['nama_tampil']} anomali ({konfigurasi['unit']})",
        fontsize=9.5,
        va="center",
        ha="left",
    )

    kotak = ax.legend(
        handles=legenda,
        loc="lower right",
        fontsize=10,
        title=f"Kontur per {langkah_kontur:g} {konfigurasi['unit']}",
        framealpha=0.92,
    )
    ax.add_artist(kotak)

    ax_peta = fig.add_axes([0.830, 0.640, 0.150, 0.210])
    gambar_peta_pita(ax_peta, batas_bujur, *lat_pita)

    fig.text(
        0.075,
        0.020,
        "Garis putus-putus = anomali negatif. Dihitung dari data CFS oleh "
        "proses_hovmoller.py; penanda siklon tropis tidak disertakan.",
        fontsize=7.5,
        color="#555555",
    )
    fig.text(
        0.98,
        0.020,
        f"Dibuat: {dt.datetime.now(dt.timezone.utc):%Y-%m-%d %H:%M} UTC · BMKG Kemayoran",
        fontsize=7.5,
        color="#555555",
        ha="right",
    )

    berkas_keluar.parent.mkdir(parents=True, exist_ok=True)
    fig.savefig(berkas_keluar, dpi=dpi)
    plt.close(fig)

    return True


# ============================================================
# PROSES SATU PRODUK
# ============================================================

def proses_produk(produk, inisial, hari_belakang, hari_depan, daftar_pita, dpi, root_keluar):
    konfigurasi = PRODUK[produk]
    berkas = konfigurasi["nc"]

    if not berkas.exists():
        print(f"[LEWAT] {produk}: file tidak ada -> {berkas}")
        return 0

    print(f"\n=== {produk} <- {berkas.name} ===")

    ds = xr.open_dataset(berkas, decode_times=False)

    tanggal = np.array([hari_dari_nilai(v) for v in ds["time"].values.astype(float)])
    lat = np.asarray(ds["lat"].values, dtype=float)
    lon = np.asarray(ds["lon"].values, dtype=float)

    indeks_terakhir = indeks_terakhir_teramati(ds)

    if inisial is not None:
        cocok = np.where(tanggal == inisial)[0]

        if cocok.size == 0:
            print(
                f"[LEWAT] {produk}: tanggal inisial {inisial} tidak ada di file "
                f"(rentang {tanggal[0]} s.d. {tanggal[-1]})."
            )
            ds.close()
            return 0

        indeks_terakhir = int(cocok[-1])

    tanggal_inisial = tanggal[indeks_terakhir]
    indeks_mulai = indeks_terakhir + 1

    if indeks_mulai >= tanggal.size:
        print(f"[LEWAT] {produk}: tidak ada bagian prakiraan setelah {tanggal_inisial}.")
        ds.close()
        return 0

    i0 = max(0, indeks_terakhir - hari_belakang + 1)
    i1 = min(tanggal.size, indeks_mulai + hari_depan)

    jumlah_hari = i1 - i0
    indeks_mulai_prakiraan = indeks_mulai - i0
    waktu = list(tanggal[i0:i1])

    print(
        f"Inisial {tanggal_inisial} · analisis s.d. indeks {indeks_terakhir} · "
        f"jendela {jumlah_hari} hari ({waktu[0]} s.d. {waktu[-1]})"
    )

    urut = np.argsort(lon)
    lon_urut = lon[urut]

    def beresihkan(arr):
        return np.where(np.isfinite(arr), arr, np.nan)

    # Sumbu bujur diseragamkan naik (0 -> 360) agar indeks sama dengan lon_urut.
    base = beresihkan(
        ds[konfigurasi["variabel"]].isel(time=slice(i0, i1)).values[:, :, urut]
    )

    kontur_mentah = {
        nama: beresihkan(ds[nama].isel(time=slice(i0, i1)).values[:, :, urut])
        for nama, _, _ in GELOMBANG
        if nama in ds.variables
    }

    ds.close()

    indeks_waktu = np.arange(jumlah_hari)
    folder_keluar = root_keluar / produk / f"{tanggal_inisial:%Y%m%d}"
    dibuat = 0

    for kode in daftar_pita:
        if kode not in PITA:
            print(f"[LEWAT] pita tidak dikenal: {kode}")
            continue

        lat_min, lat_max, label = PITA[kode]
        indeks_lat = indeks_pita(lat, lat_min, lat_max)

        if indeks_lat.size == 0:
            print(f"[LEWAT] {produk} {kode}: tidak ada grid lintang dalam {label}")
            continue

        skala = batas_warna(
            np.nanmean(base[:, indeks_lat, :], axis=1),
            konfigurasi["langkah_isi"],
            konfigurasi["batas_isi_maks"],
        )

        for varian_id in (False, True):
            if varian_id and kode not in PITA_ID:
                continue

            if varian_id:
                batas_bujur = BUJUR_ID
                pilih_lon = np.where((lon_urut >= BUJUR_ID[0]) & (lon_urut < BUJUR_ID[1]))[0]
                kode_berkas = kode + "id"
            else:
                batas_bujur = BUJUR_DUNIA
                pilih_lon = np.arange(lon_urut.size)
                kode_berkas = kode

            if pilih_lon.size < 2:
                continue

            ambil = np.ix_(indeks_waktu, indeks_lat, pilih_lon)

            nilai_isi = np.nanmean(base[ambil], axis=1)
            data_kontur = {
                nama: np.nanmean(arr[ambil], axis=1)
                for nama, arr in kontur_mentah.items()
            }
            tengah_x = lon_urut[pilih_lon]

            berkas_keluar = folder_keluar / f"{konfigurasi['nama_file']}.cfs.{kode_berkas}.png"

            ok = render_hovmoller(
                nilai_isi,
                data_kontur,
                konfigurasi,
                label,
                (lat_min, lat_max),
                waktu,
                indeks_mulai_prakiraan,
                tengah_x,
                batas_bujur,
                skala,
                berkas_keluar,
                dpi,
            )

            if ok:
                dibuat += 1
                print(f"  [OK] {berkas_keluar.relative_to(BASE_DIR)}")

    return dibuat


# ============================================================
# UTAMA
# ============================================================

def main():
    parser = argparse.ArgumentParser(
        description="Render Diagram Hovmoller dari NetCDF gelombang CFS"
    )
    parser.add_argument("--produk", nargs="*", default=["OLR", "PWAT"], choices=["OLR", "PWAT"])
    parser.add_argument("--inisial", type=str, default=None, help="Tanggal analisis terakhir, YYYYMMDD")
    parser.add_argument("--belakang", type=int, default=120, help="Jumlah hari analisis ke belakang")
    parser.add_argument("--depan", type=int, default=45, help="Jumlah hari prakiraan ke depan")
    parser.add_argument("--pita", nargs="*", default=None, help="Kode sabuk lintang, default semua")
    parser.add_argument("--resolusi", type=int, default=150, help="DPI gambar keluaran")
    parser.add_argument("--output", type=Path, default=OUTPUT_DIR, help="Folder keluaran")

    argumen = parser.parse_args()

    daftar_pita = argumen.pita or list(PITA.keys())

    inisial = None
    if argumen.inisial:
        inisial = dt.datetime.strptime(argumen.inisial, "%Y%m%d").date()

    total = 0

    for produk in argumen.produk:
        total += proses_produk(
            produk,
            inisial,
            argumen.belakang,
            argumen.depan,
            daftar_pita,
            argumen.resolusi,
            argumen.output,
        )

    print(f"\nSelesai: {total} gambar dibuat di {argumen.output.relative_to(BASE_DIR)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
