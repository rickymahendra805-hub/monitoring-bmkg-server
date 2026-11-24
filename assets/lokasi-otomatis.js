/* =========================================================
   DETEKSI LOKASI OTOMATIS
   - Meminta izin lokasi perangkat
   - Memetakan koordinat ke ADM4 melalui api/lokasi.php
   - Menyimpan pilihan ke cookie agar dipakai semua halaman
========================================================= */

(function () {
    'use strict';

    var COOKIE_HARI = 7;
    var sedangBerjalan = false;

    function setCookie(nama, nilai) {
        document.cookie = nama + '=' + encodeURIComponent(String(nilai)) +
            '; path=/; max-age=' + (COOKIE_HARI * 86400) + '; SameSite=Lax';
    }

    function getCookie(nama) {
        var hasil = document.cookie.split('; ').filter(function (baris) {
            return baris.indexOf(nama + '=') === 0;
        })[0];

        return hasil ? decodeURIComponent(hasil.split('=').slice(1).join('=')) : '';
    }

    function simpanLokasi(data) {
        setCookie('bmkg_adm4', data.adm4 || '');
        setCookie('bmkg_provinsi', data.provinsi || '');
        setCookie('bmkg_kotkab', data.kotkab || '');
        setCookie('bmkg_kecamatan', data.kecamatan || '');
        setCookie('bmkg_desa', data.desa || '');
        setCookie('bmkg_kode_provinsi', data.kode_provinsi || '');
        setCookie('bmkg_kode_kabupaten', data.kode_kabupaten || '');
        setCookie('bmkg_kode_kecamatan', data.kode_kecamatan || '');
        setCookie('bmkg_lat', data.lat || '');
        setCookie('bmkg_lon', data.lon || '');
        setCookie('bmkg_perkiraan', data.perkiraan ? '1' : '0');

        try {
            localStorage.setItem('bmkg_lokasi', JSON.stringify(data));
        } catch (e) {
            /* localStorage dapat diblokir; cookie tetap dipakai. */
        }
    }

    function buatToast() {
        var toast = document.getElementById('lokasiToast');

        if (toast) {
            return toast;
        }

        toast = document.createElement('div');
        toast.id = 'lokasiToast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.style.cssText = [
            'position:fixed', 'right:18px', 'bottom:18px', 'z-index:99999',
            'max-width:min(360px,calc(100vw - 36px))', 'padding:14px 16px',
            'border-radius:14px', 'border:1px solid #dbe5f0',
            'background:#ffffff', 'color:#16293f', 'font:12px/1.6 Inter,Arial,sans-serif',
            'box-shadow:0 18px 45px rgba(16,42,67,.18)', 'display:flex',
            'align-items:flex-start', 'gap:10px', 'opacity:0',
            'transform:translateY(10px)', 'transition:.25s'
        ].join(';');

        document.body.appendChild(toast);

        window.setTimeout(function () {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        }, 20);

        return toast;
    }

    function tampilToast(pesan, jenis) {
        var toast = buatToast();
        var warna = jenis === 'error' ? '#d63549' : (jenis === 'success' ? '#129267' : '#1573b8');
        var ikon = jenis === 'error' ? '⚠' : (jenis === 'success' ? '✓' : '⌖');

        toast.innerHTML = '';

        var simbol = document.createElement('span');
        simbol.textContent = ikon;
        simbol.style.cssText = 'font-size:16px;line-height:1.2;color:' + warna + ';flex:0 0 auto';

        var teks = document.createElement('div');
        teks.innerHTML = pesan;

        var tutup = document.createElement('button');
        tutup.type = 'button';
        tutup.setAttribute('aria-label', 'Tutup pemberitahuan');
        tutup.textContent = '×';
        tutup.style.cssText = [
            'margin-left:auto', 'border:0', 'background:transparent', 'color:#5c748c',
            'font-size:18px', 'line-height:1', 'cursor:pointer', 'padding:0 2px'
        ].join(';');
        tutup.addEventListener('click', function () {
            toast.remove();
        });

        toast.appendChild(simbol);
        toast.appendChild(teks);
        toast.appendChild(tutup);
    }

    function bersihkanParameterLokasi() {
        var url = new URL(window.location.href);

        [
            'adm4', 'nama_provinsi', 'nama_kabupaten', 'nama_kecamatan',
            'nama_desa', 'provinsi', 'kabupaten', 'kecamatan', 'desa'
        ].forEach(function (param) {
            url.searchParams.delete(param);
        });

        url.searchParams.set('lokasi', '1');

        return url.toString();
    }

    function mintaKoordinat() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('Perangkat atau browser ini tidak mendukung deteksi lokasi.'));
                return;
            }

            navigator.geolocation.getCurrentPosition(resolve, function (error) {
                var pesan = 'Izin lokasi ditolak.';

                if (error.code === error.POSITION_UNAVAILABLE) {
                    pesan = 'Informasi lokasi tidak tersedia saat ini.';
                } else if (error.code === error.TIMEOUT) {
                    pesan = 'Waktu deteksi lokasi habis.';
                }

                reject(new Error(pesan));
            }, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 300000
            });
        });
    }

    function deteksi(opsi) {
        opsi = opsi || {};

        if (sedangBerjalan) {
            return;
        }

        sedangBerjalan = true;

        if (!opsi.senyap) {
            tampilToast('Mendeteksi lokasi perangkat...');
        }

        mintaKoordinat()
            .then(function (posisi) {
                var lat = posisi.coords.latitude;
                var lon = posisi.coords.longitude;
                var url = 'api/lokasi.php?lat=' + encodeURIComponent(lat) +
                    '&lon=' + encodeURIComponent(lon);

                return fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                }).then(function (respons) {
                    return respons.json();
                });
            })
            .then(function (json) {
                if (!json || !json.success || !json.data || !json.data.adm4) {
                    throw new Error((json && json.message) || 'Lokasi tidak dapat dipetakan ke kode wilayah BMKG.');
                }

                simpanLokasi(json.data);

                try {
                    sessionStorage.setItem('bmkg_auto_selesai', '1');
                } catch (e) {
                    /* abaikan */
                }

                tampilToast(
                    '<strong>Lokasi dikenali:</strong><br>' +
                    (json.data.desa || '') + ', ' + (json.data.kecamatan || '') +
                    ', ' + (json.data.kotkab || '') +
                    '<br><small style="color:#5c748c">Memuat prakiraan untuk lokasi ini...</small>',
                    'success'
                );

                document.dispatchEvent(new CustomEvent('lokasi:terdeteksi', {
                    detail: json.data
                }));

                if (opsi.reload !== false) {
                    window.setTimeout(function () {
                        window.location.replace(bersihkanParameterLokasi());
                    }, 550);
                }
            })
            .catch(function (error) {
                try {
                    sessionStorage.setItem('bmkg_auto_selesai', 'gagal');
                } catch (e) {
                    /* abaikan */
                }

                tampilToast(
                    '<strong>Deteksi lokasi gagal.</strong><br>' +
                    error.message +
                    '<br><a href="prediksi.php" style="color:#1573b8">Pilih wilayah secara manual</a>',
                    'error'
                );
            })
            .finally(function () {
                sedangBerjalan = false;
            });
    }

    function aktifkanTombol() {
        document.querySelectorAll('[data-deteksi-lokasi]').forEach(function (tombol) {
            if (tombol.dataset.lokasiSiap === '1') {
                return;
            }

            tombol.dataset.lokasiSiap = '1';
            tombol.addEventListener('click', function (event) {
                event.preventDefault();
                deteksi({ reload: true });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        aktifkanTombol();

        var skrip = document.currentScript || document.querySelector('script[src*="lokasi-otomatis.js"]');
        var auto = skrip && skrip.getAttribute('data-auto') === '1';
        var cookieAdm4 = getCookie('bmkg_adm4');
        var selesai = '';

        try {
            selesai = sessionStorage.getItem('bmkg_auto_selesai') || '';
        } catch (e) {
            /* abaikan */
        }

        if (auto && !cookieAdm4 && !selesai) {
            window.setTimeout(function () {
                deteksi({ reload: true, senyap: true });
            }, 700);
        }
    });

    window.LokasiOtomatis = {
        deteksi: deteksi,
        simpan: simpanLokasi,
        ambil: function () {
            try {
                return JSON.parse(localStorage.getItem('bmkg_lokasi') || 'null');
            } catch (e) {
                return null;
            }
        }
    };
})();
