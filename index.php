<?php

$tmpDir = __DIR__ . '/tmp';
if (!file_exists($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

if (isset($_GET['pause']) && isset($_GET['url'])) {
    $stopFile = $tmpDir . '/stop_' . md5($_GET['url']);
    file_put_contents($stopFile, '1');
    exit('paused');
}

if (isset($_GET['url'])) {

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');

    function sendProgress($percentage) {
        echo "data: " . json_encode(['percentage' => $percentage]) . "\n\n";
        ob_flush();
        flush();
    }

    function getFilenameFromUrl($url) {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'];
        return basename($path);
    }

    function getRemoteFileSize($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);
        return $size;
    }

    function getStopFile($url) {
        global $tmpDir;
        return $tmpDir . '/stop_' . md5($url);
    }

    function downloadFile($url, $path, $stopFile) {
        global $totalSize, $startSize, $stopFilePath;
        $stopFilePath = $stopFile;
        $totalSize = getRemoteFileSize($url);

        if (file_exists($path)) {
            $startSize = filesize($path);
            $fp = fopen($path, 'a');
        } else {
            $startSize = 0;
            $fp = fopen($path, 'w');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        if ($startSize > 0) {
            curl_setopt($ch, CURLOPT_RANGE, $startSize . '-');
        }
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    function progress($resource, $download_size, $downloaded, $upload_size, $uploaded) {
        global $totalSize, $startSize, $stopFilePath;
        if ($totalSize > 0) {
            $percentage = ($startSize + $downloaded) / $totalSize * 100;
            sendProgress(round($percentage, 2));
        }
        if (file_exists($stopFilePath)) {
            unlink($stopFilePath);
            return 1; // abort
        }
    }

    $url = $_GET['url'];
    $filename = getFilenameFromUrl($url);
    $stopFile = getStopFile($url);
    if (file_exists($stopFile)) {
        unlink($stopFile);
    }
    downloadFile($url, $filename, $stopFile);
    exit;
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>پی‌اچ‌پی دانلود اکسپرس</title>
    <link rel="icon" href="https://rastegar.info/wp-content/uploads/2023/05/cropped-Hologram.png">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap");

        body {
            height: 90vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f5f5;
            font-family: "Vazirmatn", sans-serif;
        }

        .container {
            width: 100%;
            margin: 20px;
            padding: 20px;
            max-width: 1024px;
            display: flex;
            align-items: center;
            flex-direction: column;
            justify-content: center;
            box-sizing: border-box;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        #logo {
            width: 150px;
        }

        #downloadForm {
            width: 100%;
            text-align: center;
            margin-bottom: 30px;
        }

        #progressContainer {
            width: 100%;
            text-align: center;
            border-radius: 50px;
            margin-bottom: 30px;
            background-color: #ddd;
        }

        #progressBar {
            width: 0%;
            height: 30px;
            color: white;
            line-height: 30px;
            border-radius: 50px;
            background-color: #ff0000;
        }

        #message {
            display: none;
            text-align: center;
        }

        .footer-shape {
            width: 100%;
            height: 2px;
            margin-bottom: 20px;
        }

        p {
            font-size: 12px;
            text-align: center;
        }

        input {
            width: 60%;
        }

        input,
        button {
            padding: 10px 20px;
            margin: 5px;
            border-radius: 50px;
            border: 1px solid #ddd;
            font-family: "Vazirmatn", sans-serif;
        }

        button {
            color: white;
            cursor: pointer;
            background-color: #4caf50;
            font-family: "Vazirmatn", sans-serif;
        }

        button:hover {
            background-color: #45a049;
        }
    </style>
</head>

<body>
    <div class="container">
        <img src="https://rastegar.info/wp-content/uploads/PHP-logo.png" id="logo" alt="php logo">
        <h1>پی‌اچ‌پی دانلود اکسپرس</h1>
        <form id="downloadForm">
            <input type="text" id="downloadLink" placeholder="لینک دانلود را وارد کنید">
            <button type="submit">دانلود کن</button>
        </form>
        <button id="pauseBtn" style="display:none">توقف</button>
        <div id="progressContainer">
            <div id="progressBar">0%</div>
        </div>
        <h2 id="message"></h2>
        <img src="https://rastegar.info/wp-content/uploads/2023/05/Footer-Shape.png" class="footer-shape" alt="Shape">
        <p>طراحی و توسعه توسط <a href="https://rastegar.info/php-download-express/" target="_blank">رضا رستگار</a></p>

    </div>

    <script>
        document.getElementById("progressContainer").style.display = 'none';
        let source = null;
        let currentUrl = '';

        document.getElementById('downloadForm').addEventListener('submit', function (e) {
            e.preventDefault();
            document.getElementById("progressContainer").style.display = 'block';
            currentUrl = document.getElementById('downloadLink').value;
            document.getElementById('downloadLink').value = '';
            document.getElementById('pauseBtn').style.display = 'inline-block';
            source = new EventSource("index.php?url=" + encodeURIComponent(currentUrl));


            source.onmessage = function (event) {
                let data = JSON.parse(event.data);

                if (data.percentage < 100) {
                    document.getElementById("progressBar").style.width = data.percentage + "%";
                    document.getElementById("progressBar").innerHTML = data.percentage + "%";
                } else {
                    document.getElementById("message").style.display = 'block';
                    document.getElementById("message").innerHTML = "فایل با موفقیت دانلود شد";
                    document.getElementById('pauseBtn').style.display = 'none';
                    setTimeout(function () {
                        document.getElementById("progressBar").innerHTML = "0%";
                        document.getElementById("progressBar").style.width = "0%";
                        document.getElementById("progressContainer").style.display = 'none';
                        document.getElementById("message").style.display = 'none';
                    }, 5000);

                    source.close();
                }
            };

            source.onerror = function (event) {
                source.close();
                document.getElementById('pauseBtn').style.display = 'none';
            };
        });

        document.getElementById('pauseBtn').addEventListener('click', function () {
            if (source && currentUrl) {
                fetch('index.php?pause=1&url=' + encodeURIComponent(currentUrl));
                source.close();
                source = null;
                document.getElementById('pauseBtn').style.display = 'none';
                document.getElementById("message").style.display = 'block';
                document.getElementById("message").innerHTML = 'دانلود متوقف شد';
            }
        });
    </script>
</body>

</html>