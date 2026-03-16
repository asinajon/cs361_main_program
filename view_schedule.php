<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once('mysqli_connect.php');

$user_id = (int)$_SESSION['user_id'];

// Helper Functions

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
    return ($response !== false && isset($decoded['total_show_duration'])) ? $decoded['total_show_duration'] : ($pre + $movie);
}

function get_total_cost($costs) {

    if (empty($costs)) return 0.00;

    $data = json_encode(
        [
            'costs' => $costs,
        ]
    );
    $ch = curl_init('http://127.0.0.1:5007/calculate_cost');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);

    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    return ($response !== false && isset($decoded['total_expenditure'])) ? $decoded['total_expenditure'] : array_sum($costs);
}

function render_movie_row($movie, &$i, $is_restricted = false)
{
    $bg_color = $is_restricted ? '#fff3cd' : 'transparent';
    echo '<div class="movie-row" style="background-color:' . $bg_color . ' !important; border-bottom:1px solid #ccc;padding:10px 0;margin-bottom:5px;">';
    
    // Hidden fields to preserve state on POST
    echo '<input type="hidden" name="movies[' . $i . '][show_date]" value="' . $movie['show_date'] . '">';
    echo '<input type="hidden" name="movies[' . $i . '][preshow_length]" value="' . $movie['preshow_length'] . '">';
    echo '<input type="hidden" name="movies[' . $i . '][duration_minutes]" value="' . $movie['duration_minutes'] . '">';
    echo '<input type="hidden" name="movies[' . $i . '][location]" value="' . htmlspecialchars($movie['location']) . '">';
    echo '<input type="hidden" name="movies[' . $i . '][cost]" value="' . $movie['cost'] . '">';
    
    // Hidden field so the rating persists in the POST data
    echo '<input type="hidden" name="movies[' . $i . '][rating]" value="' . htmlspecialchars($movie['rating']) . '">';

    echo '<span>' . formatTime($movie['show_time']) . '</span><br>';
    echo '<input type="text" name="movies[' . $i . '][title]" value="' . htmlspecialchars($movie['title']) . '" style="font-weight:bold;width:60%;">';
    echo '<br>';
    echo '<small>Year:</small> <input type="number" name="movies[' . $i . '][year]" value="' . $movie['release_year'] . '" style="width:60px;">';
    echo '<br>';
    
    // Displayed as read-only text
    echo '<small>Rating: ' . htmlspecialchars($movie['rating']) . '</small><br>';
    
    echo '<small>Preshow: ' . $movie['preshow_length'] . ' min</small><br>';
    echo '<small>Total: ' . $movie['total_duration'] . ' min</small><br>';
    echo '<small>Cost: $' . number_format($movie['cost'], 2) . ' | ' . htmlspecialchars($movie['location']) . '</small>';
    echo '</div>';

    $i++;
}

function render_quick_add($day_index, $raw_dates)
{
    // $day_index is array key so each day has its own "New Movie" slot
    echo '
    <div class="quick-add" style="background:#f9f9f9; padding:10px; border:1px dashed #999; margin-top:10px;">
        <h4 style="margin:0 0 10px 0; font-size:0.9rem;">Quick Add Movie</h4>
        
        <input type="hidden" name="new_movies[' . $day_index . '][show_date]" value="' . $raw_dates[$day_index] . '">
        
        <input type="text" name="new_movies[' . $day_index . '][title]" placeholder="Film Title" style="width:90%; margin-bottom:5px;">

        <div style="display:flex; gap:5px; margin-bottom:5px;">
            <input type="number" name="new_movies[' . $day_index . '][year]" placeholder="Year" style="width:65px;">
            <input type="time" name="new_movies[' . $day_index . '][show_time]" value="19:00">
        </div>

        <div style="margin-bottom:5px;">
            <small><strong>Rating:</strong></small>
            <select name="new_movies[' . $day_index . '][rating]">
                <option value="G">G</option>
                <option value="PG">PG</option>
                <option value="PG-13">PG-13</option>
                <option value="R">R</option>
                <option value="NR" selected>NR</option>
            </select>
        </div>

        <div style="margin-bottom:5px;">
            <small><strong>Preshow:</strong></small>
            <select name="new_movies[' . $day_index . '][preshow_length]">
                <option value="0">0</option>
                <option value="10">10</option>
                <option value="15" selected>15</option>
                <option value="20">20</option>
                <option value="25">25</option>
            </select>
        </div>

        <input type="text" name="new_movies[' . $day_index . '][location]" placeholder="Theater Name" style="width:90%; margin-bottom:5px;">
        
        <div style="display:flex; gap:5px;">
            <input type="number" name="new_movies[' . $day_index . '][duration_minutes]" placeholder="Mins" style="width:60px;">
            <input type="number" step="0.01" name="new_movies[' . $day_index . '][cost]" placeholder="Cost $" style="width:70px;">
        </div>
    </div>';
}

function is_age_restricted($user_dob, $show_date, $rating) {
    $rating = trim(strtoupper($rating));
    if ($rating == 'G' || $rating == 'PG' || $rating == 'NR') {
        return false;
    }
    $data = json_encode(
        [
            'birthdate' => $user_dob,
            'show_date' => $show_date,
            'rating' => $rating
        ]
    );
    $ch = curl_init('http://127.0.0.1:5008/verify_age');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);

    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    return ($response !== false && isset($decoded['is_restricted'])) ? $decoded['is_restricted'] : false;
}

// Handle Post Requests

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update Preferences
    if (isset($_POST['update_prefs'])) {
        $region = $_POST['date_region'] ?? 'US';
        $length = $_POST['date_length'] ?? 'short';

        $stmt = $dbc->prepare("UPDATE users SET date_region=?, date_length=? WHERE user_id=?");
        $stmt->bind_param("ssi", $region, $length, $user_id);
        $stmt->execute();

        header("Location: view_schedule.php");
        exit();
    }

    // Insert New Movies
    if (isset($_POST['submit_new']) && !empty($_POST['new_movies'])) {
        foreach ($_POST['new_movies'] as $movie) {
            $title = trim($movie['title'] ?? '');
            $year  = trim($movie['year'] ?? '');

            if (empty($title) || empty($year)) {
                continue;
            }

            $title_esc = mysqli_real_escape_string($dbc, $title);
            $year = (int)$year;

            $find_r = mysqli_query($dbc, "SELECT movie_id FROM movies WHERE title='$title_esc' AND release_year=$year LIMIT 1");

            if ($find_r && mysqli_num_rows($find_r) > 0) {
                $movie_id = (int)mysqli_fetch_assoc($find_r)['movie_id'];
            } else {
                $duration = (int)($movie['duration_minutes'] ?? 0);
                $rating = mysqli_real_escape_string($dbc, $movie['rating'] ?? 'NR');

                mysqli_query($dbc, "INSERT INTO movies (title,duration_minutes,rating,release_year) VALUES ('$title_esc',$duration,'$rating',$year)");
                $movie_id = mysqli_insert_id($dbc);
            }

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

        header("Location: view_schedule.php");
        exit();
    }
}

// Fetch preferences and dates

$stmt = $dbc->prepare("SELECT date_region, date_length, birthdate FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$prefs = $stmt->get_result()->fetch_assoc();
$user_dob = $prefs['birthdate'];

$user_region = $prefs['date_region'];
$user_length = $prefs['date_length'];

$raw_dates = [];
$date = new DateTime("monday this week");
for ($x = 0; $x <= 6; $x++) {
    $raw_dates[] = $date->format('Y-m-d');
    $date->modify('+1 day');
}

// Date Format Microservice
$date_info = ['format' => [$user_region, $user_length], 'dates' => $raw_dates];
$ch = curl_init('http://127.0.0.1:5005/format_dates');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($date_info));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);
$formatted_dates = $response ? json_decode($response, true) : $raw_dates;

// Fetch Schedule
$fetch_q = "SELECT ms.id, ms.show_date, ms.show_time, ms.preshow_length, ms.location, ms.cost, m.title, m.release_year, m.rating, m.duration_minutes, DATE_FORMAT(ms.show_date,'%W') AS day_of_week
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
$restricted_list = [];
?>

<div class="container">
  <h1>Weekly Movie Schedule</h1>

    <form action="view_schedule.php" method="POST" style="margin-bottom: 20px;">
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
    
    <form action="view_schedule.php" method="POST">
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
                    <?php 
                        $i = 0; 
                        $day_cost_totals = [];  
                    ?>
                    <?php foreach ($days as $day_index => $day): ?>
                        <?php $day_costs = []; ?>
                        <td valign="top" style="width: 14%;">
                            <?php
                            if (!empty($schedule[$day])) {
                                foreach ($schedule[$day] as $movie) {
                                    $day_costs[] = (float)$movie['cost'];
                                    
                                    $is_flagged = is_age_restricted($user_dob, $movie['show_date'], $movie['rating']);
                                    
                                    if($is_flagged) {
                                        $restricted_list[] = $movie['title'];
                                    }
                                    render_movie_row($movie, $i, $is_flagged);
                                }
                            }
                            $day_cost_total = get_total_cost($day_costs);
                            $day_cost_totals[] = $day_cost_total;
                            ?>
                            <div class="day-total-container" style="margin: 15px 0; padding: 10px 5px; border-top: 2px solid #444; background: #fdfdfd;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 0.8rem; color: #666; text-transform: uppercase; font-weight: bold;">Day Total</span>
                                    <span style="font-size: 1.1rem; font-weight: bold; color:#2c3e50;"> $ <?php echo number_format($day_cost_total, 2); ?> </span>
                                </div>
                            </div>
                            <?php
                            render_quick_add($day_index, $raw_dates);
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
        <?php
        if (!empty($restricted_list)) {
            echo '<div style="background:#fff3cd; border:1px solid #ffeeba; padding:15px; margin-top:20px; color:#856404;">';
            echo '<strong>Note:</strong> The following movies may exceed age requirements based on your age: ';
            echo '<em>' . implode(', ', array_unique($restricted_list)) . '</em>';
            echo '</div>';
        }
        ?>
        <div class="week-total-cost-container" style="margin: 15px 0; padding: 10px; border: 2px solid #444; background: #eee; text-align: right;">
            <span style="font-weight: bold;">TOTAL WEEKLY EXPENDITURE: </span>
            <span style="font-size: 1.3rem; font-weight: bold;">
                $<?php echo number_format(get_total_cost($day_cost_totals), 2); ?>
            </span>
        </div>

        <div style="margin-top:20px;">
            <input type="submit" name="submit_new" value="Save New Entries" style="padding: 10px 20px; background: #28a745; color: #fff; border: none; cursor: pointer;">
        </div>
    </form>
</div>
</body>
</html>