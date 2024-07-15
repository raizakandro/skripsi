<?php

    session_start();
    $APIkey = "AIzaSyCTjkkpungviJNtu_QmecX1J3PjK-vjFOA";

    // Fungsi untuk mendapatkan jumlah hasil pencarian berdasarkan kata kunci
    function intToPercent($number) {
        if ($number == 0) {
            return '0%';
        }
        $percent = $number / 100;
        return number_format($percent, 2) . '%';
    }

    function safeDivide($numerator, $denominator) {
        if ($denominator == 0) {
            return 0;
        }
        return $numerator / $denominator;
    }

    function getSearchResults($apiKey, $keyword) {
        $sanitizedKeyword = urlencode($keyword);
        $oneMonthAgo = date('2024-7-15\TH:i:s\Z', strtotime('-30 days'));
        $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=$sanitizedKeyword&type=video&publishedAfter=$oneMonthAgo&key=$apiKey";

        $response = file_get_contents($url);
        $data = json_decode($response);

        if ($data && isset($data->pageInfo)) {
            return $data->pageInfo->totalResults;
        } else {
            return 0;
        }
    }

    function countVideosWithTag($api_url, $params, &$total_videos) {
        $response = callAPI($api_url, $params);
        if (isset($response['items'])) {
            $total_results = count($response['items']);
            $total_videos += $total_results;
            if (isset($response['nextPageToken'])) {
                $params['pageToken'] = $response['nextPageToken'];
                countVideosWithTag($api_url, $params, $total_videos);
            }
        }
    }

    function callAPI($url, $params) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    function hitungPencarianYouTube($kata_kunci, $APIkey) {
        return getSearchResults($APIkey, $kata_kunci);
    }

    function hitungPenggunaanTag($kata_kunci, $APIkey) {
        $api_url = 'https://www.googleapis.com/youtube/v3/search';
        $params = array(
            'key' => $APIkey,
            'part' => 'snippet',
            'q' => $kata_kunci,
            'type' => 'video',
            'maxResults' => 50,
        );

        $total_videos = 0;
        countVideosWithTag($api_url, $params, $total_videos);
        return $total_videos;
    }

    function getVideoTags($videoId, $apiKey) {
        $url = "https://www.googleapis.com/youtube/v3/videos?id={$videoId}&part=snippet&key={$apiKey}";
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (isset($data['items'][0]['snippet']['tags'])) {
            return $data['items'][0]['snippet']['tags'];
        } else {
            return array();
        }
    }

    function findPopularTags($query, $apiKey) {
        $publishedAfter = date('2024-7-15\TH:i:s\Z', strtotime('-30 days'));
        $dicari = 4;
        $encodedQuery = urlencode($query);
        $params = [
            'part' => 'snippet',
            'type' => 'video',
            'q' => $encodedQuery,
            'maxResults' => $dicari,
            'publishedAfter' => $publishedAfter,
            'regionCode' => 'ID',
            'key' => $apiKey
        ];

        $searchUrl = "https://www.googleapis.com/youtube/v3/search?" . http_build_query($params);
        $searchResponse = file_get_contents($searchUrl);

        if ($searchResponse === false) {
            return array();
        }

        $searchData = json_decode($searchResponse, true);

        if (!empty($searchData['items'])) {
            $tags = array();
            foreach ($searchData['items'] as $item) {
                $videoId = $item['id']['videoId'];
                $videoTags = getVideoTags($videoId, $apiKey);
                $tags = array_merge($tags, $videoTags);
            }

            $tagCounts = array_count_values($tags);
            arsort($tagCounts);
            $popularTags = array_slice(array_keys($tagCounts), 0, $dicari);
            return array_diff($popularTags, array($query));
        } else {
            return array();
        }
    }

    if (isset($_POST['submit1'])) {
        if (isset($_POST['kata_kunci'])) {
            $kata_kunci = $_POST['kata_kunci'];
            $jumlah_pencarian = hitungPencarianYouTube($kata_kunci, $APIkey);
            $jumlah_penggunaan_tag = hitungPenggunaanTag($kata_kunci, $APIkey);
            $rank = safeDivide($jumlah_pencarian, $jumlah_penggunaan_tag);
            $ranking = intToPercent($rank);

            $_SESSION['result1'] =
            "<table>
                <thead>
                    <tr>
                        <th>Kata Kunci</th>
                        <th>Jumlah Dicari</th>
                        <th>Jumlah Digunakan</th>
                        <th>Rank</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>$kata_kunci</td>
                        <td>$jumlah_pencarian</td>
                        <td>$jumlah_penggunaan_tag</td>
                        <td>$ranking</td>
                    </tr>
                </tbody>
            </table>";
        }
    }

    if (isset($_POST['submit2'])) {
        if (isset($_POST['kata_kunci1'])) {
            $query = $_POST['kata_kunci1'];
            $popularTags = findPopularTags($query, $APIkey);

            $_SESSION['result2'] =
                "<table id='sortableTable'>
                    <thead>
                        <tr>
                            <th onclick='sortTable(0)'>Kata Kunci</th>
                            <th onclick='sortTable(1)'>Jumlah Dicari</th>
                            <th onclick='sortTable(2)'>Jumlah Digunakan</th>
                            <th onclick='sortTable(3)'>Rank</th>
                        </tr>
                    </thead>
                    <tbody>";
            foreach ($popularTags as $tag) {
                $tagscari = hitungPencarianYouTube($tag, $APIkey);
                $tagsguna = hitungPenggunaanTag($tag, $APIkey);
                $rank1 = safeDivide($tagscari, $tagsguna);
                $ranking1 = intToPercent($rank1);

                $_SESSION['result2'] .= "
                    <tr>
                        <td>$tag</td>
                        <td>$tagscari</td>
                        <td>$tagsguna</td>
                        <td>$ranking1</td>
                    </tr>";
            }
            $_SESSION['result2'] .= "
                    </tbody>
                </table>";
        }
    }

    if (isset($_POST['submit3'])) {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Proses Kata Kunci YouTube</title>
        <style>
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #ddd;
                text-align: left;
                padding: 8px;
            }
            th {
                background-color: #f2f2f2;
                cursor: pointer;
            }
            th.sort-asc::after {
                content: " \25B2";
            }
            th.sort-desc::after {
                content: " \25BC";
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            tr:hover {
                background-color: #f1f1f1;
            }
            td {
                width: auto;
            }
        </style>
    </head>
    <body>
        <h2>Masukkan Kata Kunci</h2>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <table>
                <tr>
                    <td>
                        <input type="text" name="kata_kunci" placeholder="Masukkan kata kunci">
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" name="submit1" value="Cari Peforma">
                    </td>
                </tr>
            </table>
        </form>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="kata_kunci1" value="<?php echo isset($_POST['kata_kunci']) ? $_POST['kata_kunci'] : ''; ?>">
            <input type="submit" name="submit2" value="Cari Tags Lain">
        </form>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="submit" name="submit3" value="Reset">
        </form>

        <?php
        if (isset($_SESSION['result1'])) {
            echo "<p>{$_SESSION['result1']}</p>";
        }
        if (isset($_SESSION['result2'])) {
            echo "<p>{$_SESSION['result2']}</p>";
        }
        ?>

        <script>
            let currentSortColumn = -1;
            let currentSortDirection = 'asc';

            function sortTable(columnIndex) {
                const table = document.getElementById("sortableTable");
                const tbody = table.tBodies[0];
                const rows = Array.from(tbody.rows);
                const isNumericColumn = !isNaN(rows[0].cells[columnIndex].innerText);

                let sortDirection = 'asc';
                if (currentSortColumn === columnIndex) {
                    sortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
                }

                rows.sort((a, b) => {
                    const aText = a.cells[columnIndex].innerText;
                    const bText = b.cells[columnIndex].innerText;
                    if (isNumericColumn) {
                        return sortDirection === 'asc' ? aText - bText : bText - aText;
                    } else {
                        return sortDirection === 'asc' ? aText.localeCompare(bText) : bText.localeCompare(aText);
                    }
                });

                rows.forEach(row => tbody.appendChild(row));

                const headers = table.querySelectorAll('th');
                headers.forEach(header => header.classList.remove('sort-asc', 'sort-desc'));
                headers[columnIndex].classList.add(sortDirection === 'asc' ? 'sort-asc' : 'sort-desc');

                currentSortColumn = columnIndex;
                currentSortDirection = sortDirection;
            }
        </script>
    </body>
</html>
