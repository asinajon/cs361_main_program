<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require('mysqli_connect.php');

$user_id = (int)$_SESSION['user_id'];

/* ---------------------------------------------------
   HELPER FUNCTIONS
---------------------------------------------------*/

function formatTime($time)
{
    return date("g:i A", strtotime($time));
}

function get_total_duration($pre, $movie)
{
    $data = json_encode([
        'pre_dur' => (int)$pre,
        'movie_dur' => (int)$movie
    ]);

    $ch = curl_init('http://127.0.0.1:5006/calculate_duration');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);

    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    return ($response !== false && $decoded) ? $decoded : ($pre + $movie);
}

function render_movie_row($movie, &$i)
{
    echo '<div class="movie-row" style="border-bottom:1px solid #ccc;padding:10px 0;margin-bottom:5px;">';
    
    // Hidden fields to preserve state on POST
    echo '<input type="hidden" name="movies[' . $i . '][show_date]" value="' . $movie['show_date'] . '">';
    echo '<input type="hidden" name="movies[' . $i . '][preshow_length]" value="' . $movie['preshow_length'] . '">';
    echo '<input type="hidden" name="movies[' . $i . '][duration_minutes]" value="' . $movie['duration_minutes'] . '">';
    echo '<input type="hidden" name="movies[' . $i . '][location]" value="' . htmlspecialchars($movie['location']) . '">';
    echo '<input type="hidden" name="movies[' . $i . '][cost]" value="' . $movie['cost'] . '">';

    echo '<span>' . formatTime($movie['show_time']) . '</span><br>';
    echo '<input type="text" name="movies[' . $i . '][title]" value="' . htmlspecialchars($movie['title']) . '" style="font-weight:bold;width:60%;">';
    echo '<br>';
    echo '<small>Year:</small> <input type="number" name="movies[' . $i . '][year]" value="' . $movie['release_year'] . '" style="width:60px;">';
    echo '<br>';
    echo '<small>Preshow: ' . $movie['preshow_length'] . ' min</small><br>';
    echo '<small>Total: ' . $movie['total_duration'] . ' min</small><br>';
    echo '<small>Cost: $' . $movie['cost'] . ' | ' . htmlspecialchars($movie['location']) . '</small>';
    echo '</div>';

    $i++;
}

function render_quick_add($day_index, $raw_dates, &$i)
{
    echo '
    <div class="quick-add">
        <input type="hidden" name="movies[' . $i . '][show_date]" value="' . $raw_dates[$day_index] . '">
        <input type="text" name="movies[' . $i . '][title]" placeholder="Film Title">

        <div style="display:flex;gap:5px;margin-bottom:5px;">
            <input type="number" name="movies[' . $i . '][year]" placeholder="Year" style="width:60px;">
            <input type="time" name="movies[' . $i . '][show_time]" value="19:00">
        </div>

        <small><strong>Rating</strong></small>
        <select name="movies[' . $i . '][rating]">
            <option value="G">G</option>
            <option value="PG">PG</option>
            <option value="PG-13">PG-13</option>
            <option value="R">R</option>
            <option value="NR">NR</option>
        </select>

        <small><strong>Preshow</strong></small>
        <select name="movies[' . $i . '][preshow_length]">
            <option value="0">0</option>
            <option value="10">10</option>
            <option value="15" selected>15</option>
            <option value="20">20</option>
            <option value="25">25</option>
        </select>

        <input type="text" name="movies[' . $i . '][location]" placeholder="Theater">
        <input type="number" name="movies[' . $i . '][duration_minutes]" placeholder="Minutes">
        <input type="number" step="0.01" name="movies[' . $i . '][cost]" placeholder="Cost">
    </div>';

    $i++;
}

/* ---------------------------------------------------
   HANDLE POST REQUESTS
---------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Update Preferences */
    if (isset($_POST['update_prefs'])) {
        $region = $_POST['date_region'] ?? 'US';
        $length = $_POST['date_length'] ?? 'short';

        $stmt = $dbc->prepare("UPDATE users SET date_region=?, date_length=? WHERE user_id=?");
        $stmt->bind_param("ssi", $region, $length, $user_id);
        $stmt->execute();

        header("Location: view_schedule_refactor.php");
        exit();
    }

    /* Insert New Movies */
    if (isset($_POST['submit_new']) && !empty($_POST['movies'])) {
        foreach ($_POST['movies'] as $movie) {
            $title = trim($movie['title'] ?? '');
            $year  = trim($movie['year'] ?? '');

            if (!$title || !$year) {
                continue;
            }

            $title_esc = mysqli_real_escape_string($dbc, $title);
            $year = (int)$year;

            // 1. Check if movie exists
            $find_r = mysqli_query($dbc, "SELECT movie_id FROM movies WHERE title='$title_esc' AND release_year=$year LIMIT 1");

            if ($find_r && mysqli_num_rows($find_r) > 0) {
                $movie_id = (int)mysqli_fetch_assoc($find_r)['movie_id'];
            } else {
                $duration = (int)($movie['duration_minutes'] ?? 0);
                $rating = mysqli_real_escape_string($dbc, $movie['rating'] ?? 'NR');

                mysqli_query($dbc, "INSERT INTO movies (title,duration_minutes,rating,release_year) VALUES ('$title_esc',$duration,'$rating',$year)");
                $movie_id = mysqli_insert_id($dbc);
            }

            // 2. Schedule insert
            $show_date = mysqli_real_escape_string($dbc, $movie['show_date'] ?? '');
            if (!$show_date) {
                continue;
            }

            $show_time = date("H:i:s", strtotime($movie['show_time'] ?? '19:00:00'));
            $preshow = mysqli_real_escape_string($dbc, $movie['preshow_length'] ?? '15');
            $location = mysqli_real_escape_string($dbc, $movie['location'] ?? '');
            $cost = isset($movie['cost']) && is_numeric($movie['cost']) ? (float)$movie['cost'] : 0.0;

            mysqli_query($dbc, "INSERT INTO movie_schedule (movie_id,user_id,show_date,show_time,preshow_length,location,cost) VALUES ($movie_id,$user_id,'$show_date','$show_time','$preshow','$location',$cost)");
        }

        header("Location: view_schedule_refactor.php");
        exit();
    }
}

/* ---------------------------------------------------
   FETCH PREFERENCES & DATES
---------------------------------------------------*/

$stmt = $dbc->prepare("SELECT date_region, date_length FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$prefs = $stmt->get_result()->fetch_assoc();

$user_region = $prefs['date_region'];
$user_length = $prefs['date_length'];

$raw_dates = [];
$date = new DateTime("monday this week");
for ($x = 0; $x <= 6; $x++) {
    $raw_dates[] = $date->format('Y-m-d');
    $date->modify('+1 day');
}

/* Date Format Microservice */
$date_info = ['format' => [$user_region, $user_length], 'dates' => $raw_dates];
$ch = curl_init('http://127.0.0.1:5005/format_dates');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($date_info));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);
$formatted_dates = $response ? json_decode($response, true) : $raw_dates;

/* Fetch Schedule */
$fetch_q = "SELECT ms.id, ms.show_date, ms.show_time, ms.preshow_length, ms.location, ms.cost, m.title, m.release_year, m.duration_minutes, DATE_FORMAT(ms.show_date,'%W') AS day_of_week
            FROM movie_schedule ms
            JOIN movies m ON ms.movie_id = m.movie_id
            WHERE ms.user_id = $user_id
            AND ms.show_date BETWEEN '{$raw_dates[0]}' AND '{$raw_dates[6]}'
            ORDER BY ms.show_date, ms.show_time";

$result = mysqli_query($dbc, $fetch_q);
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$schedule = array_fill_keys($days, []);

while ($row = mysqli_fetch_assoc($result)) {
    $row['total_duration'] = get_total_duration($row['preshow_length'], $row['duration_minutes']);
    $schedule[$row['day_of_week']][] = $row;
}

$page_title = "Weekly Movie Schedule";
include('includes/header.html');
?>

<div class="container">
    <h1>Weekly Movie Schedule</h1>

    <form method="POST" style="margin-bottom: 20px;">
        <label>Region:</label>
        <select name="date_region">
            <option value="US" <?= ($user_region == 'US') ? 'selected' : '' ?>>US</option>
            <option value="International" <?= ($user_region == 'International') ? 'selected' : '' ?>>International</option>
        </select>

        <label>Date Length:</label>
        <select name="date_length">
            <option value="short" <?= ($user_length == 'short') ? 'selected' : '' ?>>Short</option>
            <option value="long" <?= ($user_length == 'long') ? 'selected' : '' ?>>Long</option>
        </select>

        <input type="submit" name="update_prefs" value="Update View">
    </form>

    <form method="POST">
        <table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <?php foreach ($formatted_dates as $index => $date_string): ?>
                        <th>
                            <?= $days[$index] ?><br>
                            <small><?= $date_string ?></small>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php $i = 0; ?>
                    <?php foreach ($days as $day_index => $day): ?>
                        <td valign="top" style="width: 14%;">
                            <?php
                            if (!empty($schedule[$day])) {
                                foreach ($schedule[$day] as $movie) {
                                    render_movie_row($movie, $i);
                                }
                            }
                            render_quick_add($day_index, $raw_dates, $i);
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <div style="margin-top:20px;">
            <input type="submit" name="submit_new" value="Save New Entries" style="padding: 10px 20px; background: #28a745; color: #fff; border: none; cursor: pointer;">
        </div>
    </form>
</div>
</body>
</html>