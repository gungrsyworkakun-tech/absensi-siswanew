<?php
/**
 * Baca dan urai file Word (.docx) berisi soal ujian.
 * Tidak butuh library tambahan dan tidak butuh ekstensi php-zip (hanya zlib + DOM, yang umumnya aktif).
 * Wajib di-require bersama _lib.php.
 */

/** Baca isi file zip (.docx) ke array [nama => isi]. $filter: fungsi(nama) => bool untuk memilih entri. */
function ujianZipBaca($file, $filter = null) {
    $data = @file_get_contents($file);
    if ($data === false || strlen($data) < 22) return null;
    $eocd = strrpos($data, "PK\x05\x06");
    if ($eocd === false) return null;

    $h = unpack('vdisk/vdiskcd/ventriesdisk/ventries/Vcdsize/Vcdoffset/vcommentlen', substr($data, $eocd + 4, 18));
    $pos = $h['cdoffset'];
    $out = [];
    $batas = 20 * 1024 * 1024; // pengaman terhadap file zip yang dirancang meledak saat diekstrak

    for ($i = 0; $i < $h['entries']; $i++) {
        if (substr($data, $pos, 4) !== "PK\x01\x02") break;
        $c = unpack('vver/vvermin/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vintattr/Vextattr/Voffset', substr($data, $pos + 4, 42));
        $name = substr($data, $pos + 46, $c['nlen']);
        $pos += 46 + $c['nlen'] + $c['elen'] + $c['clen'];

        if ($filter && !$filter($name)) continue;
        if ($c['usize'] > $batas) continue;
        if (substr($data, $c['offset'], 4) !== "PK\x03\x04") continue;

        $lh = unpack('vnlen/velen', substr($data, $c['offset'] + 26, 4));
        $start = $c['offset'] + 30 + $lh['nlen'] + $lh['elen'];
        $raw = substr($data, $start, $c['csize']);

        if ($c['method'] === 0)      $isi = $raw;
        elseif ($c['method'] === 8)  $isi = @gzinflate($raw, $batas);
        else continue;
        if ($isi === false) continue;
        $out[$name] = $isi;
    }
    return $out;
}

/**
 * Urai file .docx menjadi daftar soal.
 * Mengembalikan ['soal' => [...], 'peringatan' => [...]] atau ['error' => '...'].
 * Gambar dari soal yang valid langsung disimpan ke uploads/ujian_soal/.
 */
function ujianParseDocx($path) {
    if (!class_exists('DOMDocument')) {
        return ['error' => 'Ekstensi PHP XML/DOM belum aktif di server (paket php-xml). Hubungi admin server.'];
    }

    $files = ujianZipBaca($path, function ($n) {
        return $n === 'word/document.xml' || $n === 'word/_rels/document.xml.rels' || strpos($n, 'word/media/') === 0;
    });
    if (!$files || !isset($files['word/document.xml'])) {
        return ['error' => 'File tidak terbaca sebagai dokumen Word (.docx). Buka di Word lalu simpan ulang sebagai .docx.'];
    }

    // Peta rId -> lokasi file gambar
    $rels = [];
    if (isset($files['word/_rels/document.xml.rels'])) {
        $r = new DOMDocument();
        if (@$r->loadXML($files['word/_rels/document.xml.rels'], LIBXML_NONET)) {
            foreach ($r->getElementsByTagName('Relationship') as $el) {
                $rels[$el->getAttribute('Id')] = $el->getAttribute('Target');
            }
        }
    }

    $dom = new DOMDocument();
    if (!@$dom->loadXML($files['word/document.xml'], LIBXML_NONET)) {
        return ['error' => 'Isi dokumen Word tidak bisa dibaca. File mungkin rusak.'];
    }
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
    $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');
    $xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

    // 1) Kumpulkan paragraf: teks + gambar
    $items = [];
    foreach ($xp->query('//w:body//w:p') as $p) {
        $teks = '';
        foreach ($xp->query('.//w:t | .//m:t | .//w:tab', $p) as $n) {
            $teks .= $n->localName === 'tab' ? ' ' : $n->textContent;
        }
        $teks = trim(str_replace("\xC2\xA0", ' ', $teks));

        $gambar = [];
        $sudah = [];
        foreach ($xp->query('.//a:blip/@r:embed | .//v:imagedata/@r:id', $p) as $attr) {
            $target = $rels[$attr->value] ?? null;
            if (!$target) continue;
            $full = $target[0] === '/' ? ltrim($target, '/') : 'word/' . $target;
            if (isset($files[$full])) {
                $hash = md5($files[$full]);
                if (isset($sudah[$hash])) continue; // dedup (Word kadang menulis gambar 2x)
                $sudah[$hash] = true;
                $gambar[] = ['bytes' => $files[$full], 'ext' => strtolower(pathinfo($full, PATHINFO_EXTENSION))];
            }
        }
        if ($teks !== '' || $gambar) $items[] = ['teks' => $teks, 'gambar' => $gambar];
    }

    // 2) Mesin status: kenali nomor soal, opsi, kunci, bobot, pedoman
    $reSoal    = '/^\s*(?:soal\s*)?(\d{1,3})\s*[.)]\s*(.*)$/isu';
    $reOpsi    = '/^\s*\(?([A-Ea-e])\s*[.)]\s*(.+)$/su';
    $reKunci   = '/^\s*(?:kunci(?:\s*jawaban)?|jawaban)\s*[:=]\s*\(?([A-Ea-e])\)?\s*$/iu';
    $reBobot   = '/^\s*(?:bobot|skor|poin)\s*[:=]\s*([\d.,]+)/iu';
    $rePedoman = '/^\s*(?:pedoman(?:\s*penilaian)?|rubrik)\s*[:=]\s*(.*)$/isu';
    $reTag     = '/\[\s*(essay|esai|uraian|pg|pilihan\s*ganda)\s*\]/iu';

    $daftar = [];
    $cur = null;
    $mode = null;
    $peringatan = [];
    $abaikanAwal = 0;

    $baru = function ($no, $teks) {
        return ['no' => $no, 'pertanyaan' => $teks, 'opsi' => [], 'kunci' => '', 'pedoman' => '', 'bobot' => 1,
                'paksa' => null, '_img' => null];
    };

    foreach ($items as $it) {
        $t = $it['teks'];
        $imgs = $it['gambar'];

        if ($t !== '' && preg_match($reSoal, $t, $m)) {
            if ($cur) $daftar[] = $cur;
            $cur = $baru((int)$m[1], trim($m[2]));
            $mode = 'q';
            foreach ($imgs as $g) {
                if ($cur['_img'] === null) $cur['_img'] = $g;
                else $peringatan[] = "Soal {$cur['no']}: hanya satu gambar per soal, gambar tambahan diabaikan.";
            }
            continue;
        }

        if (!$cur) {
            if ($t !== '' || $imgs) $abaikanAwal++;
            continue;
        }

        foreach ($imgs as $g) {
            if ($mode === 'q' && $cur['_img'] === null) {
                $cur['_img'] = $g;
            } elseif ($mode === 'q') {
                $peringatan[] = "Soal {$cur['no']}: hanya satu gambar per soal, gambar tambahan diabaikan.";
            } else {
                $peringatan[] = "Soal {$cur['no']}: gambar di bawah opsi/pedoman diabaikan. Letakkan gambar di paragraf soal, sebelum opsi A.";
            }
        }
        if ($t === '') continue;

        if (preg_match($rePedoman, $t, $m)) { $cur['pedoman'] = trim($m[1]); $mode = 'ped'; continue; }
        if (preg_match($reKunci, $t, $m))   { $cur['kunci'] = strtolower($m[1]); $mode = 'x'; continue; }
        if (preg_match($reBobot, $t, $m))   { $cur['bobot'] = (float)str_replace(',', '.', $m[1]); $mode = 'x'; continue; }
        if ($mode !== 'ped' && preg_match($reOpsi, $t, $m)) {
            $k = strtolower($m[1]);
            $cur['opsi'][$k] = trim($m[2]);
            $mode = 'opt:' . $k;
            continue;
        }

        // Baris lanjutan dari bagian sebelumnya
        if ($mode === 'q')                   $cur['pertanyaan'] .= "\n" . $t;
        elseif ($mode === 'ped')             $cur['pedoman'] .= "\n" . $t;
        elseif (strpos((string)$mode, 'opt:') === 0) {
            $k = substr($mode, 4);
            $cur['opsi'][$k] .= ' ' . $t;
        }
    }
    if ($cur) $daftar[] = $cur;

    if ($abaikanAwal > 0) {
        $peringatan[] = "{$abaikanAwal} paragraf sebelum soal nomor pertama diabaikan (biasanya petunjuk).";
    }
    if (count($daftar) > 200) {
        $daftar = array_slice($daftar, 0, 200);
        $peringatan[] = 'Hanya 200 soal pertama yang dibaca.';
    }

    // 3) Validasi tiap soal dan simpan gambarnya
    $hasil = [];
    foreach ($daftar as $s) {
        $err = null;
        $pert = trim($s['pertanyaan']);
        if (preg_match($reTag, $pert, $m)) {
            $tag = strtolower(preg_replace('/\s+/', '', $m[1]));
            $s['paksa'] = in_array($tag, ['pg', 'pilihanganda'], true) ? 'pilihan_ganda' : 'essay';
            $pert = trim(preg_replace($reTag, '', $pert, 1));
        }

        $opsi = array_filter($s['opsi'], fn($v) => $v !== '');
        $tipe = $s['paksa'] ?: (count($opsi) > 0 ? 'pilihan_ganda' : 'essay');

        if ($pert === '') {
            $err = 'Teks pertanyaan kosong.';
        } elseif ($tipe === 'pilihan_ganda') {
            if (count($opsi) < 2)                  $err = 'Pilihan ganda butuh minimal opsi A dan B.';
            elseif ($s['kunci'] === '')            $err = 'Baris "Kunci: X" tidak ditemukan.';
            elseif (!isset($opsi[$s['kunci']]))    $err = 'Kunci (' . strtoupper($s['kunci']) . ') tidak cocok dengan opsi yang ada.';
        }

        $bobot = ($s['bobot'] > 0) ? min(100, $s['bobot']) : 1;

        $gambar = null;
        if (!$err && $s['_img'] !== null) {
            $gambar = ujianSimpanBytesGambar($s['_img']['bytes']);
            if (!$gambar) {
                $peringatan[] = "Soal {$s['no']}: gambar dilewati (format harus JPG/PNG/GIF/WebP, maksimal 3 MB, dan folder uploads/ujian_soal harus bisa ditulis).";
            }
        }

        $ops = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $k) $ops[$k] = ($tipe === 'pilihan_ganda' && isset($opsi[$k])) ? $opsi[$k] : null;

        $hasil[] = [
            'no'         => $s['no'],
            'tipe'       => $tipe,
            'pertanyaan' => $pert,
            'opsi'       => $ops,
            'kunci'      => $tipe === 'pilihan_ganda' ? $s['kunci'] : null,
            'pedoman'    => $tipe === 'essay' ? trim($s['pedoman']) : '',
            'bobot'      => $bobot,
            'gambar'     => $gambar,
            'error'      => $err,
        ];
    }

    return ['soal' => $hasil, 'peringatan' => array_values(array_unique($peringatan))];
}